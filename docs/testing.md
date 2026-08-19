# Testing and CI

How this package is tested, which suites need a host shop, and what CI runs.

## Testing and CI

### Automated checks

`.github/workflows/ci.yml` runs on every push and pull request:

| check | what it protects |
|---|---|
| `composer validate` | the manifest stays well-formed |
| `phpcs` (PHP 8.3, 8.4) | coding standard, via this package's own `phpcs.xml` |
| `composer check-floors` (PHP 8.3, 8.4) | the declared dependency floors are real |
| `rector` dry-run (PHP 8.3, 8.4) | no unapplied Rector rule set drifts in |
| `phpmd` (`phpmd.xml` + `phpmd-public-methods.xml`) | cyclomatic/NPath complexity, method/class length stay reasonable — run as two separate invocations because PHPMD merges every loaded ruleset's `exclude-pattern` into one global file list per run, and only the public-method-count rule should skip Facades/Factories (Spryker's own DI convention gives each one method per capability/collaborator, not a design problem this package can fix) |
| `phpstan` (PHP 8.3, 8.4) | static analysis, level 8, standalone CI variant — see "Static analysis" below |
| `portable tests` (PHP 8.3, 8.4) | this package's own `@group Portable` test subset actually passes — see "Test suite" below |

`check-floors` is the one worth understanding. This package's `require` constraints are a promise about
which Spryker versions an adopter may install — and that promise is exactly what a development shop
cannot verify, because a full demo shop has every Spryker module present regardless of what this package
declares. A missing declaration only surfaces on a leaner shop, as a fatal, after installation.

So the check resolves every constraint to its **oldest** allowed version (`composer update
--prefer-lowest --prefer-stable --no-dev`) and then asserts that every vendor symbol used in `src/`
actually exists in that tree. It exits non-zero if not. Run it locally the same way:

```bash
composer check-floors
```

It reports three categories: resolved, host-generated (`Generated\*` transfer classes from
`transfer:generate`, and `Orm\*` Propel classes from `propel:install` — both build artifacts of the host
project, correctly absent from any vendor tree), and optional-absent (symbols from the `suggest`ed
`spryker-community/search-debug`, whose every use — `ScoreSectionBuilder`,
`SearchRankingProductDebugDataExpanderPlugin` — is guarded by that package never being autoloaded/wired
unless a project deliberately installs and registers both).

This audit is what caught two real, otherwise-invisible problems: an undeclared dependency on
`spryker/catalog` (needed a floor of `^5.7.0` specifically — versions below that still construct Elastica's
`Match` query class by its pre-PHP-8 name, a hard parse error under PHP 8.3+), and a `php >= 8.2` claim
that was actually false — every `spryker/propel-orm` release resolvable under `minimum-stability: stable`
(the ones depending on a real, non-beta `propel/propel`) requires PHP >= 8.3.

### Test suite

Every test class carries a portability `@group`, so `codecept run -g <tag>` tells you what a given test
actually needs:

| tag | needs | where it runs |
|---|---|---|
| `Portable` | nothing beyond `Generated\Shared\Transfer\*` | standalone — CI runs exactly this, see below |
| `NeedsDatabase` | a real Propel connection | host shop only |
| `NeedsSearch` | a real Elasticsearch/OpenSearch | host shop only |
| `NeedsProject` | this package's own installation diagnostics, deliberately coupled — see their own docblocks | host shop only |

`Portable` tests run standalone in CI on every push, via `tests/codeception.portable.yml` +
`tests/_ci-standalone/` — no host shop, no live database, no search engine. The recipe: a direct
`TransferBusinessFactory` call generates `Generated\Shared\Transfer\*`, and a direct
`spryker/search-elasticsearch` `IndexMapGenerator` call generates `Generated\Shared\Search\PageIndexMap`
(merging that package's own default `page` mapping with this package's own `Schema/page.json` addition) —
both into `src/Generated/` (gitignored, exactly like a real project already gitignores its own —
regenerated every run, never committed). Run it yourself the same way CI does:

```bash
composer install
php tests/_ci-standalone/generate-transfers.php
php tests/_ci-standalone/generate-index-map.php
vendor/bin/codecept run -c tests/codeception.portable.yml -g Portable
```

**299 tests, 1670 assertions** across seven Codeception suites (`Zed/SearchRanking`,
`Zed/SearchRankingStorage`, `Zed/SearchRankingGui`, `Zed/SearchRankingDataImport`, `Client/SearchRanking`,
`Client/SearchRankingStorage`, `Yves/SearchRankingWidget`) make up the full suite, `Portable` and
non-`Portable` alike. The rest — `NeedsDatabase`/`NeedsSearch`/`NeedsProject` — runs **inside a host
shop** that has the package installed:

```bash
vendor/bin/codecept build -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRanking
vendor/bin/codecept run -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRanking
```

Covers the formula evaluator (functions, `random()` range, division-by-zero/unknown-function
failures, validation messages), the normalizer (clamping, batch paging, per-metric error isolation, and
that the random tie-breaker metric is skipped entirely regardless of its active flag), the page-data
loader (per-payload score mapping), the publish trigger (event chunking), the function_score builder
(script shape, zero-weight skipping, script-injection guarding, null on empty configuration), the
distribution-digest builder (percentile-backbone correctness, order-independence), the curve fitter (a
linearly-spread digest recovers a near-perfect linear fit, a saturating digest fits clearly better than
linear, `isHigherBetter=false` swaps in the decay candidate), the formula-preview builder backing the
normalization-authoring GUI's live SVG preview (the "no digest yet" error, the happy path building
CDF/formula points and curve-fit candidates, and formula-evaluation failure returning a fresh error
transfer rather than a half-populated chart), the metric randomizer (no-op when the metric is missing or
inactive, re-normalizes and republishes only when it is active), the shared R² calculator and per-formula
fit evaluator (both feeding the metric-history snapshot), the metric writer's history recording (an
initial row for a brand-new metric, no row when nothing tracked actually changed, the digest snapshot and
fit quality captured correctly when a formula change has an existing digest to compare against, and
`shape` derivation: a saved formula that matches a fresh fit candidate gets that candidate's slug, a
freeform formula or a brand-new metric with no digest yet leaves it null), and the standalone
current-fit evaluator backing the read-only drift-detection primitive (metric missing, no digest yet,
and the happy path delegating to the shared fit evaluator) as pure unit tests — no database needed. The
Client suite lives at
`tests/SprykerCommunityTest/Client/SearchRanking`.

Several tests in that Client suite are real integration tests, not unit tests: `FunctionScoreExecutionTest`
builds a real `function_score` and executes it against real documents in a test-owned index,
`EngineCompatibilityCheckerTest` runs `EngineCompatibilityChecker`'s real `_validate/query` probes against
the actual cluster, and `QueryTermFrequencyFetcherTest` fires real `_termvectors` probes against a
test-owned index (including one deliberately using a mismatched index-time analyzer, to prove
`per_field_analyzer` actually overrides it) — all three need a reachable search engine, though still no
database. `QuerySpecificityCalculator`'s own blend/normalize formula and `SpecificityWeightCalculator`'s
own shift/fallback orchestration are covered separately as plain unit tests (plain arrays/stubbed IO, no
engine needed) in `QuerySpecificityCalculatorTest`/`SpecificityWeightCalculatorTest`. The [random-impact
preview](../README.md#what-it-does)'s own delta math is covered the same way: `RandomImpactCalculatorTest` (pure unit,
the subtract-and-resort formula plus the "no signal of its own defaults to zero"/"unchanged position
renders nothing" edge cases) and `RandomImpactResultFormatterPluginTest` (mocks the factory the same way
`SearchDebugResultFormatterPluginTest` does, covering the permission gate and the "not active" short
circuit) — neither needs a search engine.

`Zed/SearchRankingGui` (`ProductMetricGapFinderTest`) is the mirror case on the database side: real raw
SQL (the `CROSS JOIN` + `LEFT JOIN` + `IS NULL` — see [What it does](../README.md#what-it-does) for why this one query
isn't built through Propel), seeded with real metrics and product abstracts, then torn down — a mocked
connection could confirm the PHP shaped a query string, never that the join actually returns the right
rows, that parameters actually bind correctly, or that the sort-column whitelist actually blocks SQL
injection rather than just looking like it does.

`Zed/SearchRankingDataImport` covers the four data-import steps against a real database: the metric
writer's upsert-by-name (create, update-not-duplicate, and the metric-name pattern rejection that keeps
an unusable name from ever being persisted — see [Data import](../README.md#what-it-does) for why that fails the row
immediately rather than deferring like a bad formula), the two name/SKU-to-ID resolver steps (real
resolution plus the "not found" failure a project sees when it imports product metrics before the metrics
themselves), and the product-metric writer's upsert-by-foreign-keys (create, and that re-importing an
existing pair updates only `rawValue`, never touching an already-normalized `normalizedValue` — normalization
only ever happens downstream, in the normalize cron).

Coverage (Codeception + pcov): the Zed suite covers 90% of classes / 95.97% of lines; the uncovered
remainder is almost entirely Spryker's own Facade/Factory DI-wiring boilerplate (thin delegation, not
meaningfully unit-testable — the same convention `phpmd`'s public-method-count rule already exempts them
from) plus a handful of deep floating-point edge cases in the curve-fitter's grid-search fallback.

For that reason the suites are **not** part of CI: a clean runner has neither a Spryker shop nor a search
cluster, and standing both up per build would cost far more than it returns. CI therefore covers the
static guarantees; the test suite is run against a real shop before a release.

### Browser (Presentation) suite

> **These suites are a development tool for this package's own reference demoshop — not something
> to install or run against YOUR shop.** The Zed suite logs in as `admin@spryker.com`, drives the real Zed
> GUI through a store/locale scope this demoshop seeds (`DE`/`de_DE`), and asserts against an existing
> metric (id `1`) that already has a distribution digest for the normalization-preview check. The Yves
> suite logs in as `search-admin@test-company.example` (the one account this demoshop's fixtures grant
> `SeeSearchRankingRandomImpactPermissionPlugin` to) and `spencor.hopkin@acme.com` (same company, no role,
> for the negative permission-gate tests). Point either at a different shop and most of it will simply fail
> on missing data, not on a real defect. They exist to catch UI regressions while developing this package,
> not as something adopters are expected to run.

**Reproducing the fixture on a fresh clone of this demoshop.** `spencor.hopkin@acme.com`
(`customer_reference` `DE--1`) is already a base-fixture member of the `test-company` company with no
company-role assignment — that's the negative-test account, nothing to add. The permitted account
(`search-admin@test-company.example`) is not a base fixture; add it to
`data/import/common/common/`:

- `customer.csv`: `SearchAdmin--1,en_US,,search-admin@test-company.example,Mr,Search,Admin,,Male,,$2y$12$CUw8PyVm4isuM.ugzQhZ0.os.n1nlGJOA61SEd7cgjXivzt5LqJ2.,2026-08-10`
  (that hash is `change123`, the password the Yves Tester expects)
- `company_user.csv`: `SearchAdmin--1,SearchAdmin--1,test-company,true`
- `company_business_unit_user.csv`: `SearchAdmin--1,test-business-unit-1`
- `company_user_role.csv`: `test-company_Admin,SearchAdmin--1`
- `company_role_permission.csv`: `test-company_Admin,SeeSearchRankingRandomImpactPermissionPlugin,`

Then re-import: `vendor/bin/console data:import customer company-user company-business-unit-user
company-user-role company-role-permission`. The Zed suite's `admin@spryker.com` login and the metric
id `1` distribution digest are both standard seeded state in this demoshop and need no extra fixture.

Two suites, split by layer:

- `tests/SprykerCommunityTest/Zed/SearchRankingGuiPresentation/` (15 tests) — the Zed GUI: the metric list (scoped
  by store/locale, plus a full create → edit → delete round trip through the real forms), the "Normalize
  active weights" action, the Edit form's live normalization preview (smoke-level only — the curve-fit
  math itself is already covered by the unit suite above), the Settings form (including that every
  configured field, `specificityCurveExponent` among them, actually renders), the Scope Copy page (loads
  with its combined picker and both Copy now/Lock actions present), the Product Values table and its Gaps
  view, and the Metric History table. It is kept as its own module directory rather than nested under
  `Zed/SearchRankingGui/` because that module's `Zed` suite scans its whole directory tree recursively — a
  nested WebDriver suite there would break it.
- `tests/SprykerCommunityTest/Yves/SearchRankingWidgetPresentation/` (9 tests) — the [random-impact admin
  preview](../README.md#what-it-does): the permission gate (two negative-test accounts plus an anonymous-shopper
  check, mirroring `spryker-community/search-ranking-optimizer`'s own `PermissionGateCest`), and that the
  checkbox toggles every badge's visibility on and back off client-side with no second request, and that
  every rendered badge carries exactly one of the two sign/color classes (asserted via a live DOM count,
  not just "at least one exists"). Deltas are real, live data — today's randomized signal values, refreshed
  daily by the `search-ranking:randomize` cron — so this suite deliberately never asserts a *specific*
  delta value or count, only "at least one badge is visible", to stay green across that daily reshuffle.

```bash
vendor/bin/codecept build -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRankingGuiPresentation
vendor/bin/codecept run   -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Zed/SearchRankingGuiPresentation
vendor/bin/codecept build -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Yves/SearchRankingWidgetPresentation
vendor/bin/codecept run   -c packages/spryker-community/search-ranking/tests/SprykerCommunityTest/Yves/SearchRankingWidgetPresentation
```

Like the rest of the test suite, neither is part of CI — both need a real running shop plus the Selenium/
chromedriver service already provisioned in this demoshop's `docker-compose.yml`.

### Static analysis

Static analysis (`phpstan`, level 8) runs in two variants:

- **`composer phpstan-ci`** (config [`phpstan.ci.neon`](../phpstan.ci.neon)) — what CI runs on every push,
  standalone. Same transfer/index-map generation recipe as the `Portable` test subset above, and treats one
  category of class as out of scope rather than faking it: Propel's generated `Orm\Zed\*\Persistence\*`
  entity/query/map classes (need a real schema + database, via `propel:model:build`) and the aggregated
  `Generated\{Zed,Yves,Client,Service}\Ide\AutoCompletion` stub.
- **`composer phpstan`** (config [`phpstan.neon`](../phpstan.neon)) — the full check, run from a host shop:
  it needs the generated `Generated\Shared\Transfer\*` classes, which only exist once a project has run
  `transfer:generate`, and it needs the shop's `Ide/AutoCompletion` stub freshly regenerated
  (`console dev:ide-auto-completion:generate`) so the magic `Locator` calls in this package's
  DependencyProviders resolve instead of reporting as undefined methods — so it stays the authoritative
  check for adopters even though CI can't run it.

```bash
vendor/bin/console dev:ide-auto-completion:generate
vendor/bin/phpstan clear-result-cache -c vendor/spryker-community/search-ranking/phpstan.neon
vendor/bin/phpstan analyse -c vendor/spryker-community/search-ranking/phpstan.neon vendor/spryker-community/search-ranking/src
```
