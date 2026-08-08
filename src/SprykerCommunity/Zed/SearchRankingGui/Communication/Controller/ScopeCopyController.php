<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRankingGui\Communication\Controller;

use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \SprykerCommunity\Zed\SearchRankingGui\Communication\SearchRankingGuiCommunicationFactory getFactory()
 */
class ScopeCopyController extends AbstractController
{
    /**
     * @var string
     */
    public const PARAM_SOURCE_STORE_NAME = 'sourceStoreName';

    /**
     * @var string
     */
    public const PARAM_SOURCE_LOCALE_NAME = 'sourceLocaleName';

    /**
     * @var string
     */
    public const PARAM_TARGET_STORE_NAME = 'targetStoreName';

    /**
     * @var string
     */
    public const PARAM_TARGET_LOCALE_NAME = 'targetLocaleName';

    /**
     * The "Sync store configuration" widget has its own independent source/target store+locale pickers,
     * deliberately separate from the (store,locale)-scoped ones above — a metric can be `isLocaleScoped`
     * (see {@see \SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface}), so
     * this widget needs a real locale picker of its own too, not a shared one.
     *
     * @var string
     */
    public const PARAM_SYNC_SOURCE_STORE_NAME = 'syncSourceStoreName';

    /**
     * @var string
     */
    public const PARAM_SYNC_TARGET_STORE_NAME = 'syncTargetStoreName';

    /**
     * @var string
     */
    public const PARAM_SYNC_SOURCE_LOCALE_NAME = 'syncSourceLocaleName';

    /**
     * @var string
     */
    public const PARAM_SYNC_TARGET_LOCALE_NAME = 'syncTargetLocaleName';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    public function indexAction(Request $request): array
    {
        $stores = $this->getFactory()->getAllStoreNames();
        $locales = $this->getFactory()->getAllLocaleNames();

        [$sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName] = $this->resolveScopeParams(
            $request,
            $stores,
            static::PARAM_SOURCE_STORE_NAME,
            static::PARAM_SOURCE_LOCALE_NAME,
            static::PARAM_TARGET_STORE_NAME,
            static::PARAM_TARGET_LOCALE_NAME,
        );

        [$syncSourceStoreName, $syncSourceLocaleName, $syncTargetStoreName, $syncTargetLocaleName] = $this->resolveScopeParams(
            $request,
            $stores,
            static::PARAM_SYNC_SOURCE_STORE_NAME,
            static::PARAM_SYNC_SOURCE_LOCALE_NAME,
            static::PARAM_SYNC_TARGET_STORE_NAME,
            static::PARAM_SYNC_TARGET_LOCALE_NAME,
        );

        $searchRankingFacade = $this->getFactory()->getSearchRankingFacade();
        $activeLocks = $searchRankingFacade->getActiveScopeCopyLocks();

        return $this->viewResponse([
            'stores' => $stores,
            'locales' => $locales,
            'sourceStoreName' => $sourceStoreName,
            'sourceLocaleName' => $sourceLocaleName,
            'targetStoreName' => $targetStoreName,
            'targetLocaleName' => $targetLocaleName,
            'syncSourceStoreName' => $syncSourceStoreName,
            'syncTargetStoreName' => $syncTargetStoreName,
            'syncSourceLocaleName' => $syncSourceLocaleName,
            'syncTargetLocaleName' => $syncTargetLocaleName,
            'hasTargetData' => $searchRankingFacade->hasScopeConfiguration($targetStoreName, $targetLocaleName),
            'hasTargetStoreConfig' => $searchRankingFacade->hasStoreConfiguration($syncTargetStoreName),
            'scopeCopyPreview' => $searchRankingFacade->previewScopeConfigurationCopy($sourceStoreName, $sourceLocaleName),
            'storeConfigPreview' => $searchRankingFacade->previewStoreConfigurationSync($syncSourceStoreName, $syncSourceLocaleName),
            'activeLocks' => $activeLocks,
            'copyForm' => $this->getFactory()->createScopeCopyActionForm()->createView(),
            'lockForm' => $this->getFactory()->createScopeCopyActionForm()->createView(),
            'storeConfigSyncForm' => $this->getFactory()->createStoreConfigSyncActionForm()->createView(),
            // One fresh form view per row — a single FormView reused across a Twig loop only renders its
            // (CSRF) field on the first iteration, silently leaving every later row's Unlock button
            // without a valid token.
            'unlockFormsByIdScopeCopyLock' => $this->createUnlockFormsByIdScopeCopyLock($activeLocks),
        ]);
    }

    /**
     * Extracted purely to keep indexAction()'s own size/complexity down — the (source store, source
     * locale, target store, target locale) resolution is identical for the "Copy configuration between
     * store/locale scopes" widget and the independent "Sync store configuration" widget, just against a
     * different set of query param names, no behavioral change from sharing it.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param array<string> $stores
     * @param string $sourceStoreParamName
     * @param string $sourceLocaleParamName
     * @param string $targetStoreParamName
     * @param string $targetLocaleParamName
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function resolveScopeParams(
        Request $request,
        array $stores,
        string $sourceStoreParamName,
        string $sourceLocaleParamName,
        string $targetStoreParamName,
        string $targetLocaleParamName,
    ): array {
        $sourceStoreName = (string)$request->query->get($sourceStoreParamName, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_STORE_NAME;
        $sourceLocaleName = (string)$request->query->get($sourceLocaleParamName, '') ?: SharedSearchRankingConfig::DEFAULT_SCOPE_LOCALE_NAME;
        $targetStoreName = (string)$request->query->get($targetStoreParamName, '') ?: $this->resolveDefaultTargetStoreName($stores, $sourceStoreName);
        $targetLocaleName = (string)$request->query->get($targetLocaleParamName, '') ?: $sourceLocaleName;

        return [$sourceStoreName, $sourceLocaleName, $targetStoreName, $targetLocaleName];
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchRankingScopeCopyLockTransfer> $activeLocks
     *
     * @return array<int, \Symfony\Component\Form\FormView>
     */
    protected function createUnlockFormsByIdScopeCopyLock(array $activeLocks): array
    {
        $unlockFormsByIdScopeCopyLock = [];

        foreach ($activeLocks as $activeLock) {
            $unlockFormsByIdScopeCopyLock[$activeLock->getIdSearchRankingScopeCopyLockOrFail()] = $this->getFactory()
                ->createScopeCopyUnlockForm()
                ->createView();
        }

        return $unlockFormsByIdScopeCopyLock;
    }

    /**
     * Picks the first store that isn't the source, so the page doesn't land on an always-invalid
     * (source == target) default when more than one store exists. Falls back to the source itself for a
     * single-store shop — harmless, the page just won't let you actually copy/lock until you pick a
     * locale-only difference or a second store gets added.
     *
     * @param array<string> $stores
     * @param string $sourceStoreName
     */
    protected function resolveDefaultTargetStoreName(array $stores, string $sourceStoreName): string
    {
        foreach ($stores as $store) {
            if ($store !== $sourceStoreName) {
                return $store;
            }
        }

        return $sourceStoreName;
    }
}
