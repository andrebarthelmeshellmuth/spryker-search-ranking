# Terminology

The vocabulary this package uses, and how each term maps to the code.

A quick reference for terms this README reuses across many sections. Each is explained in full where
it's first introduced in context — this is a lookup index, not a replacement for those explanations.

For the full store/locale scoping picture across every field this package manages — walked through
formula term by term, plus a quick-reference table — see [SCOPING.md](../SCOPING.md).

### metric

A named business signal (e.g. `pdp_impressions`, `top_seller`) with its own weight, normalization
formula, active flag, and direction. See [What it does](../README.md#what-it-does).

A metric's fields have two different scopes, not one: `name`/`isHigherBetter` (direction) are global —
the same everywhere the metric exists at all, since they're definitional (what the metric IS/MEANS).
`formula`/`isActive`/curve `shape`/`weight` all live at (store, locale) tier
(`spy_search_ranking_metric_store_config` and `spy_search_ranking_metric_weight`, one row per metric ×
store × locale unconditionally) — but whether sibling locale rows of the same store are kept identical or
allowed to genuinely diverge is a single per-metric decision, the global `isLocaleScoped` flag (default
`false`), not something baked into the storage shape itself. A brand-new store gets its own
formula/active/shape via the Scope Copy page's combined copy action (see [What it does](../README.md#what-it-does)),
not a database migration — the columns that once held a single global formula/isActive/shape on
`spy_search_ranking_metric` itself were removed entirely once every part of this package migrated onto
the (store, locale)-scoped table (a breaking change; see [CHANGELOG](../CHANGELOG.md) for the release that
shipped it).

`isLocaleScoped` (default `false`) answers one question once, and the answer cascades through formula,
isActive, shape, AND weight together — never one flag per concern:
- **`false`** (default, most metrics): this metric is a store-wide fact (e.g. `top_seller`, driven by
  sales/stock data that doesn't vary by language) rather than a language-dependent one — saving
  formula/isActive/weight for any one locale of a store fans it out to every real locale of that store
  automatically, so the admin only ever edits one value per store instead of keeping N locale copies in
  sync by hand.
- **`true`** (rare — evidence first via `evaluateCurrentMetricFitAcrossLocales()`, see
  [Metric history](../README.md#what-it-does) below): this metric genuinely differs per locale (the classic case: a
  signal whose raw-value distribution differs by language, needing e.g. `2 * atan(x)` in one locale and
  `3 * atan(x)` in another to normalize comparably) — formula, isActive, shape, and weight are then
  authored and stored independently per locale, no fan-out.

The metric list's "Scope" column shows which mode each metric is in. See
[SCOPING.md](../SCOPING.md) for the full field-by-field breakdown.

### weight

How much one metric's signal contributes to the combined business-signal score, relative to the other
active metrics. See [Ranking formula](ranking-formula.md).

For an `isLocaleScoped=false` metric (most metrics — see [metric](#metric) above), weight is still the
mechanism for suppressing one metric in just ONE locale of a store without touching `isActive` (which
stays store-wide, fanned out with everything else): set that locale's weight to `0`. The query-time blend
is literally `weight * scores.metric`, so a `0` weight contributes nothing to that locale's ranking. (For
an `isLocaleScoped=true` metric, `isActive` is itself per-locale, so this trick isn't needed — just
uncheck Active for that one locale directly.) A `0` weight for a locale whose underlying data you distrust
(e.g. missing/broken tracking for that market) and a `0` weight that's simply never been configured look
identical today; there is no flag distinguishing "deliberately suppressed" from "unconfigured".

### raw value / normalized value

The real-world number for one metric on one product (e.g. "8,250 impressions"), and the `]0;1]` value
its formula maps that number to. See [What it does](../README.md#what-it-does).

### signal

A metric's own normalized value — used interchangeably with "metric" once normalization, not the raw
real-world number behind it, is the topic.

### digest

A metric's precomputed distribution snapshot — min/max/mean/median plus a 101-point percentile/empirical-CDF
backbone — rebuilt by the normalization cron and read by the normalization-authoring GUI's live preview
and curve-fit suggestions, so neither ever touches the raw per-product rows directly. See
[What it does](../README.md#what-it-does).

### relevanceWeight

Shorthand `α`. The single knob for how much of the final score comes from normalized text relevance vs.
the combined business-signal score. See [Ranking formula](ranking-formula.md).

### relevanceSaturationPoint

Shorthand `k`. The raw Elasticsearch `_score` at which normalized relevance reaches exactly 0.5 — a
search-infra tuning constant, not a business knob. See [Ranking formula](ranking-formula.md).
