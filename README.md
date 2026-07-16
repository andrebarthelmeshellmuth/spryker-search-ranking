# Spryker Search Ranking

Data-driven search ranking for Spryker Commerce OS: rank search results by **business signals**
(PDP impressions, sales, or anything else you can measure) instead of relying on string matching
alone. Based on Spryker's
[data-driven ranking best practice](https://docs.spryker.com/docs/pbc/all/search/latest/base-shop/best-practices/data-driven-ranking).

Designed as the companion package to
[spryker-community/search-debug](https://github.com/andrebarthelmeshellmuth/spryker-search-debugger) —
the eventual `function_score` ranking is meant to stay fully inspectable in the search-debug overlay.

## Status

🚧 Early development — **phases 1–2 of 6** are functional: the metric/value data model, the Zed
management UI, CSV data import, the normalization cron, and the export of normalized signals into
the Elasticsearch page documents (`scores` field). The signals **do not yet influence ranking** —
metric weights are stored but unused until the `function_score` phase lands. See [Roadmap](#roadmap).

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
| `SearchRanking` | Propel schema, facade (CRUD, formula validation, normalization, ES export/publish), expression evaluator, ProductPageSearch export plugins, `search-ranking:normalize` console command |
| `SearchRankingGui` | Zed UI controllers, tables, forms, navigation entry |
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

### 9. Build

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

- Metric **weights are stored but not consumed** yet — they become meaningful with the
  `function_score` query (phase 3) and the whf tuning UI (phase 5).
- Re-publishing happens **only via the normalize cron** (or manually) — importing raw values alone
  does not refresh the search documents until the next run.
- Values are per abstract product and global — no per-store or per-locale signals.

## Roadmap

- [x] **Phase 1** — metric definitions, product values, Zed UI, data import, normalization cron
- [x] **Phase 2** — export normalized signals into the Elasticsearch page index
- [ ] **Phase 3** — `function_score` query wrapping the catalog search with weighted business signals
- [ ] **Phase 4** — score breakdown integration with spryker-community/search-debug
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
isolation), the page-data loader (per-payload score mapping) and the publish trigger (event
chunking) as pure unit tests — no database needed.

## License

MIT — see [LICENSE](LICENSE).
