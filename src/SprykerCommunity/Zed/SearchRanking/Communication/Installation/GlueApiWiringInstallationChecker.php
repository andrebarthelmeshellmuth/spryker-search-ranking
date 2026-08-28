<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Installation;

use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig;

/**
 * Split out of {@see \SprykerCommunity\Zed\SearchRanking\Communication\Console\SearchRankingCheckInstallationConsole}
 * (which grew past this package's phpmd class-size threshold), the same way
 * {@see EntityLookupSyncInstallationChecker} and
 * {@see \SprykerCommunity\Zed\SearchRanking\Communication\Acl\BackOfficeAccessAnalyzer} already are for
 * that console's other checks — a single-consumer Communication-layer helper, wired through
 * {@see \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory}.
 */
class GlueApiWiringInstallationChecker implements GlueApiWiringInstallationCheckerInterface
{
    /**
     * This package only ADDITIVELY merges a `randomImpact` property onto core's `catalog-search` resource
     * (spryker/catalog-search-rest-api) — the merged schema is what this class-name check confirms exists.
     *
     * @var string
     */
    protected const GLUE_API_RESOURCE_CLASS_NAME = 'Generated\\Api\\Storefront\\CatalogSearchStorefrontResource';

    /**
     * README, "Glue REST API": the merge alone is not enough — a project-level Provider override is
     * required to actually copy the value into the response (the merged schema only describes SHAPE).
     * Relative to `APPLICATION_ROOT_DIR`, shared with spryker-community/search-debug's own `searchDebug`
     * property (both packages document registering this same override once).
     *
     * @var string
     */
    protected const GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH = '/src/Pyz/Glue/CatalogSearchRestApi/Api/Storefront/Provider/CatalogSearchStorefrontProvider.php';

    /**
     * {@inheritDoc}
     *
     * @return array{messages: array<int, string>, warnings: array<int, string>}
     */
    public function check(): array
    {
        $messages = [];
        $resourceClassName = $this->getResourceClassName();

        if (!class_exists($resourceClassName) || !method_exists($resourceClassName, 'getRandomImpact')) {
            return [
                'messages' => $messages,
                'warnings' => [sprintf(
                    '%s does not have a getRandomImpact() accessor yet: either `vendor/bin/glue api:generate storefront` has not been run since this package was installed, or (on a composer path-repository install) the Finder symlink-traversal fix from search-debug\'s README, "Glue REST API" is missing. GET /catalog-search will not include randomImpact until this is resolved. Skip this if your project does not run a Glue Storefront application.',
                    $resourceClassName,
                )],
            ];
        }

        $messages[] = sprintf('Glue API schema merge: %s has a randomImpact property', $resourceClassName);

        $overrideFilePath = $this->getProviderOverrideFilePath();

        if (!is_readable($overrideFilePath)) {
            return [
                'messages' => $messages,
                'warnings' => [sprintf(
                    'The schema merge is in place, but no project-level %s override exists (README, "Glue REST API"). The merged schema only describes SHAPE — without this override, randomImpact is silently omitted from every GET /catalog-search response.',
                    static::GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH,
                )],
            ];
        }

        $overrideFileContents = (string)file_get_contents($overrideFilePath);

        if (!str_contains($overrideFileContents, SearchRankingConfig::RANDOM_IMPACT_RESULT_KEY)) {
            return [
                'messages' => $messages,
                'warnings' => [sprintf(
                    '%s exists but does not reference "%s" — randomImpact is still silently omitted from GET /catalog-search (README, "Glue REST API").',
                    static::GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH,
                    SearchRankingConfig::RANDOM_IMPACT_RESULT_KEY,
                )],
            ];
        }

        $messages[] = 'project-level CatalogSearchStorefrontProvider override wires randomImpact into the Glue response';

        return ['messages' => $messages, 'warnings' => []];
    }

    /**
     * Isolated so a test can override it to point at a fixture class name instead of this host shop's real
     * generated Glue resource.
     */
    protected function getResourceClassName(): string
    {
        return static::GLUE_API_RESOURCE_CLASS_NAME;
    }

    /**
     * Isolated so a test can override it to point at a fixture file instead of this host shop's real
     * `src/Pyz/Glue/CatalogSearchRestApi/Api/Storefront/Provider/CatalogSearchStorefrontProvider.php`.
     */
    protected function getProviderOverrideFilePath(): string
    {
        return APPLICATION_ROOT_DIR . static::GLUE_API_PROVIDER_OVERRIDE_RELATIVE_PATH;
    }
}
