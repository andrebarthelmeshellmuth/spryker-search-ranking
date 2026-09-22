# Changelog

All notable changes to this package are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Each version below also has a [GitHub release](../../releases) with the fuller write-up.

## [Unreleased]

### Added
- `checkGlueApiWiring()` in `search-ranking:check-installation` — warns (never fails) when the
  additive `randomImpact` schema merge or the shared project-level `CatalogSearchStorefrontProvider`
  override is missing.

### Documented
- `extra.dependency-pins` note on why `symfony/security-guard: 5.4.0-BETA1` must stay in `require`
  (a resolution pin, no `src/` usage).
- OpenSearch 3.5 / Lucene 10.3 compatibility; new `docs/opensearch-3.x-migration.md`.

## [2.4.0] - 2026-08-27

### Added
- Glue API (API Platform): additive `randomImpact` property on core's `catalog-search` resource
  (`GET /catalog-search`). Schema-only merge — surfacing the value needs a project-level Provider
  override, shared with `search-debug`'s `searchDebug` property.

### Fixed
- Corrected `composer.json` dependency declarations after a requires-vs-usage audit; `spryker/transfer`
  moved to `require-dev`.
- Pinned `symfony/security-guard` to the one pre-release that supports Symfony 6.4/7 `security-core`,
  unblocking `spryker/api-platform` resolution.
- Applied Rector `IfToNullCoalescingAssignRector` (unpinned dev-tooling drift).

_PR #43 (hybrid semantic search + intent-aware alpha) intentionally left open — not part of this
release._

## [2.3.5] - 2026-08-23

### Changed
- CI: bumped `actions/checkout` v4 → v7.

## [2.3.4] - 2026-08-22

### Fixed
- Closed the scope-copy lock target-exclusivity race with a DB constraint.

### Changed
- Deduped `ScopeCopyLockController` / `ScopeCopyRunController` shared boilerplate; added coverage for
  `SearchRankingScopeCopySyncConsole`, all 3 facades, and `SearchRankingClient`'s remaining
  factory-delegation methods.

## [2.3.3] - 2026-08-20

### Changed
- Restored README screenshots against the fictional "Feldwerk" demo catalog.
- CI: added a `fixtures-sync` job diffing the bundled demo-catalog CSVs against search-feedback's
  canonical copy.

### Fixed
- A Rector finding.

## [2.3.2] - 2026-08-19

### Changed
- README states plainly that `spryker-community/*` is an independent, community-built namespace with
  no official Spryker affiliation; split into `README.md` + `docs/*.md`, fixing 3 broken internal
  anchors.
- CI: added an `xmllint` job for ruleset XML; pinned dev tooling to stop CI drift.

## [2.3.1] - 2026-08-18

### Changed
- Fixed install instructions for a real external adopter; removed product-screenshot images per the
  Demo Shop license; stopped committing Spryker's generated `PageIndexMap.php`; authenticated CI's
  composer downloads against `codeload.github.com` rate limiting.

## [2.3.0] - 2026-08-14

### Added
- Optional `search-feedback` frozen-replay integration: `SearchFeedbackTermVectorSnapshotProviderPlugin`
  captures this client's last specificity-weighting result into a ticket's frozen snapshot;
  `SearchFeedbackTermVectorSnapshotRestorerPlugin` restores it on replay so the debug overlay's
  "Relevance weight (α)" reflects what actually scored the ticket. Fully optional.

## [2.2.2] - 2026-08-13

### Changed
- CI: `phpstan` level 8 gated via a standalone `composer phpstan-ci` variant; now a required check.

### Fixed
- Declared `spryker/translator` in `require` (previously only satisfied transitively).

## [2.2.1] - 2026-08-13

### Changed
- CI: the Codeception "Portable" subset now runs standalone via a `tests/_ci-standalone` bootstrap.

## [2.2.0] - 2026-08-12

### Added
- Back-office ACL reachability check in `search-ranking:check-installation` — warns (never fails) when
  restricted Zed roles exist but none has an ACL rule for `search-ranking-gui`.
- `spryker/acl ^3.27.0` added to `require` (read-only, used only by that diagnostic).

### Fixed
- A `ReturnEarlyIfVariableRector` finding in `ScoreSectionBuilder` (CI rector drift).

## [2.1.0] - 2026-08-12

### Added
- `SearchRankingFacadeInterface::getConfiguration(string $storeName, string $localeName)` — the whole
  live ranking configuration for one scope in a single transfer-shaped read. `Business/Configuration/
  ConfigurationReader` is now the single Zed-side assembler. Backward compatible; weights returned raw,
  active metrics only.

## [2.0.1] - 2026-08-12

### Fixed
- 35 `|trans` strings the Zed GUI renders were missing from `data/translation/Zed/` — rendered
  untranslated in a non-English Zed.

### Added
- `search-ranking:check-installation` now scans the package's own `|trans` keys against the shipped
  catalog (one-directional).

## [2.0.0] - 2026-08-12

### Changed
- **Breaking:** ranking configuration is now keyed by **store *and* locale** everywhere it was keyed
  by store alone. Every affected `SearchRankingFacadeInterface` / `SearchRankingRepositoryInterface` /
  `SearchRankingEntityManagerInterface` method gained a required `$localeName` — no deprecation shim.
  The Scope Copy surface was reshaped to full-scope equivalents. `isLocaleScoped` is now a single root
  flag per metric.

### Added
- Yves installation-check page at `/search-ranking-widget/check-installation` (behind
  `IS_CHECK_INSTALLATION_PAGE_ENABLED`, default off).
- `search-ranking:check-installation` gained cron and navigation checks.
- `spryker/router` declared in `require`; `spryker/symfony-scheduler` added as `suggest`.

## [1.3.0] - 2026-08-07

### Changed
- Replaced entropy-based query weighting with **query-specificity weighting** (blended max/harmonic-mean
  IDF against a saturation curve, tunable per store/locale).
- Ranking configuration (metric weights, settings, formulas) is now scoped **per store/locale**
  throughout. Republishing is centralised behind a single event.

### Added
- Scope Copy admin page (bootstrap a new market's config from an existing one, optional lockable daily
  sync); per-metric `isLocaleScoped` flag; filterable Metric History page; per-query specificity-math
  breakdown in the search-debug overlay.

## [1.2.0] - 2026-07-30

### Added
- Metric shape derivation (stable curve-family slug) + read-only drift-detection primitives
  (`findLastMetricChangeHistoryEntry()`, `evaluateCurrentMetricFit()`, `recordCheckOnly()`).
- Entropy weighting is now shown in the search-debug overlay.

### Fixed
- Entropy weighting silently no-op'd on dynamic-store-mode shops (`Store::getInstance()` threw, was
  swallowed by the fail-safe) — fixed via a proper `StoreClient` bridge.

## [1.1.3] - 2026-07-28

### Changed
- Default `relevanceWeight` `0.5` → `0.75`, `entropyWeightShiftMagnitude` `0.5` → `0.25` — a reasoned
  behaviour change (text relevance stays the primary signal; business signals refine and tiebreak).

## [1.1.2] - 2026-07-28

### Documented
- The shipped `search_ranking_product_metric.csv` example carries this dev shop's real SKUs, not
  portable placeholder data — README now says to copy the format, not the rows.

## [1.1.1] - 2026-07-28

### Added
- Entropy-aware relevance weighting (opt-in, off by default); Product Value Gaps page.
- Hygiene to match search-debug's bar: CODEOWNERS, Rector, PHPStan level 8; new `SearchRankingDataImport`
  test suite.

### Fixed
- A metric with an invalid name could be marked active without contributing to live ranking.
- A stale KV-storage fallback used wrong defaults for `relevanceWeight` / `relevanceSaturationPoint`.

## [1.0.0] - 2026-07-26

### Added
- Initial release: business-signal search ranking — re-scores catalog search results by blending
  normalized relevance with weighted business signals
  (`relevanceWeight × normalizedRelevance + (1 - relevanceWeight) × Σ weightᵢ × signalᵢ`), configurable
  end-to-end from Zed: metric/value data model, CSV import, an hourly normalization cron, and a
  data-driven normalization-authoring GUI with a live curve-fit preview. `function_score`/`script_score`
  formula cross-validated across OpenSearch 1.3.4/2.11 and Elasticsearch 8.11.

[Unreleased]: ../../compare/v2.4.0...HEAD
[2.4.0]: ../../releases/tag/v2.4.0
[2.3.5]: ../../releases/tag/v2.3.5
[2.3.4]: ../../releases/tag/v2.3.4
[2.3.3]: ../../releases/tag/v2.3.3
[2.3.2]: ../../releases/tag/v2.3.2
[2.3.1]: ../../releases/tag/v2.3.1
[2.3.0]: ../../releases/tag/v2.3.0
[2.2.2]: ../../releases/tag/v2.2.2
[2.2.1]: ../../releases/tag/v2.2.1
[2.2.0]: ../../releases/tag/v2.2.0
[2.1.0]: ../../releases/tag/v2.1.0
[2.0.1]: ../../releases/tag/v2.0.1
[2.0.0]: ../../releases/tag/v2.0.0
[1.3.0]: ../../releases/tag/v1.3.0
[1.2.0]: ../../releases/tag/v1.2.0
[1.1.3]: ../../releases/tag/v1.1.3
[1.1.2]: ../../releases/tag/v1.1.2
[1.1.1]: ../../releases/tag/v1.1.1
[1.0.0]: ../../releases/tag/v1.0.0
