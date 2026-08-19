# Ranking formula

How a final score is computed: the formula, specificity-aware relevance weighting, and the normalization curves.

## Ranking formula

The final ranking score blends normalized text relevance and the weighted business signals:

```
relevanceWeight × (_score / (_score + relevanceSaturationPoint)) + (1 - relevanceWeight) × Σ weightᵢ × signalᵢ
```

Both `relevanceWeight` and `relevanceSaturationPoint` are Zed-editable at
`/search-ranking-gui/settings` and synced to key-value storage like the metric weights.

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
`[0;1)` no matter how large `_score` gets. Recovering that upside on purpose, rather than as a side effect
of an otherwise unpredictable formula, needs real additional machinery — some way to read how specific a
query's own text is. [Specificity-aware relevance weighting](#specificity-aware-relevance-weighting-opt-in)
below is exactly that machinery, opt-in and off by default, built for a related but distinct purpose
(deciding how much a query should lean on business signals at all, not recovering this specific upside) —
the general shape of what "reading the query" looks like in this package now exists, even though it
doesn't reintroduce the old formula.

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
  set once from real `_score` values (the search-debug overlay's "Text match raw score" line is
  exactly the number to sample) and rarely touched afterwards, not guessed.

**Default `relevanceWeight` is `0.75`, not a neutral `0.5`.** Two reasons, both rooted in the
multiplicative-formula history above: (1) the old `(1 + sqrt(_score)) × (signals + baseline)` shape gave
text relevance an *unbounded* multiplier — for this catalog's typical scores (roughly 4-20, see
`relevanceSaturationPoint` above), that term swung roughly 2x-5x across weak-to-strong matches, so text
relevance was structurally dominant in practice, not a 50/50 partner with business signals. A flat `0.5`
split on the current (correctly bounded) formula would be a real behavior change, not a like-for-like
fix — it under-weights text relevance relative to what this package used to do. (2) it matches
established field guidance (e.g. Turnbull & Berryman, *Relevant Search*): text relevance should stay the
*primary* ranking signal, with business/popularity signals refining and tiebreaking rather than competing
as an equal partner — an equal-weight blend risks letting a popular-but-off-target result outrank an
exact/obviously-right match, a common and easily user-visible relevance failure. This is still a starting
point, not a measured optimum — this package deliberately scopes itself to *using* business signals to
rank, not deciding what the weights *should* be (see [What it does](../README.md#what-it-does)); an `nDCG`-style
evaluation against real rated queries, run by separate tooling on top of this one, is one principled way
to validate or refine this value once enough ratings exist.

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

## Specificity-aware relevance weighting (opt-in)

**Off by default.** A single global `relevanceWeight` is a reasonable default, but it can't tell an
exact-SKU-style query ("SKU-12345", a query whose own text is already unambiguous) apart from a
category-style query ("office chairs", a query so generic that business signals are what should actually
decide the order). This feature derives `relevanceWeight` per query instead of using one static value for
every search.

**An earlier version of this feature measured the SHAPE of the top-N `_score` distribution (Shannon
entropy) instead — that approach was replaced.** Verified live against this package's own real catalog: an
ordinary multi-term/browsy query's top-10 raw BM25 `_score`s cluster within roughly 7% of each other
regardless of how generic the query actually is, so normalized entropy came back ≈`0.9998` — numerically
indistinguishable from the theoretical maximum — for nearly every query tried, browsy or not. Only a
literal single-hit query ever produced a meaningfully different reading. In other words, entropy over
`_score` was measuring "did this query match more than one document," not "how specific is this query,"
so it couldn't actually grade the "somewhat vs. very generic" middle ground the feature exists for.

**The mechanism now measures the QUERY TEXT itself, not the resulting scores.** With the flag enabled,
`SprykerCommunity\Client\SearchRanking\Search\SpecificityWeightCalculator` fires ONE ADDITIONAL,
lightweight `_termvectors` probe per catalog search — an artificial document containing the search string,
probed against the same fields the real query searches (with `per_field_analyzer` forcing the SAME
search-time tokenization the real query uses, since `_termvectors` otherwise defaults to a field's
INDEX-time analyzer) — never a real catalog query at all, unlike the entropy-era probe this replaces. From
the response it reads each query term's real corpus document frequency (`doc_freq`) and corpus size
(`N`), derives `idf = ln(N / doc_freq)` per term (a term with zero real corpus evidence is skipped, not
treated as maximally specific), and blends the terms into one raw specificity value:

```
rawSpecificity = specificityBlendWeight × max(idf) + (1 − specificityBlendWeight) × harmonicMean(idf)
```

`max` alone would reward a single rare term even in an otherwise generic query (e.g. a SKU trailing a
common word); `harmonicMean` alone would punish a query as soon as ANY term is common, even alongside a
very rare one. Blending the two (default `specificityBlendWeight = 0.7`, favoring `max`) keeps a query
with one genuinely rare term reading as specific, while still letting an all-common-words query read as
unspecific. Real, verified idf values from this package's own catalog (`N = 1064`, `ln(N/df)`): `office`
→ `0.68`, `office chair` → `2.33`, `chair` → `2.86`, `topstar chair` → `3.57`, `topstar` → `3.71`,
`topstar M11480` → `5.79`, `M11480` (an exact SKU) → `6.28` — a sensible, continuously-graded ordering
from generic to specific, unlike the old entropy signal's two-extremes-only behavior.

The unbounded `rawSpecificity` is then normalized into `[0;1[` the same saturating way `relevanceWeight`'s
own text-relevance term already is, generalized to a Hill/Michaelis-Menten curve:

```
normalizedSpecificity = rawSpecificity^curveExponent / (rawSpecificity^curveExponent + specificitySaturationPoint^curveExponent)
```

`curveExponent = 1.0` (the default) reproduces the original `rawSpecificity / (rawSpecificity +
specificitySaturationPoint)` formula exactly. At `rawSpecificity == specificitySaturationPoint` the result
is always exactly `0.5` for any `curveExponent > 0` — the pivot never moves, only how sharply the curve
transitions around it does. A `curveExponent` above `1.0` sharpens the transition (near-binary
specific-vs-unspecific); below `1.0` flattens it (more gradual grading across the whole range).

**The configured `relevanceWeight` is a baseline the specificity result shifts, never fully replaces.** A
highly specific query shifts it up toward text relevance, an unspecific/browsy query shifts it down toward
business signals, by up to a configured maximum in either direction; a query with average specificity
(`normalizedSpecificity` exactly `0.5`, i.e. raw specificity exactly at the calibrated saturation point)
leaves the baseline untouched:

```
signedDeviation = 2 × normalizedSpecificity − 1              // −1 at 0, 0 at 0.5, +1 at 1
shapedDeviation = sign(signedDeviation) × |signedDeviation|^exponent
relevanceWeight = clamp(configuredRelevanceWeight + shiftMagnitude × shapedDeviation, 0, 1)
```

The exponent is applied to the deviation's magnitude, not to `normalizedSpecificity` directly — that
keeps `0.5` an exact neutral point regardless of the exponent's value, rather than moving it.

> [!NOTE]
> **`field_statistics.doc_count` (the `N` in `ln(N/df)`) is index-wide across every locale, not
> locale-scoped** — `_termvectors` has no way to scope it to one locale. This is an accepted approximation:
> a shop indexing one page document per store-locale per abstract product has a uniform duplication factor
> across every product, so the constant multiplicative inflation of both `N` and `df` cancels out in the
> `ln(N/df)` ratio. If your shop has uneven per-product locale coverage, this approximation may not hold —
> verify against your own catalog before relying on it.

**Five Zed-editable settings, at `/search-ranking-gui/settings`** (alongside `relevanceWeight` and
`relevanceSaturationPoint` — see [Ranking formula](#ranking-formula)) — all only take effect once the
code-level flag below is on:
- **Specificity blend weight** (default `0.7`) — `specificityBlendWeight` (α) above. Also tunable via
  `spryker-community/search-ranking-optimizer`'s own blackbox-optimizer search (e.g. CMA-ES).
- **Specificity saturation point** — `specificitySaturationPoint` (k) above. Calibration-tunable only
  (like `relevanceSaturationPoint`), not tunable by the blackbox-optimizer search — see
  `spryker-community/search-ranking-optimizer`'s Calibration feature. Needs a real value sampled from your
  own catalog before trusting the default; a placeholder chosen without that data could be wildly wrong.
- **Specificity curve exponent** (default `1.0`) — `curveExponent` above, how sharply
  `normalizedSpecificity` transitions around the saturation point. Also tunable via
  `spryker-community/search-ranking-optimizer`'s own blackbox-optimizer search (e.g. CMA-ES).
- **Specificity weight exponent** (default `1.0`) — how sharply the shift ramps up away from the neutral
  point.
- **Specificity weight shift magnitude** (default `0.25`) — the maximum shift in either direction. Sized
  to match the `0.75` `relevanceWeight` baseline: `shiftMagnitude = 1 - relevanceWeight`. A baseline above
  `0.5` has less headroom upward (toward `1.0`) than downward (toward `0.0`) before clamping;
  `relevanceWeight` itself cannot leave `[0;1]`, so this isn't a formula flaw, just where a bounded knob
  sitting near its own edge runs out of room. Sizing the magnitude to the *tighter* side means a maximally
  specific query (`normalizedSpecificity = 1`) reaches exactly `1.0` — pure text relevance — with no
  clamped/wasted resolution, while a maximally unspecific query (`normalizedSpecificity = 0`) floors at
  exactly `0.75 - 0.25 = 0.5`: the OLD global default, never lower. If you change the `relevanceWeight`
  baseline, re-derive this value as `1 - relevanceWeight` again rather than leaving it fixed.

**Why the ON/OFF switch is code-level, not one of the settings above:** it's the one control that decides
whether a second live `_termvectors` probe fires on every catalog search at all — flipping it should take
a project deploy, not just a Zed form save. Enable it in your project's
`Pyz\Client\SearchRanking\SearchRankingConfig` by overriding `isSpecificityWeightingEnabled(): bool` to
return `true`; the tuning settings become meaningful once that's done. **This is the only override point
that actually works** — `Shared\SearchRanking\SearchRankingConfig::isSpecificityWeightingEnabled()` is a
plain hardcoded `return false;`, not a project-overridable `AbstractSharedConfig`, so overriding a
`Pyz\Shared\SearchRanking\SearchRankingConfig` class has no effect. Other code that needs to ask whether
specificity weighting is live for this project — e.g. a different package reimplementing this formula for
its own evaluation tooling — should call `SearchRankingClientInterface::isSpecificityWeightingEnabled()`
rather than referencing either config class directly; it resolves through this same, genuinely
project-override-aware Client config.

**Also override `getSpecificityProbeFieldSearchAnalyzers(): array`** on the same Client config class if
your project's own `page.json` schema declares a custom search-time analyzer for its fulltext fields (this
package's own demo shop does, for synonym handling). The package's own default maps the two standard
Spryker fulltext fields to Elasticsearch/OpenSearch's built-in `standard` analyzer — safe on a vanilla
install, but almost certainly wrong once a project adds its own custom search-time analyzer, since
`_termvectors` would then tokenize differently than the real query does.

**Why it's opt-in at all:** it doubles the number of Elasticsearch/OpenSearch round trips per catalog
search. That's a real, permanent cost — worth it once you have a mixed catalog with both exact-match and
browsy query patterns, not worth paying on every search by default.

**Safety:** a failing or empty probe (no query term with real corpus evidence, a transient engine hiccup)
is caught and falls back to the configured static `relevanceWeight` unchanged — this feature can degrade
to "as if it were off" but never breaks or blocks the real search it's attached to. The same fallback
covers a KV-storage payload published before this feature existed: a project that enables the flag before
its first post-upgrade Zed save still gets sane defaults, not an exception.

**Visible in the search-debug overlay.** `SearchRankingFunctionScoreQueryExpanderPlugin` hands its
`SpecificityWeightingResult` off to `SearchRankingClient` (the one instance the Locator guarantees stays
the same across this package's plugins for the whole request), so
`SearchRankingProductDebugDataExpanderPlugin` can read the SAME result back later, when building the
overlay — not an independent, stale config lookup. Two effects:

- The overlay's "Relevance weight (α)" line and the closing combination formula use the per-query
  effective weight specificity weighting actually applied, not the static configured one — the formula
  stays reproducible-by-eye against the real final score even with specificity weighting on.
- A second "Specificity weighting" section appears (only when the feature actually ran for that query),
  directly above the "Relevance weight (α)" line it explains the shift for, listing the configured
  baseline, the measured normalized specificity, the shift, and the resulting effective weight — so the
  debug overlay explains *why* `relevanceWeight` moved, right next to its new value, not just the value
  itself elsewhere on the page.

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
[What it does](../README.md#what-it-does) and
[Why full republish, not a partial score-only ES update](design-decisions.md).

Examples:

```
atan(x / avg) / (pi() / 2)   # saturating curve, ~0.5 at the average, approaches 1 for outliers
x / max                      # linear scaling relative to the best performer
random()                     # random tie-breaker signal in ]0;1]
```

Every result is clamped into ]0;1] (lower bound `1.0E-6`, see `SearchRankingConfig`), so a
misbehaving formula cannot poison the data with zeros, negatives, `NaN` or `INF`.
