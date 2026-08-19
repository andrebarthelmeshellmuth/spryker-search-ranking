# Import file formats

CSV shapes accepted by the data importers.

`search_ranking_metric.csv` — `store`/`locale` scope which store×locale this row's `weight` applies to;
`name`/`formula`/`is_active` are global identity fields shared across every scope, so a metric imported
for two stores appears as two rows with the same `name` but different `weight`/`store`/`locale`:

```csv
name,weight,formula,is_active,store,locale
pdp_impressions,0.3,atan(x / avg) / (pi() / 2),1,DE,de_DE
top_seller,0.5,x / max,1,DE,de_DE
random,0.2,random(),1,DE,de_DE
```

`search_ranking_product_metric.csv` (raw values only — normalized values are computed by the cron;
`store`/`locale` scope the raw value itself, same convention as above):

```csv
abstract_sku,metric_name,raw_value,store,locale
001,pdp_impressions,8250,DE,de_DE
001,top_seller,132,DE,de_DE
001,random,0,DE,de_DE
```

> **Breaking change (since the store/locale scoping migration):** both CSVs now require `store`/`locale`
> columns. A pre-migration CSV without them fails at import time — `is_active`/`weight` were always
> required columns too, so a missing `store`/`locale` column surfaces the same way any other missing
> required column always has.

`locale` in either CSV also accepts a comma-separated list (e.g. `de_DE,en_US`) — the same convention
Spryker core itself uses for multi-value import cells (e.g. `ProductAbstractSkusToIdsConditionResolver`'s
`explode(',', $conditionValue)`). For a metric that doesn't genuinely vary by locale — a store-wide fact
like sales or stock, the kind of metric `isLocaleScoped=false` fits (see [Terminology](terminology.md)) —
list every locale it applies to in one row instead of duplicating the whole row per locale; the importer
writes the identical `weight`/`raw_value` into each listed locale:

```csv
name,weight,formula,is_active,store,locale
top_seller,0.5,x / max,1,DE,"de_DE,en_US"
```

Quoting the cell (`"de_DE,en_US"`) is required, not optional — the reader is a plain RFC 4180 CSV parser
(`SplFileObject::READ_CSV`, comma delimiter, `"` enclosure — see Spryker core's
`CsvReaderConfiguration`), so an unquoted comma inside a cell is indistinguishable from a real column
boundary and will silently shift every column after it.

Example files ship in this package under `data/import/`, formatted correctly but **populated with this
package's own development shop's real catalog SKUs and metric values** — they exist to prove the import
mechanics work end-to-end against a real catalog, not as generic/portable seed data. Copy the format, not
the rows: replace every `abstract_sku` with your own shop's own abstract SKUs (and real
`pdp_impressions`/`top_seller` values, or your own metric names entirely — `random` is the only metric this
package assumes nothing about) before importing into a different Spryker installation. Importing them
as-is elsewhere will not error, but will silently do nothing useful — either no rows match your catalog's
SKUs at all, or coincidentally-matching SKUs get some other shop's numbers.
