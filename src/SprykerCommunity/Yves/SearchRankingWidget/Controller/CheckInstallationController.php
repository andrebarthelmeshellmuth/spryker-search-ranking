<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchRankingWidget\Controller;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spryker\Yves\Kernel\Controller\AbstractController;
use Spryker\Yves\Kernel\PermissionAwareTrait;
use SprykerCommunity\Shared\SearchRanking\Plugin\SeeSearchRankingRandomImpactPermissionPlugin;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Diagnoses the Yves-side half of the random-impact admin preview — the half
 * {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole}
 * cannot reach, because Zed never bootstraps the Yves DI container and the preview's every moving part
 * (the permission's Client-side registration, the Catalog result formatter, the glossary, the frontend
 * bundle) lives on that side. Complementary to that console command, not a replacement: this page does
 * not re-check the schema, the metric configuration or the synchronization queues.
 *
 * Every check here corresponds to a step that fails SILENTLY. An unregistered result formatter, an
 * unsynchronized ranking configuration, an unimported glossary and an unbuilt frontend all leave a
 * storefront that renders perfectly and simply never shows the checkbox or the badges — there is no
 * error anywhere to notice.
 *
 * Reachable only when BOTH gates pass: the route only exists when
 * {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED}
 * allows it (defaults to `false`), AND the visiting customer holds
 * {@see SeeSearchRankingRandomImpactPermissionPlugin}.
 *
 * @method \SprykerCommunity\Yves\SearchRankingWidget\SearchRankingWidgetFactory getFactory()
 */
class CheckInstallationController extends AbstractController
{
    use PermissionAwareTrait;

    /**
     * Any one of the preview's own glossary keys proves the package's glossary data was imported — they
     * ship in, and are imported from, a single CSV.
     *
     * @var string
     */
    protected const GLOSSARY_KEY_PROBE = 'search_ranking.random_impact.show';

    /**
     * The badge's BEM block, present in the built CSS bundle if and only if the frontend build picked
     * this package's components up.
     *
     * @var string
     */
    protected const FRONTEND_ASSET_PROBE = 'random-impact-badge';

    /**
     * Deliberately matches nothing in particular: this probe only needs the Catalog client to run a real
     * search through the project's own formatter plugin stack, not to find products.
     *
     * @var string
     */
    protected const PROBE_SEARCH_STRING = '';

    /**
     * @return \Spryker\Yves\Kernel\View\View|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        if (!$this->can(SeeSearchRankingRandomImpactPermissionPlugin::KEY)) {
            return $this->renderView(
                '@SearchRankingWidget/views/check-installation/permission-denied.twig',
                [],
                new Response('', Response::HTTP_FORBIDDEN),
            );
        }

        return $this->view(
            [
                'checks' => $this->runChecks(),
            ],
            [],
            '@SearchRankingWidget/views/check-installation/check-installation.twig',
        );
    }

    /**
     * @return array<int, array{label: string, passed: bool, remedy: string|null}>
     */
    protected function runChecks(): array
    {
        $randomImpactResult = $this->probeRandomImpactResult();

        return [
            $this->checkResultFormatter($randomImpactResult),
            $this->checkRandomImpactIsActive($randomImpactResult),
            $this->checkGlossary(),
            $this->checkFrontendAssets(),
        ];
    }

    /**
     * Runs one real catalog search through the project's own formatter plugin stack and returns this
     * package's own slice of the result, or null when the key is absent entirely.
     *
     * Asking the search result rather than introspecting `CatalogDependencyProvider` is deliberate: the
     * formatter plugin list is private to the Catalog client's factory, and a plugin that is registered
     * but throws would still read as "registered" from a registry. This asks the only question that
     * actually matters — does a search on this storefront come back carrying random-impact data.
     *
     * @return array<string, mixed>|null
     */
    protected function probeRandomImpactResult(): ?array
    {
        try {
            $searchResult = $this->getFactory()->getCatalogClient()->catalogSearch(static::PROBE_SEARCH_STRING, []);
        } catch (Throwable) {
            return null;
        }

        $randomImpactResult = $searchResult[SharedSearchRankingConfig::RANDOM_IMPACT_RESULT_KEY] ?? null;

        return is_array($randomImpactResult) ? $randomImpactResult : null;
    }

    /**
     * @param array<string, mixed>|null $randomImpactResult
     *
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkResultFormatter(?array $randomImpactResult): array
    {
        $isRegistered = $randomImpactResult !== null;

        return [
            'label' => 'RandomImpactResultFormatterPlugin is registered on the Catalog client',
            'passed' => $isRegistered,
            'remedy' => $isRegistered
                ? null
                : 'A catalog search came back with no "randomImpact" key. Register RandomImpactResultFormatterPlugin in Pyz\Client\Catalog\CatalogDependencyProvider::createCatalogSearchResultFormatterPlugins() (see README step 14a).',
        ];
    }

    /**
     * The formatter returns an EMPTY payload — not an error — for a customer without the permission, and
     * for a (store, locale) with no synchronized ranking configuration. Reaching this page already proves
     * the permission, so an empty payload here means the configuration side: either never published, or
     * published with no random tie-breaker metric carrying a non-zero weight. Both leave the checkbox
     * absent from the SRP with nothing to indicate why.
     *
     * @param array<string, mixed>|null $randomImpactResult
     *
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkRandomImpactIsActive(?array $randomImpactResult): array
    {
        $isActive = ($randomImpactResult[SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE] ?? false) === true;

        return [
            'label' => 'A random tie-breaker metric is active for this store and locale',
            'passed' => $isActive,
            'remedy' => $isActive
                ? null
                : 'The formatter reports the preview as inactive here, so the checkbox never renders. Either no ranking configuration is synchronized for this store/locale (publish it and run the sync queues), or no random metric currently carries a non-zero weight (set one in Zed under Search Ranking).',
        ];
    }

    /**
     * A missing glossary key is not an error anywhere — Spryker's translator returns the key itself, so
     * the checkbox renders with a raw `search_ranking.random_impact.show` label and nothing complains.
     * Rendering through the same `trans` filter the component's own template uses makes this a faithful
     * check rather than an approximation of one.
     *
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkGlossary(): array
    {
        $isTranslated = $this->translate(static::GLOSSARY_KEY_PROBE) !== static::GLOSSARY_KEY_PROBE;

        return [
            'label' => 'Glossary translations for the random-impact preview are imported',
            'passed' => $isTranslated,
            'remedy' => $isTranslated
                ? null
                : sprintf(
                    'Copy this package\'s data/glossary.csv into your project\'s glossary data and run "vendor/bin/console data:import glossary" (see README step 14a). Until then "%s" renders as its own raw key.',
                    static::GLOSSARY_KEY_PROBE,
                ),
        ];
    }

    /**
     * @param string $glossaryKey
     */
    protected function translate(string $glossaryKey): string
    {
        try {
            return $this->getTwig()->createTemplate(sprintf('{{ %s | trans }}', var_export($glossaryKey, true)))->render();
        } catch (Throwable) {
            return $glossaryKey;
        }
    }

    /**
     * The `index.ts`-shaped trap this project has hit before: a template-paired `.scss` is silently never
     * bundled unless its directory also has an entry point, and an adopter who never re-ran the frontend
     * build gets the same symptom — an invisible badge, no error. Searching the built CSS for the badge's
     * own block name is the only honest way to tell from here.
     *
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkFrontendAssets(): array
    {
        $isBundled = $this->isAssetProbePresentInBuiltCss();

        if ($isBundled === null) {
            return [
                'label' => 'Frontend build includes this package\'s components',
                'passed' => false,
                'remedy' => 'Could not locate any built Yves CSS bundle to inspect, which normally means the frontend has never been built in this environment. Run "yarn yves" (or your project\'s equivalent) and reload.',
            ];
        }

        return [
            'label' => 'Frontend build includes this package\'s components',
            'passed' => $isBundled,
            'remedy' => $isBundled
                ? null
                : sprintf(
                    'The built CSS contains no "%s" rules, so the badges would be invisible even when the data is there. Run "yarn yves" (or your project\'s equivalent) and reload.',
                    static::FRONTEND_ASSET_PROBE,
                ),
        ];
    }

    /**
     * Null distinguishes "no bundle found at all" (nothing was ever built, or this project keeps its
     * assets somewhere non-standard) from a definite yes/no — the two deserve different remedies.
     */
    protected function isAssetProbePresentInBuiltCss(): ?bool
    {
        $cssFilePaths = $this->findBuiltCssFilePaths();

        if ($cssFilePaths === []) {
            return null;
        }

        foreach ($cssFilePaths as $cssFilePath) {
            if (str_contains((string)file_get_contents($cssFilePath), static::FRONTEND_ASSET_PROBE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walks the asset root rather than globbing a fixed depth: the built path is theme- and
     * revision-nested (`assets/<revision>/<theme>/css/`) and neither segment is fixed across projects.
     *
     * @return array<string>
     */
    protected function findBuiltCssFilePaths(): array
    {
        $assetRootPath = APPLICATION_ROOT_DIR . '/public/Yves/assets';

        if (!is_dir($assetRootPath)) {
            return [];
        }

        $cssFilePaths = [];
        $directoryIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($assetRootPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($directoryIterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'css') {
                continue;
            }

            $cssFilePaths[] = $fileInfo->getPathname();
        }

        return $cssFilePaths;
    }
}
