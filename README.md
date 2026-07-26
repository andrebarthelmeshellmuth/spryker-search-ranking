# Spryker Search Ranking

Data-driven search ranking for Spryker Commerce OS: rank search results by **business signals**
(PDP impressions, sales, or anything else you can measure) instead of relying on string matching
alone. Based on Spryker's
[data-driven ranking best practice](https://docs.spryker.com/docs/pbc/all/search/latest/base-shop/best-practices/data-driven-ranking).

Designed as the companion package to
[spryker-community/search-debug](https://github.com/andrebarthelmeshellmuth/spryker-search-debugger) —
the eventual `function_score` ranking is meant to stay fully inspectable in the search-debug overlay.

The standout piece: a data-driven normalization-authoring GUI. As you type a formula, the server
evaluates it against the metric's own real distribution and draws the curve live, alongside ranked
closed-form curve-fit suggestions — no guessing what shape a business signal should take:

![The metric edit page: a live, labeled-axis SVG preview of the typed normalization formula (with a legend distinguishing it from the metric's own empirical-CDF reference line) plotted against the metric's own real distribution, with ranked closed-form curve-fit suggestions (atan, saturating-ratio, log, sigmoid, power, linear) each showing their R² fit and a one-click "use this formula" action](docs/screenshots/normalization-authoring.png)

## Contents

- [Terminology](#terminology)
- [Status](#status)
- [What it does today](#what-it-does-today)
- [Ranking formula](#ranking-formula)
- [Why full republish, not a partial score-only ES update](#why-full-republish-not-a-partial-score-only-es-update)
- [Normalization formulas](#normalization-formulas)
- [Modules](#modules)
- [Requirements](#requirements)
  - [Search engine compatibility](#search-engine-compatibility)
- [Installation](#installation)
  - [1. Install the package](#1-install-the-package)
  - [2. Register the core namespace](#2-register-the-core-namespace)
  - [3. Register the console commands](#3-register-the-console-commands)
  - [4. Register the data import plugins](#4-register-the-data-import-plugins)
  - [5. Add the import entities to your data-import YAML](#5-add-the-import-entities-to-your-data-import-yaml)
  - [6. Translations for the Zed GUI](#6-translations-for-the-zed-gui)
  - [7. Register the Zed navigation entry](#7-register-the-zed-navigation-entry)
  - [8. Schedule the normalization cron](#8-schedule-the-normalization-cron)
  - [9. Register the Elasticsearch export plugins](#9-register-the-elasticsearch-export-plugins)
  - [10. Register the package's search schema directory](#10-register-the-packages-search-schema-directory)
  - [11. Register the ranking-configuration sync queue](#11-register-the-ranking-configuration-sync-queue)
  - [12. Register the function_score query expander](#12-register-the-function_score-query-expander)
  - [13. Optional: register the search-debug overlay section](#13-optional-register-the-search-debug-overlay-section)
  - [14. Build](#14-build)
  - [15. Verify the installation](#15-verify-the-installation)
- [Import file formats](#import-file-formats)
- [Limitations (current phase)](#limitations-current-phase)
- [Roadmap](#roadmap)
  - [Tuning layer moved to a separate package](#tuning-layer-moved-to-a-separate-package)
  - [Deferred (v1.1+)](#deferred-v11)
- [Testing and CI](#testing-and-ci)
  - [Automated checks](#automated-checks)
  - [Test suite](#test-suite)
- [License](#license)
- [Acknowledgements](#acknowledgements)

## Terminology

A quick reference for terms this README reuses across many sections. Each is explained in full where
it's first introduced in context — this is a lookup index, not a replacement for those explanations.

### metric

A named business signal (e.g. `pdp_impressions`, `top_seller`) with its own weight, normalization
formula, active flag, and direction. See [What it does today](#what-it-does-today).

### weight

How much one metric's signal contributes to the combined business-signal score, relative to the other
active metrics. See [Ranking formula](#ranking-formula).

### raw value / normalized value

The real-world number for one metric on one product (e.g. "8,250 impressions"), and the `]0;1]` value
its formula maps that number to. See [What it does today](#what-it-does-today).

### signal

A metric's own normalized value — used interchangeably with "metric" once normalization, not the raw
real-world number behind it, is the topic.

### digest

A metric's precomputed distribution snapshot — min/max/mean/median plus a 101-point percentile/empirical-CDF
backbone — rebuilt by the normalization cron and read by the normalization-authoring GUI's live preview
and curve-fit suggestions, so neither ever touches the raw per-product rows directly. See [What it does
today](#what-it-does-today).

### relevanceWeight

Shorthand `α`. The single knob for how much of the final score comes from normalized text relevance vs.
the combined business-signal score. See [Ranking formula](#ranking-formula).

### relevanceSaturationPoint

Shorthand `k`. The raw Elasticsearch `_score` at which normalized relevance reaches exactly 0.5 — a
search-infra tuning constant, not a business knob. See [Ranking formula](#ranking-formula).

## Status

Feature-complete and verified for its scope: business-signal search ranking, including the
normalization-authoring GUI's data-driven curve-fitting workflow. More tools (tuning, evaluation,
auto-tune) are planned as a separate package.

Verified: dependency floors resolved and checked at their oldest allowed versions (`composer
check-floors`), the ranking formula's `function_score`/`script_score` cross-validated across three
engines and two Lucene generations (see [Search engine compatibility](#search-engine-compatibility)), 107
tests, phpcs and phpstan level 6 clean.

This package's own mechanism (phases 1–4.5) is functional and complete: the metric/value data model, the
Zed management UI, CSV data import, the normalization cron, the export of normalized signals into the
Elasticsearch page documents (`scores` field), the **`function_score` ranking itself**: catalog searches
are re-scored by `relevanceWeight × normalizedRelevance + (1 - relevanceWeight) × Σ weightᵢ × signalᵢ`,
with the metric weights and the two blend constants editable in Zed and synchronized to key-value storage
— see [Ranking formula](#ranking-formula) for the full rationale — and a **data-driven
normalization-authoring GUI**: a live preview of the typed formula against the metric's own real
distribution, plotted alongside the theoretical max-discrimination reference curve, with ranked
closed-form curve-fit suggestions.

The tuning layer originally planned as phases 5/6 of this package — SRP weight-slider live preview, a
propose/review/apply workflow, `rank_eval`-based offline evaluation, and a monthly auto-tune job — is now
being built as a separate, dependent package,
[spryker-community/search-ranking-optimizer](https://github.com/andrebarthelmeshellmuth/spryker-search-ranking-optimizer),
so this package's own scope stays "use business signals to rank," not also "decide what the weights should
be." See [Roadmap](#roadmap).

## What it does today

- **Ranking metrics** (`spy_search_ranking_metric`): named business signals (e.g. `pdp_impressions`,
  `top_seller`, `random`) with a **weight** for their future contribution to the combined score, an
  **active** flag, a **normalization formula** stored as an expression string, and a **direction** flag
  (`isHigherBetter`) — whether a higher raw value is the better outcome (sales, impressions) or a lower
  one (days-since-restock, return rate). Direction is business knowledge that cannot be inferred from the
  data; it only steers which curve-fit suggestions the normalization GUI offers below, never the formula
  itself.
- **Product values** (`spy_search_ranking_product_metric`): one row per (metric, abstract product)
  pair holding the **raw real-world value** (e.g. "8,250 impressions") and the **normalized value
  in ]0;1]** derived from it. Unique per pair, removed by cascade with either parent.
- **Metric history** (`spy_search_ranking_metric_history`): every time a metric's formula, weight,
  active flag or direction actually changes — via the Zed edit form today, or a future automated
  parameter-tuning job — a snapshot is appended: the new config, the metric's [digest](#digest) at that moment
  (min/max/mean/median/percentiles, null if none existed yet), and the new formula's R² against that
  digest. Append-only, never updated; deliberately **not** a hard foreign key to the live metric row, so
  history outlives a later rename or deletion. A save that changes nothing (a re-submitted, unmodified
  form) writes no row — this is a change log, not an access log. `metricName` is denormalized for the
  same outlive-the-live-row reason.

  The `isChange` flag on every row (always `true` for today's writer) exists for a specific future need:
  a periodic drift-detection job should compare a metric's CURRENT digest against the digest **as of its
  last real change**, not merely against last month's snapshot — otherwise the comparison window silently
  resets every period regardless of whether anything happened, and gradual multi-month drift can stay
  invisible because each individual month-over-month delta looks small. Concretely: if a monthly check
  finds the fit still adequate at 30 days and changes nothing, the *next* check should compare against the
  30-day-old baseline grown to 60 days, not reset to "vs. 30 days ago" again — `isChange` is what lets that
  job find the right anchor point (the newest row where `isChange = true`) instead of just the newest row.
  A future check-only run (fit still fine, nothing changed) would append its own row with `isChange =
  false`, extending the timeline without moving the anchor.
- **Zed UI**:

  ![The metrics list: ID, name, weight, formula, active/inactive status, and edit/delete actions for every configured business signal](docs/screenshots/metrics-list.png)

  - `/search-ranking-gui` — metric list with create/edit/delete. Formulas are validated on save by
    trial evaluation; the exact parser error is shown on the form. Metric names are checked for
    uniqueness. Deletion is a CSRF-protected POST.
  - `/search-ranking-gui/product-metric` — read-only, searchable table of all product values
    (abstract SKU, metric, raw value, normalized value, last update).

    ![The Product Values page: raw and normalized value per (abstract SKU, metric) pair, paginated across the whole catalog](docs/screenshots/product-values.png)
  - `/search-ranking-gui/metric-history` — read-only, searchable, newest-first table of every row in
    `spy_search_ranking_metric_history`: the metric name and formula at that point in time, weight,
    active flag, direction, the formula's R² fit against the digest at that moment (a dash when no
    digest existed yet), and whether the row is a real change or a check-only snapshot (see `isChange`
    above). Each row links back to the live metric's edit page via its `fkSearchRankingMetric` (the
    metric itself may since have been renamed or deleted — the link simply 404s in that case, since the
    history row intentionally keeps no hard foreign key). No filtering by metric or date range yet; add
    a `?id-search-ranking-metric=` query-string constraint here if that becomes necessary once the table
    grows.

    ![The Metric History page: newest-first rows showing each config change's formula, weight, active status, direction, fit quality (R²), whether it was a real change or check-only snapshot, and when it was recorded](docs/screenshots/metric-history.png)
  - **Normalization-authoring preview** (on the metric edit page): as you type a formula, a debounced
    request asks the server to evaluate it at ~100 sample points spanning the metric's own real
    distribution (never a JS reimplementation of the expression-language math) and draws the resulting
    curve as an inline SVG, alongside the **empirical CDF** — the theoretical max-discrimination
    normalizer (map each raw value to the fraction of products below it) — as a reference line. Below the
    plot, ranked **curve-fit suggestions** (`atan(x/k)`, `x/(x+k)`, `log(1+x/k)/log(1+max/k)`, `pow(x/max,
    p)`, a sigmoid, linear, and — for `isHigherBetter=false` metrics — an `exp(-x/tau)` decay) show how
    closely each closed-form shape tracks that empirical CDF (R²), with a one-click "use this formula"
    action. The best fit is a data-driven *default*, never imposed — a metric can have a legitimate
    business reason (e.g. a rating signal where only 4.5★+ should approach 1) to deviate from the
    statistically "ideal" spread.
- **Data import**: importer types `search-ranking-metric` (upsert by name) and
  `search-ranking-product-metric` (resolves metric name + abstract SKU to foreign keys, writes raw
  values only — normalized values are never imported). Both writer steps go straight to Propel rather
  than through `SearchRankingFacade` — the standard Spryker DataImport convention, needed since a
  per-row facade call would add mapping/validation/transaction overhead on top of what the
  batch/transaction machinery around each step already does. The one real consequence: importing a
  metric this way skips the formula validation and history recording `saveMetric()` normally does, so a
  malformed formula in a CSV row surfaces later — as a per-metric skip reported by the next
  `search-ranking:normalize` run — rather than failing the import immediately. The product-metric
  importer has no such gap: there's no facade-level "save one product metric" method it bypasses, this
  direct upsert is the only path that data ever takes.
- **Normalization cron**: `vendor/bin/console search-ranking:normalize` recalculates every
  normalized value of every active metric **except the random tie-breaker metric** (see below) in
  batches. A metric whose formula fails to evaluate is skipped and reported (non-zero exit code)
  without aborting the run for the other metrics. As a byproduct, it also rebuilds each active metric's
  **distribution digest** (`spy_search_ranking_metric_digest`): min/max/mean/median plus a 101-point
  empirical-CDF backbone (percentiles 0, 1, 2, ..., 100), computed by sorting that metric's raw values
  once — this is the data the normalization-authoring preview above reads, so it never has to touch the
  raw per-product rows directly, however many there are.
- **Random tie-breaker cron**: `vendor/bin/console search-ranking:randomize` is a separate, nightly
  command that reshuffles ONE metric — the one configured as the random tie-breaker
  (`SearchRankingConfig::getRandomMetricName()`, `random` by default) — and republishes affected
  products, on its own cadence, independent of the hourly normalize run above. It is a deliberate no-op
  (exit 0, no work done) whenever that metric does not exist or is not active, so it is always safe to
  keep scheduled regardless of whether the metric happens to be turned on. Kept separate from the hourly
  cron because reshuffling a tie-breaker every hour would make search result order visibly churn for a
  shopper who searches again shortly after — nightly is frequent enough to keep ties from calcifying into
  a permanent order without looking unstable. Reuses the same full-republish path as every other score
  update; see [Why full republish, not a partial score-only ES update](#why-full-republish-not-a-partial-score-only-es-update).
- **`search-ranking:check-compatibility`**: probes the live search engine's ACTUAL capabilities —
  never a version-string comparison, since OpenSearch and Elasticsearch report incompatible version
  identifiers under the same API surface (this stack self-reports `distribution: opensearch, 1.3.4`; a
  bare Elasticsearch cluster reports a version number with no `distribution` field at all). Fires
  `_validate/query` cluster-wide for each construct the package uses today or could use in a future
  phase (`function_score` + `script_score`, `rank_feature`, `distance_feature`, `pinned`) and a
  deliberately incomplete `_rank_eval` request to check whether that endpoint is recognized at all,
  reading back the engine's own parser response either way rather than trusting a claimed version.
  Read-only — every probe only asks "would the engine accept this?", it never touches real documents or
  indices. Exits non-zero only if `function_score` + `script_score` is unsupported (the construct the
  live phase-3 ranking actually depends on today); every other probed capability is purely
  forward-looking and never affects the exit code, only the printed report.
- **Elasticsearch export**: the package ships a `page.json` fragment defining a dynamic `scores`
  object field (per Spryker's data-driven-ranking best practice) and a ProductPageSearch plugin trio
  — bulk data loader, data expander, map expander — that writes each product's normalized values
  into its page document as `scores: {metricName: value}`. Products without values get no `scores`
  field. After normalizing, the cron triggers `Product.product_abstract.publish` events (chunked)
  for all scored products so the documents refresh; `--skip-publish` suppresses that — a full
  product-page republish rather than a partial score-only write, deliberately; see
  [Why full republish, not a partial score-only ES update](#why-full-republish-not-a-partial-score-only-es-update).
- **function_score ranking**: a `QueryExpanderPlugin` wraps the catalog search query in a
  `function_score` with the painless script
  `params.relevanceWeight * (_score / (_score + params.relevanceSaturationPoint)) + (1 - params.relevanceWeight) * (params.w0 * doc['scores.metric'] + …)` —
  `boost_mode: replace`, weights and both blend constants as script params, every doc-value access
  guarded against missing fields, and metric names validated against a strict pattern before being
  embedded (import data cannot inject script code). See [Ranking formula](#ranking-formula) for why
  it's shaped this way. It only acts on queries **with a search string** (category/browse pages keep
  their ordering) and silently steps aside when no configuration is synchronized or no active metric
  has a non-zero weight.
- **Ranking configuration in key-value storage**: active metric weights + the two blend constants
  ([`relevanceWeight`](#relevanceweight), [`relevanceSaturationPoint`](#relevancesaturationpoint)) live in one dictionary document
  (`kv:search_ranking_configuration`), published from Zed through a storage table with
  synchronization behavior. Metric CRUD, the settings form, and the cron all republish it. Both blend
  constants are Zed-editable at `/search-ranking-gui/settings`. Metric weights are **normalized to sum
  to 1 at publish time** (`RankingConfigurationStorageWriter`) — see [Ranking formula](#ranking-formula)
  for why. The raw values in `spy_search_ranking_metric.weight` are untouched; only this published copy
  is normalized.
- **search-debug overlay integration** (optional, needs
  [spryker-community/search-debug](https://github.com/andrebarthelmeshellmuth/spryker-search-debugger)):
  `SearchRankingProductDebugDataExpanderPlugin` adds a "Business signals" section to the per-product
  SRP overlay — one `signal × weight = contribution` line per metric, their total, the saturation point
  (`k`) and the normalized relevance it produces (`score/(score+k)`), the relevance weight (`α`), and
  the full blend formula, e.g. `0.60 × 0.37 + (1 - 0.60) × 0.53 =`, reconciling exactly with the final
  score shown directly below it. The formula plugs in the already-shown normalized-relevance value
  directly rather than repeating `score / (score + k)` inline, and spells out `(1 - α)` literally
  instead of pre-subtracting it into a single number, so it stays visibly tied to the `α` line just
  above it. Every number in that section is rounded to
  `SprykerCommunity\Shared\SearchDebug\SearchDebugConfig::SCORE_DECIMAL_PLACES` (default **3**) — the
  same constant search-debug's own overlay numbers use, so the whole page shows one consistent
  precision; see that package's README for details. Rounding happens only at this display step — the
  underlying floats used for the actual ranking calculation stay full-precision throughout.

## Ranking formula

The final ranking score blends normalized text relevance and the weighted business signals:

```
relevanceWeight × (_score / (_score + relevanceSaturationPoint)) + (1 - relevanceWeight) × Σ weightᵢ × signalᵢ
```

Both `relevanceWeight` and `relevanceSaturationPoint` are Zed-editable at
`/search-ranking-gui/settings` and synced to key-value storage like the metric weights.

![The Ranking Formula Settings page: relevanceWeight and relevanceSaturationPoint, each with inline help text explaining what it controls and how to pick a value](docs/screenshots/settings.png)

**Why not just multiply, e.g. `(1 + sqrt(_score)) × (signals + baseline)`?** An earlier version of
this package did exactly that, with an additive `signalBaseline` constant keeping products without
business signals from being multiplied towards zero. The problem: Elasticsearch's raw `_score` is
unbounded and query-shape-dependent — a query matching more terms, or rarer terms, produces a much
higher score than one matching a single common term, with no ceiling — while business signals are
normalized to `[0;1]` by design. Combining an unbounded, query-dependent number with a bounded one
directly means the *relative* influence of business signals over text relevance drifts unpredictably
from query to query, and the additive baseline has no principled value — it's tuned by eye until
results look right.

That said, the old formula wasn't all bad: `sqrt(_score)` never saturates, it keeps growing (slowly) as
`_score` grows, so on long, specific queries where many rare terms match and `_score` gets large, weight
naturally drifted back toward text relevance instead of staying pinned to the business signals — a
property the saturating curve below deliberately gives up, since it caps `_score`'s contribution at
`[0;1)` no matter how large `_score` gets. Recovering that upside on purpose, instead of as a side effect
of an otherwise unpredictable formula, is deferred to `spryker-community/search-ranking-optimizer`: an
"entropy knob" plus a second query.

**`relevanceWeight` and `relevanceSaturationPoint` fix that by normalizing first.**
`_score / (_score + relevanceSaturationPoint)` is the same saturating-curve shape BM25 itself uses for
term-frequency saturation (also known from Michaelis-Menten kinetics): it maps the unbounded `_score`
onto `[0;1)`, reaching exactly 0.5 when `_score == relevanceSaturationPoint`. With both sides of the
blend now on the same `[0;1]` scale, `relevanceWeight` becomes one single, interpretable knob — "how
much of the final score comes from text relevance vs. business signals" — rather than an implicit
multiplicative interaction plus an unexplainable additive constant.

The two constants play different roles operationally:
- **`relevanceWeight`** is a **business knob**. It's a genuine 0–1 tradeoff someone might reasonably
  A/B test — how much should business performance move the needle relative to text match.
- **`relevanceSaturationPoint`** is a **search-infra tuning constant**. It has no universal correct
  value — it depends entirely on this shop's own field boosts and typical query shapes. It should be
  set once from real `_score` values (the search-debug overlay's "Text match total score" line is
  exactly the number to sample) and rarely touched afterwards, not guessed.

One practical constraint shaped this design: `script_score` inside `function_score` runs *per
document*, with no visibility into the score distribution of the other candidates in the result set.
A saturating function only ever needs the current document's own `_score`, so it drops into the
existing single-pass query without any architecture change — true min-max/percentile normalization
across the whole candidate set would need a two-stage rescore pipeline instead.

One visible consequence: the final `_score` is now always in `[0;1]` (a weighted blend of two `[0;1]`
terms), rather than the "few units" range the old multiplicative formula produced — purely cosmetic,
not a ranking-correctness change.

**The business-signal term also needs to genuinely stay in `[0;1]` for any of this to hold — which
depends on the metric weights, and those are Zed-editable with no upper bound.** The metric form only
constrains a single weight to be `>= 0` (see `MetricForm`); nothing stops an admin from giving three
active metrics a weight of 1.0 each, which would let `Σ weightᵢ × signalᵢ` reach 3 and swamp
`relevanceWeight`'s intended meaning. The fix: **active metric weights are normalized to sum to 1
before publishing** (`RankingConfigurationStorageWriter::normalizeMetricWeights()`) —
`usedWeightᵢ = enteredWeightᵢ / Σⱼ enteredWeightⱼ`. Since each metric's own normalized signal is
already clamped into `]0;1]`, and the used weights now sum to exactly 1, `Σ usedWeightᵢ × signalᵢ` is a
genuine **convex combination** of values already inside `]0;1]` — guaranteed to stay in that interval,
not just usually fine. With a single active metric this always normalizes to 1 regardless of the raw
number entered (it's 100% of the active-weight sum either way); with several, e.g. `top_seller = 3.0`
and `pdp_impressions = 1.0` both active, they publish as `0.75` and `0.25`. Normalization happens once,
at publish time — not independently in `FunctionScoreBuilder` and `ScoreSectionBuilder` — so the live
ranking query and the search-debug overlay can never silently disagree on the numbers, and the debug
overlay's `signal × weight = contribution` lines show the real, effective weight actually used, not
whatever raw number was typed into the Zed form. All-zero (or zero active metrics) is left as-is rather
than dividing by zero — `FunctionScoreBuilder` already treats that as "no usable business signal" and
steps aside to pure text relevance.

## Why full republish, not a partial score-only ES update

After normalizing, the cron re-triggers the standard `Product.product_abstract.publish` event for every
scored product — the same full product-page export every other product change (price, stock, category)
already goes through — rather than writing just the changed `scores.*` values directly into Elasticsearch.
**We do full updates always for business score updates because:**

- **A partial ES update isn't actually cheap at the storage layer.** Lucene segments are immutable, so
  there is no such thing as patching one field of an existing document in place — Elasticsearch's own
  `_update` API internally does a read-modify-write: fetch the current `_source`, merge the given fields
  in, delete the old Lucene document, index a new one. The write cost on the Elasticsearch side is
  essentially the same as a full document replace either way. Whatever is saved by "only touching scores"
  is saved entirely on the **Zed side** (skipping the Propel queries and the other `ProductPageSearch`
  plugins for price/images/categories/stock), not on the Elasticsearch side.
- **The publish/queue pipeline's resilience would otherwise be lost.** `Product.product_abstract.publish`
  goes through Spryker's normal event → queue → consumer path — retryable, store-aware, already the
  well-tested mechanism every other republish need in this shop relies on. A direct Zed-side ES write
  (raw Elastica, same landmine as firing search queries from Zed) would be a synchronous call with no
  retry: one failure mid-batch leaves the run partially stale with no recovery path.
- **The full republish quietly self-heals other drift for scored products.** Re-collecting the whole
  product document from Propel on every normalize run also picks up anything else that changed since the
  last export (price, stock, category) for that product. A scores-only write would not — it would become
  a second, silently-diverging write path for the same document.
- **A partial update racing a concurrent full republish is a real lost-update risk.** `_update`'s
  read-then-merge reads whatever `_source` happens to be current at that moment; without optimistic
  concurrency control (`_seq_no`/`_primary_term`), a scores-only write that read a stale document could
  overwrite a concurrent price/stock change's fields back to what it saw, even though neither write was
  "wrong" on its own. Always re-collecting a fresh document from Propel sidesteps this entirely.

**You should change this if** the update cadence stops being "hourly cron over the scored subset" and
becomes near-real-time (every few minutes) at a high scored-product count, to the point where the Zed-side
data-collection cost (not the Elasticsearch write cost, which will not improve) is the actual bottleneck.
Even then, the right design is not a raw synchronous Zed → Elasticsearch call: it is a dedicated, lightweight
synchronization resource + queue consumer carrying just `{idProductAbstract: scores}`, reusing the exact
pattern this package already uses for its own ranking-configuration document
(`spy_search_ranking_configuration_storage` / `SearchRankingConfigurationSynchronizationDataPlugin`) —
same queue-based resilience and store-awareness, just a narrower payload than the full product page. That
is real new infrastructure, not a quick tweak, and is not worth building ahead of an actual need for it.

## Normalization formulas

Formulas are [symfony/expression-language](https://symfony.com/doc/current/components/expression_language.html)
expressions, evaluated in PHP by the cron — never shipped to Elasticsearch or the browser. Per
product row these variables are available:

| Variable | Meaning |
| --- | --- |
| `x` | the row's raw value |
| `min`, `max`, `avg`, `count` | aggregates of the metric's raw values across all products, computed once per metric and run |

Registered functions: `atan`, `tanh`, `log`, `log10`, `sqrt`, `exp`, `abs`, `pi`, `pow`, `round`,
`min`, `max` (all delegating to the PHP natives) and `random()` — uniform in ]0;1], ignores `x`.
The demo `random` metric is therefore not a special case anywhere in the *formula* code: its formula is
literally `random()`. It IS special-cased one level up, in scheduling: `search-ranking:normalize` (the
hourly cron above) skips whichever metric is configured as the random tie-breaker, and a separate
`search-ranking:randomize` cron refreshes only that one, nightly — see
[What it does today](#what-it-does-today) and
[Why full republish, not a partial score-only ES update](#why-full-republish-not-a-partial-score-only-es-update).

Examples:

```
atan(x / avg) / (pi() / 2)   # saturating curve, ~0.5 at the average, approaches 1 for outliers
x / max                      # linear scaling relative to the best performer
random()                     # random tie-breaker signal in ]0;1]
```

Every result is clamped into ]0;1] (lower bound `1.0E-6`, see `SearchRankingConfig`), so a
misbehaving formula cannot poison the data with zeros, negatives, `NaN` or `INF`.

## Modules

| Module | Purpose |
| --- | --- |
| `SearchRanking` (Zed) | Propel schema, facade (CRUD, settings, formula validation, normalization, ES export/publish, metric-history recording), expression evaluator, distribution-digest builder, curve-fit suggester, formula-preview builder, per-formula fit evaluator, ProductPageSearch export plugins, `search-ranking:normalize` / `search-ranking:randomize` console commands |
| `SearchRanking` (Client) | `SearchRankingFunctionScoreQueryExpanderPlugin` + painless script builder |
| `SearchRankingGui` | Zed UI controllers, tables, forms (metrics + settings), navigation entry |
| `SearchRankingStorage` (Zed) | Ranking-configuration storage table with synchronization behavior, publish writer, sync-data plugin |
| `SearchRankingStorage` (Client) | Reads the configuration document from key-value storage |
| `SearchRankingDataImport` | The two data importers; example CSVs in `data/import/` |

## Requirements

- Spryker B2B/B2C/Marketplace shop
- PHP >= 8.3
- `symfony/expression-language` ^6 or ^7 (usually already present transitively)

Dependency floors are verified, not guessed: `composer check-floors` resolves the declared constraints to
their oldest allowed versions, and every vendor symbol the package references is checked to exist in that
tree. PHP 8.2 is *not* supported — every `spryker/propel-orm` release that resolves under
`minimum-stability: stable` (the ones with a real, non-beta `propel/propel` dependency) requires PHP
>= 8.3.

### Search engine compatibility

**Verified against real engine output from all of:**

| engine | Lucene | result |
|---|---|---|
| OpenSearch 1.3.4 | 8.10.1 | ✅ |
| OpenSearch 2.11.0 | 9.7.0 | ✅ |
| Elasticsearch 8.11.0 | 9.8.0 | ✅ |

The verification: a throwaway index with a `full-text` text field and a `scores` object (the same shape
this package exports into a real catalog's page documents), two documents with known metric values, a
plain `multi_match` query to capture the baseline text-relevance `_score`, then the exact
`function_score`/`script_score` painless shape `FunctionScoreBuilder` generates — with fixed
`relevanceWeight`/`relevanceSaturationPoint`/metric-weight parameters — fired on all three engines. Every
engine returned the identical blended `_score`, to float32 precision, matching a hand-computed expected
value from the same formula — not just consistent with each other, but confirmed correct.

Elasticsearch 7.x has not been run against real output, but sits inside the verified range: it is the
fork point OpenSearch 1.x descends from, and both neighbours on either side are verified. Same
Apache-2.0-fork-point reasoning as [spryker-community/search-debug's own engine-compatibility
section](https://github.com/andrebarthelmeshellmuth/spryker-search-debugger#search-engine-compatibility) —
this package's own painless usage (`doc['field'].value`, `containsKey`, `size()`) is bog-standard,
available on both lineages since well before the fork, so no engine-specific behavior was expected or
found.

## Installation

### 1. Install the package

Not yet published on Packagist — install from a path repository:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/spryker-community/search-ranking",
        "options": { "symlink": true }
    }
]
```

```bash
composer require spryker-community/search-ranking:@dev
```

### 2. Register the core namespace

In `config/Shared/config_default.php` (skip if already present, e.g. for search-debug):

```php
$config[KernelConstants::CORE_NAMESPACES] = [
    // ...
    'SprykerCommunity',
];
```

### 3. Register the console commands

In `Pyz\Zed\Console\ConsoleDependencyProvider::getConsoleCommands()`:

```php
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckCompatibilityConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingNormalizeConsole;
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingRandomizeConsole;
use SprykerCommunity\Zed\SearchRankingDataImport\SearchRankingDataImportConfig;

new SearchRankingNormalizeConsole(),
new SearchRankingRandomizeConsole(),
new SearchRankingCheckCompatibilityConsole(),
new SearchRankingCheckInstallationConsole(),
// optional per-entity import commands:
new DataImportConsole(DataImportConsole::DEFAULT_NAME . static::COMMAND_SEPARATOR . SearchRankingDataImportConfig::IMPORT_TYPE_SEARCH_RANKING_METRIC),
new DataImportConsole(DataImportConsole::DEFAULT_NAME . static::COMMAND_SEPARATOR . SearchRankingDataImportConfig::IMPORT_TYPE_SEARCH_RANKING_PRODUCT_METRIC),
```

### 4. Register the data import plugins

In `Pyz\Zed\DataImport\DataImportDependencyProvider::getDataImporterPlugins()`:

```php
use SprykerCommunity\Zed\SearchRankingDataImport\Communication\Plugin\DataImport\SearchRankingMetricDataImportPlugin;
use SprykerCommunity\Zed\SearchRankingDataImport\Communication\Plugin\DataImport\SearchRankingProductMetricDataImportPlugin;

new SearchRankingMetricDataImportPlugin(),
new SearchRankingProductMetricDataImportPlugin(),
```

### 5. Add the import entities to your data-import YAML

Anywhere **after** the abstract products are imported:

```yaml
- data_entity: search-ranking-metric
  source: data/import/common/common/search_ranking_metric.csv
- data_entity: search-ranking-product-metric
  source: data/import/common/common/search_ranking_product_metric.csv
```

### 6. Translations for the Zed GUI

Zed's `trans` filter does **not** read from the Glossary module (that's a Yves/Client-facing,
Redis-backed mechanism) — it uses `spryker/translator`'s own CSV-catalog loader, which only scans
`vendor/spryker/*`, `vendor/spryker-shop/*` and `vendor/spryker-feature/*` by convention. Since this
package lives under `vendor/spryker-community/*`, extend your project's
`Pyz\Zed\Translator\TranslatorConfig::getCoreTranslationFilePathPatterns()` **once** to also scan that
namespace:

```php
$coreTranslationFilePathPatterns[] = APPLICATION_VENDOR_DIR . '/spryker-community/*/data/translation/Zed/[a-z][a-z]_[A-Z][A-Z].csv';
```

That one addition auto-discovers this package's [`data/translation/Zed/en_US.csv`](data/translation/Zed/en_US.csv)
and [`de_DE.csv`](data/translation/Zed/de_DE.csv) — no per-project copy step, unlike the Yves-side
glossary convention. Add further locale CSVs here (or override the translated text) as your project
needs, then refresh the cache:

```bash
vendor/bin/console translator:clean-cache
vendor/bin/console translator:generate-cache
```

### 7. Register the Zed navigation entry

Unlike the translations above, Zed's navigation menu has no glob-based auto-discovery for
`vendor/spryker-community/*` — this is standard Spryker behavior, not something specific to this
package (core `spryker/cms-gui`'s own README documents the identical step for itself). Copy the
`<search-ranking-gui>` block from this package's
[`src/SprykerCommunity/Zed/SearchRankingGui/Communication/navigation.xml`](src/SprykerCommunity/Zed/SearchRankingGui/Communication/navigation.xml)
into your project's own `config/Zed/navigation.xml`, then rebuild the navigation cache — delete the
generated cache files first, since `navigation:build-cache` alone re-serializes whatever is already
cached rather than recomputing it:

```bash
rm -f src/Generated/Zed/Navigation/codeBucket/navigation*.cache
vendor/bin/console navigation:build-cache
```

That gives the full "Search Ranking" sidebar group with all 4 visible pages (Metrics, Product Values,
Settings, History). If you'd rather not duplicate the whole page list, copying just the
top-level `<search-ranking-gui>` entry (drop the `<pages>` block) still gives one working sidebar link
into `/search-ranking-gui` — the individual pages stay reachable via their own in-page action buttons
(Back to Metrics, View Product Values, etc.) instead of a dropdown.

> Calibration (empirically sampling `relevanceSaturationPoint` k from real query scores) used to live
> here as a sub-page of this menu. It has moved to the separate
> [spryker-community/search-ranking-optimizer](https://github.com/andrebarthelmeshellmuth/spryker-search-ranking-optimizer)
> package — see [Roadmap](#roadmap). This package no longer ships a `search-ranking:calibrate` command or a
> Calibration page.

### 8. Schedule the normalization cron

E.g. hourly, in `Pyz\Zed\SymfonyScheduler\SymfonySchedulerConfig::getCronJobs()`:

```php
'search-ranking-normalize' => [
    'command' => '$PHP_BIN vendor/bin/console search-ranking:normalize',
    'schedule' => '0 * * * *',
],
'search-ranking-randomize' => [
    'command' => '$PHP_BIN vendor/bin/console search-ranking:randomize',
    'schedule' => '0 3 * * *',
],
```

Besides normalizing, each run triggers publish events so the search documents pick up the fresh
scores (suppress with `--skip-publish`). `search-ranking:randomize` is intentionally on its own,
less-frequent schedule — see [What it does today](#what-it-does-today) for why — and is a safe no-op
to leave scheduled even if the random tie-breaker metric is inactive or does not exist.

**Should do, if you already had search-ranking installed before `search-ranking:randomize`
existed:** registering a console command and scheduling a cron job are both project-level files this
package cannot reach into and update for you — `composer update`-ing the package alone does not add
either. Confirm both `new SearchRankingRandomizeConsole()` (step 3) and the
`search-ranking-randomize` cron entry above are actually present in your project's own
`ConsoleDependencyProvider` and `SymfonySchedulerConfig`, not just documented here.

### 9. Register the Elasticsearch export plugins

In `Pyz\Zed\ProductPageSearch\ProductPageSearchDependencyProvider`:

```php
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingPageDataLoaderPlugin;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingScoresDataExpanderPlugin;
use SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch\SearchRankingScoresMapExpanderPlugin;

// getDataLoaderPlugins():
new SearchRankingPageDataLoaderPlugin(),

// getDataExpanderPlugins():
$dataExpanderPlugins[SearchRankingConfig::PLUGIN_SEARCH_RANKING_SCORES_DATA] = new SearchRankingScoresDataExpanderPlugin();

// getProductAbstractMapExpanderPlugins():
new SearchRankingScoresMapExpanderPlugin(),
```

### 10. Register the package's search schema directory

The core schema loader only scans `vendor/spryker/*`, so extend
`Pyz\Zed\SearchElasticsearch\SearchElasticsearchConfig`:

```php
public function getJsonSchemaDefinitionDirectories(): array
{
    $directories = parent::getJsonSchemaDefinitionDirectories();
    $directories[] = sprintf('%s/vendor/spryker-community/*/src/*/Shared/*/Schema/', APPLICATION_ROOT_DIR);

    return $directories;
}
```

**Why ship `Schema/page.json` inside the package instead of asking you to paste the `scores` mapping
into your own project's `src/Pyz/Shared/*/Schema/page.json`?** The latter would technically work with
zero PHP changes — core's own default already globs `APPLICATION_SOURCE_DIR/*/Shared/*/Schema/` — but it
would mean hand-copying a JSON snippet into project-owned files, with no automatic way to pick up future
changes to that mapping; every schema tweak in a future release would need a manual re-copy, shop by
shop. Shipping the fragment in the package instead means it just flows through `composer update` like
any other change, the same way every core module (`search-elasticsearch`, `product-list-search`,
`merchant-product-search`, ...) ships its own `Schema/page.json` and relies on core's `vendor/spryker/*`
glob rather than asking integrators to copy anything. The one downside is that core's glob only covers
`vendor/spryker/*`, not `vendor/spryker-community/*` — hence this one-time override. It only needs to
happen once per project, though, not once per package: it also covers `search-debug` and
`search-ranking-optimizer`'s own schema fragments, and any other `spryker-community/*` package installed
later, for free.

### 11. Register the ranking-configuration sync queue

The queue `sync.storage.search_ranking` needs the usual three registrations, plus a fourth if your
shop routes queues through Symfony Messenger (current demoshops do):

```php
// Pyz\Client\RabbitMq\RabbitMqConfig::getSynchronizationQueueConfiguration():
SearchRankingStorageConfig::SYNC_STORAGE_SEARCH_RANKING_QUEUE,

// Pyz\Zed\Queue\QueueDependencyProvider::getProcessorMessagePlugins():
SearchRankingStorageConfig::SYNC_STORAGE_SEARCH_RANKING_QUEUE => new SynchronizationStorageQueueMessageProcessorPlugin(),

// Pyz\Zed\Synchronization\SynchronizationDependencyProvider::getSynchronizationDataPlugins():
new SearchRankingConfigurationSynchronizationDataPlugin(),

// Pyz\Client\SymfonyMessenger\SymfonyMessengerConfig::getSynchronizationQueueConfiguration():
SearchRankingStorageConfig::SYNC_STORAGE_SEARCH_RANKING_QUEUE,
```

(`SearchRankingStorageConfig` = `SprykerCommunity\Shared\SearchRankingStorage\SearchRankingStorageConfig`.)
Restart any long-running `symfonymessenger:consume` workers afterwards — they hold the old queue
configuration until their time limit expires.

This also assumes your project defines a `synchronizationPool` queue pool
(`Pyz\Client\RabbitMq\RabbitMqConfig::getQueuePools()`) — standard in Spryker demoshops, but not
guaranteed in a from-scratch project. `Zed\SearchRankingStorage\SearchRankingStorageConfig::getSearchRankingSynchronizationPoolName()`
hardcodes that name because this sync resource is store-less (its schema has no `store` column), and a
store-less synchronization resource must have a queue pool or message creation fails outright ("You must
specify either store column or SynchronizationQueuePoolName"). If your project's pool has a different
name, override that method in a project-level `SearchRankingStorageConfig`.

### 12. Register the function_score query expander

In `Pyz\Client\Catalog\CatalogDependencyProvider::createCatalogSearchQueryExpanderPlugins()`,
**after `FacetQueryExpanderPlugin`** — earlier expanders require the root query to still be a
`BoolQuery`:

```php
use SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin;

new FacetQueryExpanderPlugin(),
new SearchRankingFunctionScoreQueryExpanderPlugin(),
```

### 13. Optional: register the search-debug overlay section

With spryker-community/search-debug installed, extend its client dependency provider on project
level (`Pyz\Client\SearchDebug\SearchDebugDependencyProvider`):

```php
use SprykerCommunity\Client\SearchRanking\Plugin\SearchDebug\SearchRankingProductDebugDataExpanderPlugin;

protected function getProductDebugDataExpanderPlugins(): array
{
    return [
        new SearchRankingProductDebugDataExpanderPlugin(),
    ];
}
```

### 14. Build

```bash
vendor/bin/console transfer:generate
vendor/bin/console propel:install
vendor/bin/console navigation:build-cache
vendor/bin/console search:setup:source-map   # regenerates PageIndexMap incl. the scores field
vendor/bin/console search:setup:sources      # merges the scores field into the live index mapping
```

The "Search Ranking" section then appears in the Back Office navigation, and after the next
normalize run + queue processing the page documents carry `scores`.

### 15. Verify the installation

```bash
vendor/bin/console search-ranking:check-installation
```

Most of the steps above fail *silently* when missed — a forgotten DependencyProvider wire-up or an
un-run cron produces no error, just a ranking that quietly stays pure text relevance. This command checks
the core namespace registration, that every console command from step 3 actually registered, that the
search engine is reachable, that a page index exists and carries the `scores` field this package's export
plugins add, and that at least one active metric is configured. It exits non-zero and names the remedy for
whatever is wrong.

It is explicit about its own blind spots: running in Zed, it cannot confirm the Yves-side `function_score`
query expander (step 12) is registered, or that a live search result order actually reflects the
configured weights — those need a real storefront search request, not a CLI probe. Distinct from
`search-ranking:check-compatibility`: that command asks "does this engine support what the package
needs", this one asks "is this installation wired up correctly."

## Import file formats

`search_ranking_metric.csv`:

```csv
name,weight,formula,is_active
pdp_impressions,0.3,atan(x / avg) / (pi() / 2),1
top_seller,0.5,x / max,1
random,0.2,random(),1
```

`search_ranking_product_metric.csv` (raw values only — normalized values are computed by the cron):

```csv
abstract_sku,metric_name,raw_value
001,pdp_impressions,8250
001,top_seller,132
001,random,0
```

Example files ship in this package under `data/import/`.

## Limitations (current phase)

- The `function_score` applies to the **main catalog search query only** — suggest-as-you-type and
  concrete-product search keep pure text relevance for now.
- Re-publishing of product documents happens **only via the normalize cron** (or manually) —
  importing raw values alone does not refresh the search documents until the next run.
- Values are per abstract product and global — no per-store or per-locale signals; the ranking
  configuration document is also global (one key for all stores).
- With Spryker's **direct synchronization** enabled, core only flushes the sync buffer on console
  termination; this package flushes explicitly after publishing so Zed web saves reach key-value
  storage immediately.

## Roadmap

- [x] **Phase 1** — metric definitions, product values, Zed UI, data import, normalization cron
- [x] **Phase 2** — export normalized signals into the Elasticsearch page index
- [x] **Phase 3** — `function_score` query wrapping the catalog search with weighted business signals, weights + Zed-editable blend constants (`relevanceWeight`, `relevanceSaturationPoint`) synced to key-value storage
- [x] **Phase 4** — score breakdown integration with spryker-community/search-debug
- [x] **Phase 4.5** — normalization-authoring GUI: distribution digest, live formula preview against the
      metric's own real distribution, ranked closed-form curve-fit suggestions, and the `isHigherBetter`
      direction flag. Numbered 4.5 (a phase-1 signal-axis enhancement) rather than 5, since it is logically
      upstream of the tuning-layer work now built in spryker-community/search-ranking-optimizer (see
      below) — better normalization improves the inputs that layer operates on.
- [x] **`search-ranking:check-compatibility`** — probes the search engine's actual capabilities
      (`_validate/query` + a `_rank_eval` recognition check) rather than trusting a version string
- [x] **`search-ranking:check-installation`** — diagnoses an installation of this package itself (core
      namespace, sibling console command registration, search engine reachability, the `scores` field in
      the live page index mapping, at least one active metric configured) — see
      [15. Verify the installation](#15-verify-the-installation)

### Tuning layer moved to a separate package

What was originally planned as this package's phases 5/6 — SRP weight-tuning sliders, a tier-2/tier-3
propose/review/apply workflow with named checkpoints, `rank_eval`-based offline evaluation, and a monthly
auto-tune job — is being built in
[spryker-community/search-ranking-optimizer](https://github.com/andrebarthelmeshellmuth/spryker-search-ranking-optimizer)
instead: a real, one-directional dependent of this package, not a part of it. That keeps this package's
own scope to "use business signals to rank," not also "decide what the weights should be." See that
package's own README for its roadmap.

The **Calibration** feature (upload a list of search terms → fire the live catalog query for each →
pool the raw text-relevance scores into a suggested `relevanceSaturationPoint` k) has already been built
and now ships in that package too — it was previously a page of this one's Zed menu and a
`search-ranking:calibrate` console command, both since removed here. Calibration is a "decide what k
should be" activity, so it belongs with the tuning layer; the optimizer applies its result back into this
package's `relevanceSaturationPoint` setting through this package's own facade (a `suggest`-level
dependency, so this package still installs and runs without it).

### Deferred (v1.1+)

- **GAP 1 — `rank_features` field type** — needs prototyping/benchmarking and a reindex; must ship
  together with a search-debug `ExplanationParser` update since it changes the explain-tree shape.
  Realistically a v2-scale item, not v1.1.
- **GAP 3 — recency/time decay** (`gauss`/`exp`/`linear` decay, `distance_feature`) — cheap, no reindex
  needed, arguably the #2 commerce signal after text relevance. Not required for v1.0's coherence, but
  a natural v1.1 candidate given the low cost.
- **GAP 5 — demotion + curation** — soft-expressible today via inverted formulas; first-class support
  and merchandiser pinning (hand-rolled, since OpenSearch has no `pinned` query) can wait.

## Testing and CI

### Automated checks

`.github/workflows/ci.yml` runs on every push and pull request:

| check | what it protects |
|---|---|
| `composer validate` | the manifest stays well-formed |
| `phpcs` (PHP 8.3, 8.4) | coding standard, via this package's own `phpcs.xml` |
| `composer check-floors` (PHP 8.3, 8.4) | the declared dependency floors are real |
| `phpmd` (`phpmd.xml` + `phpmd-public-methods.xml`) | cyclomatic/NPath complexity, method/class length stay reasonable — run as two separate invocations because PHPMD merges every loaded ruleset's `exclude-pattern` into one global file list per run, and only the public-method-count rule should skip Facades/Factories (Spryker's own DI convention gives each one method per capability/collaborator, not a design problem this package can fix) |

`check-floors` is the one worth understanding. This package's `require` constraints are a promise about
which Spryker versions an adopter may install — and that promise is exactly what a development shop
cannot verify, because a full demo shop has every Spryker module present regardless of what this package
declares. A missing declaration only surfaces on a leaner shop, as a fatal, after installation.

So the check resolves every constraint to its **oldest** allowed version (`composer update
--prefer-lowest --prefer-stable --no-dev`) and then asserts that every vendor symbol used in `src/`
actually exists in that tree. It exits non-zero if not. Run it locally the same way:

```bash
composer check-floors
```

It reports three categories: resolved, host-generated (`Generated\*` transfer classes from
`transfer:generate`, and `Orm\*` Propel classes from `propel:install` — both build artifacts of the host
project, correctly absent from any vendor tree), and optional-absent (symbols from the `suggest`ed
`spryker-community/search-debug`, whose every use — `ScoreSectionBuilder`,
`SearchRankingProductDebugDataExpanderPlugin` — is guarded by that package never being autoloaded/wired
unless a project deliberately installs and registers both).

This audit is what caught two real, otherwise-invisible problems: an undeclared dependency on
`spryker/catalog` (needed a floor of `^5.7.0` specifically — versions below that still construct Elastica's
`Match` query class by its pre-PHP-8 name, a hard parse error under PHP 8.3+), and a `php >= 8.2` claim
that was actually false — every `spryker/propel-orm` release resolvable under `minimum-stability: stable`
(the ones depending on a real, non-beta `propel/propel`) requires PHP >= 8.3.

### Test suite

**107 tests, 910 assertions** across four Codeception suites (`Zed/SearchRanking`,
`Zed/SearchRankingStorage`, `Client/SearchRanking`, `Client/SearchRankingStorage`). From a shop that has
the package installed:

```bash
vendor/bin/codecept build -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRanking
vendor/bin/codecept run -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRanking
```

Covers the formula evaluator (functions, `random()` range, division-by-zero/unknown-function
failures, validation messages), the normalizer (clamping, batch paging, per-metric error isolation, and
that the random tie-breaker metric is skipped entirely regardless of its active flag), the page-data
loader (per-payload score mapping), the publish trigger (event chunking), the function_score builder
(script shape, zero-weight skipping, script-injection guarding, null on empty configuration), the
distribution-digest builder (percentile-backbone correctness, order-independence), the curve fitter (a
linearly-spread digest recovers a near-perfect linear fit, a saturating digest fits clearly better than
linear, `isHigherBetter=false` swaps in the decay candidate), the formula-preview builder backing the
normalization-authoring GUI's live SVG preview (the "no digest yet" error, the happy path building
CDF/formula points and curve-fit candidates, and formula-evaluation failure returning a fresh error
transfer rather than a half-populated chart), the metric randomizer (no-op when the metric is missing or
inactive, re-normalizes and republishes only when it is active), the shared R² calculator and per-formula
fit evaluator (both feeding the metric-history snapshot), and the metric writer's history recording (an
initial row for a brand-new metric, no row when nothing tracked actually changed, the digest snapshot and
fit quality captured correctly when a formula change has an existing digest to compare against) as pure
unit tests — no database needed. The Client suite lives at
`tests/SprykerCommunityTest/Client/SearchRanking`.

Two tests in that Client suite are real integration tests, not unit tests: `FunctionScoreExecutionTest`
builds a real `function_score` and executes it against real documents in a test-owned index, and
`EngineCompatibilityCheckerTest` runs `EngineCompatibilityChecker`'s real `_validate/query` probes against
the actual cluster — both need a reachable search engine, though still no database.

Coverage (Codeception + pcov): the Zed suite covers 90% of classes / 95.97% of lines; the uncovered
remainder is almost entirely Spryker's own Facade/Factory DI-wiring boilerplate (thin delegation, not
meaningfully unit-testable — the same convention `phpmd`'s public-method-count rule already exempts them
from) plus a handful of deep floating-point edge cases in the curve-fitter's grid-search fallback.

For that reason the suites are **not** part of CI: a clean runner has neither a Spryker shop nor a search
cluster, and standing both up per build would cost far more than it returns. CI therefore covers the
static guarantees; the test suite is run against a real shop before a release.

Static analysis (`phpstan`) is likewise run from a host shop rather than in CI: it needs the generated
`Generated\Shared\Transfer\*` classes, which only exist once a project has run `transfer:generate`.

## License

MIT — see [LICENSE](LICENSE).

## Acknowledgements

Search Ranking is an original project, but it reflects more than a decade of building search solutions
for e-commerce. Along the way, I had the privilege of working with engineers whose ideas and experience
shaped my approach to search engineering.

I'd particularly like to thank:

- **Martin Loetsch** — for the architectural ideas behind Contorion's early search platform.
- **Krešimir Slugan** — who handed over Contorion's search implementation to me and demonstrated an
  uncompromising focus on performance.
- **Alberto Reyer** (formerly Assmann) — for sharing the history and rationale behind Spryker Search's
  original design decisions and the engineering trade-offs behind them.

I'd also like to acknowledge the Spryker engineering team for creating an extensible platform that made
community packages like Search Ranking possible.

Any mistakes, questionable design decisions or bugs in this project are, of course, entirely my own.
