# Migrating to OpenSearch 3.x

This package was originally built and verified against OpenSearch 1.3.4 (a fork of Elasticsearch 7.10,
Lucene 8.10). It has since been run end-to-end on a Spryker demoshop upgraded to **OpenSearch 3.5.0**
(Lucene 10.3.2) — full re-export and reindex, `search-ranking:check-compatibility` re-probed, live kNN and
lexical queries confirmed.

**The ranking formula needs no code change.** The `function_score` / `script_score` painless shape
`FunctionScoreBuilder` generates is byte-identical across 1.3.4, 2.11, 3.5 and Elasticsearch 8.11 — see the
[engine-compatibility table](../README.md#search-engine-compatibility). Everything below is
migration-time environment work, most of it only relevant if you run the optional semantic-blend feature
(the `knn_vector` `embedding` field).

## What `check-compatibility` reports differently on 3.5

| capability | 1.3.4 | 3.5 |
|---|---|---|
| `function_score` + `script_score` (painless) | ✅ | ✅ |
| `rank_feature` query | ✅ | ✅ |
| `distance_feature` query | ✅ | ✅ |
| `_rank_eval` endpoint | ✅ | ✅ |
| completion suggester | ✅ | ✅ |
| `pinned` query | ❌ | ❌ (Elastic-licensed, never in OpenSearch) |
| **`hybrid` query** | ❌ | ✅ — the neural-search plugin's fusion query type; needs OpenSearch ≥ 2.10 |
| **`_search/pipeline` endpoint** | ❌ | ✅ — search pipelines; needs OpenSearch ≥ 2.8 |
| `_plugins/_ml` (ML Commons) | ✅ | ✅ — the endpoint is present on both (`opensearch-ml` is in the stock 1.3.x image); 3.x adds in-cluster model serving / local inference on top |
| `neural` query | ❌ | ❌ — the parser rejects a bare clause on both; it needs a registered `model_id` to validate, so this is a probe limitation on 3.x, not a missing capability |
| `_plugins/_ltr` (Learning To Rank) | ❌ | ❌ (third-party plugin, in neither stock image) |

The two genuine additions are `hybrid` query and `_search/pipeline`. The probe never compares version
strings — it asks the live engine "would you accept this?" — so it stays correct across the upgrade with no
change; the rows above were re-probed against both a live OpenSearch 1.3.14 and a live 3.5.0.

## Environment changes for the `knn_vector` semantic-blend feature

### 1. k-NN engine: `nmslib` is gone

OpenSearch removed the `nmslib` k-NN engine in 3.0. The `page.json` fragment this package ships already
uses `engine: lucene` (in-cluster, no native library, fine for catalogue-sized vector counts). If you
copied an older fragment that names `nmslib`, change it:

```json
"embedding": {
    "type": "knn_vector",
    "dimension": 768,
    "space_type": "cosinesimil",
    "method": { "name": "hnsw", "engine": "lucene" }
}
```

`space_type` moved to be a sibling of `method` in current OpenSearch; don't also nest it inside `method`.

### 2. `index.knn` is a static setting — add it to the open-index skip list

`index.knn` can only be set at index-creation time. Spryker's `IndexSettingsUpdater` re-sends the full
settings block on every `search:setup`, so the **second and every later** `search:setup` /
`check-installation` / deploy against an existing k-NN index fails with:

```
Can't update non dynamic settings [[index.knn]] for open indices [[..._page/...]]
```

Fix it once in your project config:

```php
// src/Pyz/Zed/SearchElasticsearch/SearchElasticsearchConfig.php
public function getStaticIndexSettings(): array
{
    return array_merge(parent::getStaticIndexSettings(), ['index.knn']);
}
```

Creation still applies `index.knn` (it comes from the schema fragment, not the settings-update path).

### 3. Raise `http.max_content_length` if it is capped low

A page document with a 768-float `embedding` is ~15 KB larger than one without. Bulk
`sync.search.product` batches scale accordingly and can exceed a low HTTP body cap, failing with
`Request entity is too large` (HTTP 413) into `sync.search.product.error`. OpenSearch's own default is
`100mb`; some deployment templates lower it (the Spryker `docker-sdk` OpenSearch template sets `10mb`).
Restore it to `100mb` in your engine config:

```yaml
# opensearch.yml
http.max_content_length: 100mb
```

## The neural-search transformer and empty `"properties": {}`

Independent of this package, but you will hit it during the same upgrade. OpenSearch 3.x bundles the
neural-search plugin, whose `SemanticMappingTransformer` runs on **every index create**. It walks the
mapping and treats each `properties` block as a JSON object. Any schema — yours or a third party's — that
declares:

```json
"some-field": { "type": "object", "properties": {} }
```

fails index creation with `class java.util.ArrayList cannot be cast to class java.util.Map`, because
PHP's `json_decode($json, true)` turns the empty `{}` into `[]` and Spryker then PUTs `"properties": []`.
Spryker Cloud Commerce fixed this in five core packages (ticket SC-25160) by removing the empty block;
other packages (e.g. `spryker-feature/self-service-portal`'s `ssp_asset.json`) may still carry it.

Spryker merges schema fragments with `array_replace_recursive`, which cannot delete a key, so a
project-level override has to make `properties` **non-empty** rather than remove it — e.g. add one inert,
never-populated field:

```json
{
    "mappings": {
        "<index_source>": {
            "properties": {
                "<the-offending-field>": {
                    "type": "object",
                    "properties": { "_os3_object_guard": { "type": "boolean", "index": false } }
                }
            }
        }
    }
}
```

## Not yet done

- **`neural` query probe** — currently reports "not supported" because it validates a bare clause with no
  `model_id`. Worth refining to probe with a throwaway/registered model once a `NeuralRerankStrategy` is
  actually on the roadmap.
- **OS 3.x native `hybrid` query / search pipelines** — now available, but the semantic-blend feature
  still fuses in PHP (it was built under the 1.3.4 constraint). Revisiting that is
  [`RankingStrategyInterface`](../README.md) / strategy-seam work, not a migration step.
