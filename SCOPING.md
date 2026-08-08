# Store/locale scoping in search-ranking

Every field this package manages lives at one of two scopes: **global**, or **store+locale** — every
store-scoped table in this package's own schema has a `locale_name` column, no exceptions. Store+locale
storage doesn't mean store+locale BEHAVIOR though: a metric's own `isLocaleScoped` flag decides, per
metric, whether sibling locale rows of a store are kept fanned-out-identical (the default) or allowed to
genuinely diverge (see step 5 below). Mixing these up is the single easiest way to misconfigure this
package — assuming a store-wide metric's formula secretly differs by locale (it doesn't, by design), or
wondering why a locale-scoped metric's per-locale edit didn't apply everywhere (it isn't supposed to).
This file is the single place that answers "what scope is this field at, and why" for everything the
package touches. [README.md](README.md) explains what each field *does*; this explains *where it lives*.

## How a result gets its score, and where each piece lives

```
finalScore = relevanceWeight × (score / (score + relevanceSaturationPoint)) + (1 − relevanceWeight) × Σ weightᵢ × signalᵢ
```

### 1. Elasticsearch/OpenSearch `_score`

The engine's own raw text-match signal, computed fresh per query from BM25/tf-idf. This package doesn't
own it or store it anywhere — it just reads it off the response.

**Scope:** inherited from how *you* structured your indices, not from anything this package controls. Most
Spryker projects index one page-document set per store×locale, so `_score` ends up store+locale-specific
in practice — but that's a consequence of your indexing setup, not a rule enforced here.

### 2. `relevanceSaturationPoint` (k)

The raw `_score` at which normalized relevance hits exactly 0.5 (`score / (score + k)`). It only means
anything relative to *your* real score distribution — a k of 12 is meaningless without knowing what
typical scores look like for the queries it'll actually see. See
[Calibration](README.md#what-it-does) for how a starting value gets suggested from real traffic rather
than guessed.

**Scope:** store+locale ([`relevanceSaturationPoint`](README.md#relevancesaturationpoint), Settings page).
Calibrated against one locale's real query traffic — reusing it for a locale with different
vocabulary/catalog density would silently miscalibrate the curve.

### 3. `relevanceWeight` (α)

The blend knob: how much of the final score is "does the text match" vs. "is this a good product." Not a
universal constant — a market that trusts its business data more (rich conversion history) can lean lower;
a newly launched one might lean higher until that data matures.

**Scope:** store+locale ([`relevanceWeight`](README.md#relevanceweight), same Settings page as k — they're
tuned together, against the same real traffic).

### 4. Business signals — `Σ weightᵢ × signalᵢ`

Each metric (`pdp_impressions`, `top_seller`, …) contributes its normalized value, times how much it should
count. **`signalᵢ` has to be provided — nothing computes it for you.** The pipeline is:

1. **Raw value** — the real-world number (e.g. "8,250 impressions" for one abstract SKU). Imported
   explicitly, per (store, locale), via `search_ranking_product_metric.csv` or your own integration, into
   `spy_search_ranking_product_metric`. A metric with a weight and a formula fully configured but zero
   imported raw values contributes nothing at all — the Product Value Gaps page
   ([What it does](README.md#what-it-does)) exists specifically to surface that silent gap.
   **Scope:** store+locale — the same real-world fact can genuinely differ by market and language. The
   CSV's `locale` column itself can hold a comma-separated, quoted list (`"de_DE,en_US"`) to fan the same
   raw value out to every listed locale at import time — a plain CSV convenience, independent of
   `isLocaleScoped` below, and available even for a metric that's genuinely per-locale everywhere else.
   See [Import file formats](README.md#import-file-formats).
2. **Formula / normalization** — the expression that maps that raw value into a `]0;1]` signal (e.g.
   `atan(x / avg) / (pi() / 2)`). For most metrics, a business-behavior signal like `pdp_impressions` is a
   store-level reality (conversion/stock/warehouse facts), not a language preference — but a genuinely
   different formula per locale IS expressible (see step 5's `isLocaleScoped` flag below; the classic case
   is a text-derived signal whose raw-value distribution differs by language, needing e.g. `2 * atan(x)` in
   one locale and `3 * atan(x)` in another to normalize comparably).
   **Scope:** store+locale (`spy_search_ranking_metric_store_config`, one row per metric × store × locale
   unconditionally), fanned out store-wide by default via `isLocaleScoped=false` — see step 5 for whether
   sibling locale rows of a store are kept identical or allowed to diverge. `isActive` and curve `shape`
   live at the same tier, governed by the same flag.
3. **Metric identity** — the name and direction (`isHigherBetter`) that make `pdp_impressions` mean the
   same thing everywhere it's configured at all.
   **Scope:** global — definitional, not configurable per scope.

### 5. `isLocaleScoped` — the one flag that decides both formula's AND weight's real granularity

`isLocaleScoped` (`spy_search_ranking_metric`, global per metric, default `false`) is a single root fact
about the metric: does it genuinely differ per locale within a store, or is it store-wide? It answers that
question once, and the answer cascades through *everything* scoped at store+locale tier for that metric —
formula/isActive/shape (step 4) and weight (below) both consult the SAME flag, not one flag each. There is
no such thing as "locale-scoped weight but store-wide formula" for one metric.

- **`false`** (default, most metrics): this metric is a store-wide fact (e.g. `top_seller`, driven by
  sales/stock data that doesn't vary by language). Saving formula/isActive/weight for any ONE locale of a
  store fans the write out to every real locale of that store automatically — the admin edits one value
  per store instead of keeping N locale copies in sync by hand.
- **`true`** (rare — evidence first, see below): this metric genuinely differs per locale. Formula,
  isActive, shape, AND weight are then authored and stored independently per (store, locale); saving one
  locale's value never touches another.

**How to decide which a metric should be:** don't guess. `evaluateCurrentMetricFitAcrossLocales()` (see
[What it does](README.md#what-it-does)) fits the metric's CURRENT formula against each real
locale's own digest and reports the spread — a wide spread is the evidence that this metric's underlying
data genuinely differs by language and a per-locale formula (not just per-locale weight) would fit better.
`search-ranking-optimizer`'s own `auto-tune` job surfaces this same diagnostic automatically every run (see
that package's own README) — a low-effort way to catch a candidate for the flag without running anything
manually.

**Both storage tiers are `(metric, store, locale)`-shaped unconditionally, whether or not the flag is
set** — `spy_search_ranking_metric_store_config` and `spy_search_ranking_metric_weight` both always have
one real row per real locale of a store. The flag governs the WRITE PATH (does a save fan out or stay
scoped), never the storage shape — a genuinely different value per locale is always technically possible
to store, `isLocaleScoped=false` just keeps the write path from ever letting sibling rows diverge on
purpose. The Metrics list's "Scope" column shows which mode each metric is in.

Two independent fan-out mechanisms touch these same tables, worth telling apart:
- **`isLocaleScoped=false`** — a runtime property of the metric itself. Every save (Zed GUI or API) of
  formula, isActive, or weight fans out to *every real locale* of the store, no matter which locale you
  were viewing.
- **The import CSVs' comma-separated `locale` list** (`search_ranking_metric.csv` and
  `search_ranking_product_metric.csv`, same mechanism as raw values above) — an import-time convenience.
  Fans out to *whichever locales that row explicitly lists*, which can be all of them, a subset, or just
  one — and works regardless of `isLocaleScoped`, since the CSV doesn't consult that flag at all.

`SearchRankingFacadeInterface::resolveEffectiveWeightLocales(idSearchRankingMetric, storeName, localeName)`
answers "which locales would a save for this metric actually touch?" up front — the same fan-out decision
`saveMetric()`/`saveMetricWeight()` make internally (both consult it, so formula and weight can never
disagree about which locales a write touches), exposed so a caller (this package's own Scope Copy, or a
dependent package like `search-ranking-optimizer`) can know the real blast radius of a write before
committing it, not just after.

### 6. Specificity-aware relevance weighting (optional)

Before applying the blend above, the query itself gets analyzed: rare terms mean precise intent (lean
toward text relevance), common terms mean exploratory search (lean toward business signals). See
[Specificity-aware relevance weighting](README.md#specificity-aware-relevance-weighting-opt-in) for the
full mechanism.

- **On/off switch** (`isSpecificityWeightingEnabled`) — whether this analysis runs at all; enabling it
  makes every live catalog search fire an additional Elasticsearch probe, a real cost worth deciding
  deliberately.
  **Scope:** global, code-only — not a Zed-editable setting on purpose (see the Settings page's own note
  on why).
- **The five tuning knobs** (`specificityBlendWeight`, `specificitySaturationPoint`,
  `specificityCurveExponent`, `specificityWeightExponent`, `specificityWeightShiftMagnitude`) — same
  reasoning as k in step 2: calibrated against real term-frequency statistics from *your* index.
  **Scope:** store+locale, same Settings page as α and k.

## Quick reference

| Field | Scope | Where it's stored | Edited on |
|---|---|---|---|
| Metric `name` / `isHigherBetter` (direction) | Global | `spy_search_ranking_metric` | Metrics page |
| `isLocaleScoped` flag | Global (per metric) | `spy_search_ranking_metric` | Metrics page |
| `isSpecificityWeightingEnabled` | Global, code-only | hardcoded `Config` override | not Zed-editable |
| `formula` / `isActive` / curve `shape` | Store+locale (store-wide via `isLocaleScoped=false` at runtime — the default) | `spy_search_ranking_metric_store_config` | Metrics page (Scope Copy: **Sync store configuration**) |
| Raw product-metric value | Store+locale (import CSV's `locale` column can list several, comma-separated, to fan one value across them) | `spy_search_ranking_product_metric` | data import / your integration |
| Metric `weight` | Store+locale (store-wide via `isLocaleScoped=false` at runtime — same flag as formula/isActive/shape; import CSV's `locale` column can also list several to fan out at import time) | `spy_search_ranking_metric_weight` | Metrics page (Scope Copy: **Copy configuration**) |
| `relevanceWeight` (α), `relevanceSaturationPoint` (k) | Store+locale | `spy_search_ranking_setting` | Settings page (Scope Copy: **Copy configuration**) |
| 5 specificity knobs | Store+locale | `spy_search_ranking_setting` | Settings page (Scope Copy: **Copy configuration**) |
| Published ranking-configuration KV document | Store+locale | key-value storage, one document per store×locale | derived, not directly edited |

This is also why [Scope Copy](README.md#what-it-does) is split into two separate actions rather than one:
**Copy configuration** copies weight + settings, **Sync store configuration** copies formula/isActive/shape
— two different config groups, each with its own independent source/target store+locale picker, so picking
a scope for one never resets or gets confused with the other's. (Both actions now genuinely respect a
locale-scoped metric's own per-locale values, not just a store-wide default — see step 5 above.)
