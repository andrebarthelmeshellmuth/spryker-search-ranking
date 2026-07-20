# Spryker Search Ranking

Data-driven search ranking for Spryker Commerce OS: rank search results by **business signals**
(PDP impressions, sales, or anything else you can measure) instead of relying on string matching
alone. Based on Spryker's
[data-driven ranking best practice](https://docs.spryker.com/docs/pbc/all/search/latest/base-shop/best-practices/data-driven-ranking).

Designed as the companion package to
[spryker-community/search-debug](https://github.com/andrebarthelmeshellmuth/spryker-search-debugger) —
the eventual `function_score` ranking is meant to stay fully inspectable in the search-debug overlay.

## Status

🚧 Early development — **phases 1–3 of 6** are functional: the metric/value data model, the Zed
management UI, CSV data import, the normalization cron, the export of normalized signals into the
Elasticsearch page documents (`scores` field), and the **`function_score` ranking itself**: catalog
searches are re-scored by `relevanceWeight × normalizedRelevance + (1 - relevanceWeight) × Σ weightᵢ × signalᵢ`,
with the metric weights and the two blend constants editable in Zed and synchronized to key-value
storage — see [Ranking formula](#ranking-formula) for the full rationale. See [Roadmap](#roadmap).

## What it does today

- **Ranking metrics** (`spy_search_ranking_metric`): named business signals (e.g. `pdp_impressions`,
  `top_seller`, `random`) with a **weight** for their future contribution to the combined score, an
  **active** flag, and a **normalization formula** stored as an expression string.
- **Product values** (`spy_search_ranking_product_metric`): one row per (metric, abstract product)
  pair holding the **raw real-world value** (e.g. "8,250 impressions") and the **normalized value
  in ]0;1]** derived from it. Unique per pair, removed by cascade with either parent.
- **Zed UI**:
  - `/search-ranking-gui` — metric list with create/edit/delete. Formulas are validated on save by
    trial evaluation; the exact parser error is shown on the form. Metric names are checked for
    uniqueness. Deletion is a CSRF-protected POST.
  - `/search-ranking-gui/product-metric` — read-only, searchable table of all product values
    (abstract SKU, metric, raw value, normalized value, last update).
- **Data import**: importer types `search-ranking-metric` (upsert by name) and
  `search-ranking-product-metric` (resolves metric name + abstract SKU to foreign keys, writes raw
  values only — normalized values are never imported).
- **Normalization cron**: `vendor/bin/console search-ranking:normalize` recalculates every
  normalized value of every active metric in batches. A metric whose formula fails to evaluate is
  skipped and reported (non-zero exit code) without aborting the run for the other metrics.
- **Elasticsearch export**: the package ships a `page.json` fragment defining a dynamic `scores`
  object field (per Spryker's data-driven-ranking best practice) and a ProductPageSearch plugin trio
  — bulk data loader, data expander, map expander — that writes each product's normalized values
  into its page document as `scores: {metricName: value}`. Products without values get no `scores`
  field. After normalizing, the cron triggers `Product.product_abstract.publish` events (chunked)
  for all scored products so the documents refresh; `--skip-publish` suppresses that.
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
  (`relevanceWeight`, `relevanceSaturationPoint`) live in one dictionary document
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
  `SprykerCommunity\Shared\SearchDebug\SearchDebugConfig::SCORE_DECIMAL_PLACES` (default **2**) — the
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

**Why not just multiply, e.g. `(1 + sqrt(_score)) × (signals + baseline)`?** An earlier version of
this package did exactly that, with an additive `signalBaseline` constant keeping products without
business signals from being multiplied towards zero. The problem: Elasticsearch's raw `_score` is
unbounded and query-shape-dependent — a query matching more terms, or rarer terms, produces a much
higher score than one matching a single common term, with no ceiling — while business signals are
normalized to `[0;1]` by design. Combining an unbounded, query-dependent number with a bounded one
directly means the *relative* influence of business signals over text relevance drifts unpredictably
from query to query, and the additive baseline has no principled value — it's tuned by eye until
results look right.

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
The demo `random` metric is therefore not a special case anywhere in the code: its formula is
literally `random()`.

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
| `SearchRanking` (Zed) | Propel schema, facade (CRUD, settings, formula validation, normalization, ES export/publish), expression evaluator, ProductPageSearch export plugins, `search-ranking:normalize` console command |
| `SearchRanking` (Client) | `SearchRankingFunctionScoreQueryExpanderPlugin` + painless script builder |
| `SearchRankingGui` | Zed UI controllers, tables, forms (metrics + settings), navigation entry |
| `SearchRankingStorage` (Zed) | Ranking-configuration storage table with synchronization behavior, publish writer, sync-data plugin |
| `SearchRankingStorage` (Client) | Reads the configuration document from key-value storage |
| `SearchRankingDataImport` | The two data importers; example CSVs in `data/import/` |

## Requirements

- Spryker B2B/B2C/Marketplace shop
- PHP >= 8.2
- `symfony/expression-language` ^6 or ^7 (usually already present transitively)

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
use SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingNormalizeConsole;
use SprykerCommunity\Zed\SearchRankingDataImport\SearchRankingDataImportConfig;

new SearchRankingNormalizeConsole(),
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

### 6. Schedule the normalization cron

E.g. hourly, in `Pyz\Zed\SymfonyScheduler\SymfonySchedulerConfig::getCronJobs()`:

```php
'search-ranking-normalize' => [
    'command' => '$PHP_BIN vendor/bin/console search-ranking:normalize',
    'schedule' => '0 * * * *',
],
```

Besides normalizing, each run triggers publish events so the search documents pick up the fresh
scores (suppress with `--skip-publish`).

### 7. Register the Elasticsearch export plugins

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

### 8. Register the package's search schema directory

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

### 9. Register the ranking-configuration sync queue

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

### 10. Register the function_score query expander

In `Pyz\Client\Catalog\CatalogDependencyProvider::createCatalogSearchQueryExpanderPlugins()`,
**after `FacetQueryExpanderPlugin`** — earlier expanders require the root query to still be a
`BoolQuery`:

```php
use SprykerCommunity\Client\SearchRanking\Plugin\Catalog\SearchRankingFunctionScoreQueryExpanderPlugin;

new FacetQueryExpanderPlugin(),
new SearchRankingFunctionScoreQueryExpanderPlugin(),
```

### 11. Optional: register the search-debug overlay section

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

### 12. Build

```bash
vendor/bin/console transfer:generate
vendor/bin/console propel:install
vendor/bin/console navigation:build-cache
vendor/bin/console search:setup:source-map   # regenerates PageIndexMap incl. the scores field
vendor/bin/console search:setup:sources      # merges the scores field into the live index mapping
```

The "Search Ranking" section then appears in the Back Office navigation, and after the next
normalize run + queue processing the page documents carry `scores`.

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
- [ ] **Phase 5** — live weight-tuning sliders on the SRP for privileged admins ("weltherrschaftformula")
- [ ] **Phase 6** — learning-rate based weight adoption with audit log and rollback

## Testing

From a shop that has the package installed:

```bash
vendor/bin/codecept build -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRanking
vendor/bin/codecept run -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRanking
```

Covers the formula evaluator (functions, `random()` range, division-by-zero/unknown-function
failures, validation messages), the normalizer (clamping, batch paging, per-metric error
isolation), the page-data loader (per-payload score mapping), the publish trigger (event chunking)
and the function_score builder (script shape, zero-weight skipping, script-injection guarding,
null on empty configuration) as pure unit tests — no database needed. The Client suite lives at
`tests/SprykerCommunityTest/Client/SearchRanking`.

## License

MIT — see [LICENSE](LICENSE).
