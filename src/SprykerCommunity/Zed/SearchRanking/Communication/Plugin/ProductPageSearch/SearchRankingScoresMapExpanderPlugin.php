<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Communication\Plugin\ProductPageSearch;

use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\PageMapTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface;
use Spryker\Zed\ProductPageSearchExtension\Dependency\Plugin\ProductAbstractMapExpanderPluginInterface;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;

/**
 * @method \SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchRanking\Communication\SearchRankingCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchRanking\SearchRankingConfig getConfig()
 */
class SearchRankingScoresMapExpanderPlugin extends AbstractPlugin implements ProductAbstractMapExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Writes the normalized ranking scores into the page document's `scores` object field
     *   ([metricName => normalizedValue]); products without scores get no `scores` field at all.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\PageMapTransfer $pageMapTransfer
     * @param \Spryker\Zed\ProductPageSearchExtension\Dependency\PageMapBuilderInterface $pageMapBuilder
     * @param array<string, mixed> $productData
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     */
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- signature is fixed by ProductAbstractMapExpanderPluginInterface.
    public function expandProductMap(
        PageMapTransfer $pageMapTransfer,
        PageMapBuilderInterface $pageMapBuilder,
        array $productData,
        LocaleTransfer $localeTransfer,
    ): PageMapTransfer {
        // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter
        $scores = $productData[SharedSearchRankingConfig::PAGE_INDEX_FIELD_SCORES] ?? [];

        if ($scores !== []) {
            $pageMapTransfer->setScores($scores);
        }

        return $pageMapTransfer;
    }
}
