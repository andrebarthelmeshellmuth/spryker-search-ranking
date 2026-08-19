# Design decisions

Why the publish and normalization pipelines work the way they do, rather than the more obvious alternatives.

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

## Why hourly batch normalization, not an immediate per-value hook

Raw values only ever get normalized and published once an hour, via `search-ranking:normalize` — never
the instant a raw value is written. This is a deliberate choice, not an oversight:

- **`normalizedValue` is a function of the metric's aggregate stats** (`min`/`max`/`avg`/`count`, from
  `getMetricStatistics()`), which by definition need every row for that metric, not just the one that
  changed. A single raw value cannot be correctly normalized in isolation — at best it could be evaluated
  against the *last known* (already slightly stale) aggregate stats, not freshly correct ones.
- **There is no single-value write path to hook onto.** The only writer of raw values is CSV import
  (`SearchRankingMetricWriterStep`/`SearchRankingProductMetricWriterStep` — see
  [Data import](../README.md#what-it-does)), which is inherently bulk: one import run can touch thousands of
  rows. Firing an immediate normalize-and-publish per row would mean thousands of individual publish
  events instead of the current few chunked ones — plausibly worse than the hourly batch — and every one
  of those rows would be published *again* an hour later once real stats catch up. The "Product Values"
  Zed page is read-only specifically because there is no single-row edit action this would attach to.
- **The underlying signals are ETL-style, refreshed once a day upstream by design** — Spryker's own
  data-driven-ranking best practice this package is based on assumes these get recomputed nightly from a
  data warehouse. If raw values only change once a day via import, "picked up within the hour" is already
  same-day freshness; shaving that hour down further has little practical payoff without a genuinely
  real-time upstream data source.

**A real hybrid is buildable if this ever changes**: on a raw-value write, evaluate that one row against
the metric's *currently cached* stats and publish just that one product abstract, while the hourly cron
keeps doing the full stats refresh + full re-normalization + reconciliation publish for everything the fast
path only approximated. Worth building the moment there is an actual single-value write path (e.g. a
future Zed editor for individual product-metric values) to hang it on — not before, since bulk CSV import
is a poor fit for a per-row hook regardless of how cheap the hook itself is.
