# Store/locale scoping in search-ranking

Every field this package manages lives at one of four scopes: **global**, **store**, **store+locale**, or
"**store+locale storage, store-wide behavior**" (a metric with `isLocaleScoped=false` — see step 5 below).
Mixing these up is the single easiest way to misconfigure this package — copying a store-only field as if
it were per-locale, or wondering why a locale picker does nothing on a store-only page. This file is the
single place that answers "what scope is this field at, and why" for everything the package touches.
[README.md](README.md) explains what each field *does*; this explains *where it lives*.

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
   `atan(x / avg) / (pi() / 2)`). A business-behavior signal like `pdp_impressions` is a store-level reality
   (conversion/stock/warehouse facts), not a language preference.
   **Scope:** store only (`spy_search_ranking_metric_store_config`) — deliberately not split further by
   locale. `isActive` and curve `shape` live at the same store-only tier, for the same reason.
3. **Metric identity** — the name and direction (`isHigherBetter`) that make `pdp_impressions` mean the
   same thing everywhere it's configured at all.
   **Scope:** global — definitional, not configurable per scope.

### 5. Signal weights — `weightᵢ`

How much this metric counts relative to the others. A tuning DECISION, not a fact — reasonable to let it
vary by locale even where the underlying business data doesn't.

**Scope:** store+locale by default (`spy_search_ranking_metric_weight`, one row per metric × store ×
locale — see [`weight`](README.md#weight)). Whether a given metric genuinely needs that granularity is
itself a per-metric choice, the global `isLocaleScoped` flag (default `true`):
- **`true`** (default): weight is authored and stored independently per locale, as above.
- **`false`**: this metric is a store-wide fact (e.g. `top_seller`, driven by sales/stock data that doesn't
  vary by language). Saving the weight for any ONE locale of a store fans it out to every real locale of
  that store automatically — the admin edits one number per store instead of keeping N locale copies in
  sync by hand.

**The storage tier and the practical behavior can disagree, and that's the point of the flag, not a bug:**
`spy_search_ranking_metric_weight`'s column is *capable* of holding a different value per locale for any
metric — the table itself never enforces uniformity. `isLocaleScoped=false` is what makes the write path
keep every locale row of a store identical on purpose. The metric list's "Weight scope" column shows which
mode each metric is in.

Two independent fan-out mechanisms exist for this same table, worth telling apart:
- **`isLocaleScoped=false`** — a runtime property of the metric itself. Every save (Zed GUI or API) fans
  out to *every real locale* of the store, no matter which locale you were viewing.
- **The import CSV's comma-separated `locale` list** (`search_ranking_metric.csv`, same mechanism as raw
  values above) — an import-time convenience. Fans out to *whichever locales that row explicitly lists*,
  which can be all of them, a subset, or just one — and works regardless of `isLocaleScoped`, since the
  CSV doesn't consult that flag at all.

`SearchRankingFacadeInterface::resolveEffectiveWeightLocales(idSearchRankingMetric, storeName, localeName)`
answers "which locales would a `saveMetricWeight()` call for this metric actually touch?" up front — the
same fan-out decision `saveMetricWeight()` makes internally, exposed so a caller (this package's own Scope
Copy, or a dependent package like `search-ranking-optimizer`) can know the real blast radius of a write
before committing it, not just after.

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
| `formula` / `isActive` / curve `shape` | Store only | `spy_search_ranking_metric_store_config` | Metrics page (Scope Copy: **Sync store configuration**) |
| Raw product-metric value | Store+locale (import CSV's `locale` column can list several, comma-separated, to fan one value across them) | `spy_search_ranking_product_metric` | data import / your integration |
| Metric `weight` | Store+locale (store-wide via `isLocaleScoped=false` at runtime; import CSV's `locale` column can also list several to fan out at import time) | `spy_search_ranking_metric_weight` | Metrics page (Scope Copy: **Copy configuration**) |
| `relevanceWeight` (α), `relevanceSaturationPoint` (k) | Store+locale | `spy_search_ranking_setting` | Settings page (Scope Copy: **Copy configuration**) |
| 5 specificity knobs | Store+locale | `spy_search_ranking_setting` | Settings page (Scope Copy: **Copy configuration**) |
| Published ranking-configuration KV document | Store+locale | key-value storage, one document per store×locale | derived, not directly edited |

This is also why [Scope Copy](README.md#what-it-does) is split into two separate actions rather than one:
**Copy configuration** operates at the store+locale tier (weight + settings), **Sync store configuration**
operates at the store-only tier (formula/isActive/shape) — one picker pair per tier, not a shared one, so
neither action silently drags a scope dimension it doesn't actually use.
