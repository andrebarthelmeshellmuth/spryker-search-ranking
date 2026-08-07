<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchRanking\Business\Metric;

use Generated\Shared\Transfer\EventEntityTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Shared\SearchRanking\SearchRankingEvents;
use SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

class MetricWriter implements MetricWriterInterface
{
    /**
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingEntityManagerInterface $entityManager
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface $formulaEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Fitting\MetricFormulaFitEvaluatorInterface $fitEvaluator
     * @param \SprykerCommunity\Zed\SearchRanking\Business\Fitting\NormalizationCurveFitterInterface $curveFitter
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToEventFacadeInterface $eventFacade
     * @param \SprykerCommunity\Zed\SearchRanking\Dependency\Facade\SearchRankingToStoreFacadeInterface $storeFacade
     */
    public function __construct(
        protected SearchRankingRepositoryInterface $repository,
        protected SearchRankingEntityManagerInterface $entityManager,
        protected FormulaEvaluatorInterface $formulaEvaluator,
        protected MetricFormulaFitEvaluatorInterface $fitEvaluator,
        protected NormalizationCurveFitterInterface $curveFitter,
        protected SearchRankingToEventFacadeInterface $eventFacade,
        protected SearchRankingToStoreFacadeInterface $storeFacade,
    ) {
    }

    /**
     * Writes the metric's identity fields — see this method's own interface docblock. The incoming
     * transfer's `weight` (if any) is deliberately ignored here; use {@see saveMetricWeight()} instead.
     *
     * $storeName is real here — formula/isActive/shape are saved for THIS store specifically, not
     * locale-scoped at all. $localeName is used ONLY as a lens: which (store, locale) digest to fit the
     * formula's shape against ({@see detectShape()}) and to snapshot in the history row
     * ({@see recordHistory()}) — never persisted as part of the metric's own scope. Threaded through as
     * the caller's real, currently-viewed locale (not a hardcoded default), specifically so the shape
     * saved here matches what the admin already saw in the live formula-preview endpoint, which uses the
     * real request locale too — a hardcoded default here would silently mismatch whatever locale the
     * admin was actually viewing. Digest granularity is (store, locale): if the viewed locale has no
     * digest yet, shape/fit simply comes back null/absent.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     *
     * @throws \SprykerCommunity\Zed\SearchRanking\Business\Exception\InvalidFormulaException
     */
    public function saveMetric(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): SearchRankingMetricTransfer
    {
        $validationResponseTransfer = $this->formulaEvaluator->validate($metricTransfer->getFormulaOrFail());

        if (!$validationResponseTransfer->getIsSuccess()) {
            throw new InvalidFormulaException((string)$validationResponseTransfer->getErrorMessage());
        }

        $previousMetricTransfer = $metricTransfer->getIdSearchRankingMetric() !== null
            ? $this->repository->findMetricById(
                $metricTransfer->getIdSearchRankingMetric(),
                $storeName,
                $localeName,
            )
            : null;

        $metricTransfer->setShape($this->detectShape($metricTransfer, $storeName, $localeName));

        $savedMetricTransfer = $this->entityManager->saveMetric($metricTransfer, $storeName);
        $this->eventFacade->trigger(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE, new EventEntityTransfer());

        if ($this->hasAnyTrackedFieldChanged($previousMetricTransfer, $savedMetricTransfer)) {
            // formula/isActive are store-wide, not locale-scoped — a single history row keyed to
            // whichever locale the admin happened to be viewing would under-represent the change (it
            // reads as if the change only applied to that one locale). Fan out one row per REAL locale
            // of this store instead, so
            // every locale the change actually affects gets its own honest snapshot (with that locale's
            // own real weight/digest/fit — never a duplicate/fake one). $localeName (the viewed locale) is
            // the fallback if the store can't be resolved, and is always itself one of the real locales
            // looped over below, so it's never skipped.
            $changeSource = $metricTransfer->getChangeSource() ?? SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL;

            foreach ($this->resolveLocaleNamesForStore($storeName, $localeName) as $historyLocaleName) {
                $this->recordHistory($savedMetricTransfer, $storeName, $historyLocaleName, $changeSource);
            }
        }

        return $savedMetricTransfer;
    }

    /**
     * Every real locale of $storeName, per the Store facade — falls back to just `[$fallbackLocaleName]`
     * if the store can't be found (shouldn't happen in practice, but a missing store is not a reason to
     * lose the history write entirely).
     *
     * @param string $storeName
     * @param string $fallbackLocaleName
     *
     * @return array<string>
     */
    protected function resolveLocaleNamesForStore(string $storeName, string $fallbackLocaleName): array
    {
        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            if ($storeTransfer->getNameOrFail() === $storeName) {
                return $storeTransfer->getAvailableLocaleIsoCodes();
            }
        }

        return [$fallbackLocaleName];
    }

    /**
     * {@inheritDoc}
     *
     * Fetches the metric up front (rather than only once a change is confirmed, like the rest of this
     * method used to) because {@see SearchRankingMetricTransfer::getIsLocaleScoped()} must be known
     * BEFORE writing anything, to decide whether this write targets just $localeName or every real
     * locale of $storeName.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     * @param string $changeSource One of {@see \SprykerCommunity\Shared\SearchRanking\SearchRankingConfig}::CHANGE_SOURCE_* — defaults to CHANGE_SOURCE_MANUAL, correct for every caller inside this package; only an automated writer elsewhere (e.g. search-ranking-optimizer applying an optimizer run or restoring a checkpoint) needs to pass a different one.
     */
    public function saveMetricWeight(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        float $weight,
        string $changeSource = SharedSearchRankingConfig::CHANGE_SOURCE_MANUAL,
    ): void {
        $metricTransfer = $this->repository->findMetricById($idSearchRankingMetric, $storeName, $localeName);

        // Not locale-scoped: fan the same write out to every real locale of the store, same
        // resolveLocaleNamesForStore() pattern saveMetric() already uses for its own history fan-out —
        // keeps every locale's weight row in sync automatically instead of requiring N manual edits.
        $targetLocaleNames = $metricTransfer !== null && $metricTransfer->getIsLocaleScoped() === false
            ? $this->resolveLocaleNamesForStore($storeName, $localeName)
            : [$localeName];

        foreach ($targetLocaleNames as $targetLocaleName) {
            $this->saveMetricWeightForLocale($idSearchRankingMetric, $storeName, $targetLocaleName, $weight, $metricTransfer, $changeSource);
        }

        $this->eventFacade->trigger(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE, new EventEntityTransfer());
    }

    /**
     * {@inheritDoc}
     *
     * Deliberately re-derives {@see saveMetricWeight()}'s own fan-out decision independently (same
     * `findMetricById()` + `getIsLocaleScoped()` check, own resolveLocaleNamesForStore() call) rather than
     * refactoring saveMetricWeight() to call this — zero risk to that already-tested, already-live-
     * verified write path.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     *
     * @return array<string>
     */
    public function resolveEffectiveWeightLocales(int $idSearchRankingMetric, string $storeName, string $localeName): array
    {
        $metricTransfer = $this->repository->findMetricById($idSearchRankingMetric, $storeName, $localeName);

        if ($metricTransfer !== null && $metricTransfer->getIsLocaleScoped() === false) {
            return $this->resolveLocaleNamesForStore($storeName, $localeName);
        }

        return [$localeName];
    }

    /**
     * The single-locale write {@see saveMetricWeight()} used to do inline, now reusable per fanned-out
     * locale. $metricTransfer is passed in rather than re-fetched per locale — it's the same global
     * identity record (name/isHigherBetter/isLocaleScoped) regardless of which locale it was looked up
     * with, so one fetch in the caller is enough.
     *
     * @param int $idSearchRankingMetric
     * @param string $storeName
     * @param string $localeName
     * @param float $weight
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer|null $metricTransfer
     * @param string $changeSource
     */
    protected function saveMetricWeightForLocale(
        int $idSearchRankingMetric,
        string $storeName,
        string $localeName,
        float $weight,
        ?SearchRankingMetricTransfer $metricTransfer,
        string $changeSource,
    ): void {
        $previousWeight = $this->repository->findMetricWeight($idSearchRankingMetric, $storeName, $localeName);

        $this->entityManager->saveMetricWeight($idSearchRankingMetric, $storeName, $localeName, $weight);

        if ($previousWeight === $weight || $metricTransfer === null) {
            return;
        }

        $this->recordHistory($metricTransfer, $storeName, $localeName, $changeSource);
    }

    /**
     * Derives $metricTransfer's curve-family `shape` slug by re-running {@see NormalizationCurveFitter}
     * against the metric's own current digest and looking for a candidate whose fully-substituted
     * `formula` string matches the metric's submitted one exactly — both the candidate and a genuinely
     * shape-following formula are deterministic functions of the same digest, so a real match reproduces
     * byte-for-byte. Deliberately NOT derived any other way (e.g. parsing the formula string): the Edit
     * page's "Use this formula" button only ever copies plain text into the form field, so this is the
     * only reliable signal available for "which shape is this, if any" without changing that UI.
     * Returns null (a freeform/custom formula, or no digest yet to fit against) when nothing matches —
     * a safe, expected outcome, not an error.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    protected function detectShape(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): ?string
    {
        if ($metricTransfer->getIdSearchRankingMetric() === null) {
            return null;
        }

        $digestTransfer = $this->repository->findMetricDigest(
            $metricTransfer->getIdSearchRankingMetric(),
            $storeName,
            $localeName,
        );

        if ($digestTransfer === null) {
            return null;
        }

        foreach ($this->curveFitter->fit($digestTransfer, $metricTransfer->getIsHigherBetter() ?? true) as $candidateTransfer) {
            if ($candidateTransfer->getFormula() === $metricTransfer->getFormula()) {
                return $candidateTransfer->getShape();
            }
        }

        return null;
    }

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $this->entityManager->deleteMetric($idSearchRankingMetric);
        $this->eventFacade->trigger(SearchRankingEvents::RANKING_CONFIGURATION_CHANGE, new EventEntityTransfer());
    }

    /**
     * Null $previousMetricTransfer means a brand-new metric — always worth an initial history row, since
     * there is nothing to compare it against yet. Weight is deliberately NOT part of this comparison —
     * {@see saveMetricWeight()} tracks its own changes independently, at whatever (store, locale) it was
     * called with.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer|null $previousMetricTransfer
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $currentMetricTransfer
     */
    protected function hasAnyTrackedFieldChanged(
        ?SearchRankingMetricTransfer $previousMetricTransfer,
        SearchRankingMetricTransfer $currentMetricTransfer,
    ): bool {
        if ($previousMetricTransfer === null) {
            return true;
        }

        return $previousMetricTransfer->getFormula() !== $currentMetricTransfer->getFormula()
            || $previousMetricTransfer->getIsActive() !== $currentMetricTransfer->getIsActive()
            || $previousMetricTransfer->getIsHigherBetter() !== $currentMetricTransfer->getIsHigherBetter()
            || $previousMetricTransfer->getIsLocaleScoped() !== $currentMetricTransfer->getIsLocaleScoped();
    }

    /**
     * Snapshots the metric's now-current config, weight (at the given store+locale), and digest (if one
     * exists yet — a brand-new metric has none until the normalize cron has run at least once), so a
     * later drift-detection read can compare against exactly what was true at the moment this change was
     * made.
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     * @param string $changeSource
     */
    protected function recordHistory(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName, string $changeSource): void
    {
        $this->entityManager->recordMetricHistory($this->buildHistoryTransfer($metricTransfer, $storeName, $localeName, true, $changeSource));
    }

    /**
     * {@inheritDoc}
     *
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     */
    public function recordCheckOnly(SearchRankingMetricTransfer $metricTransfer, string $storeName, string $localeName): void
    {
        // recordCheckOnly() has no caller inside this package's own Zed pages -- today the only writer is
        // search-ranking-optimizer's Auto-Tune runner, checking a metric's fit without necessarily
        // updating it. Hardcoded rather than threaded as a parameter until a second, differently-sourced
        // check-only caller actually exists.
        $this->entityManager->recordMetricHistory(
            $this->buildHistoryTransfer($metricTransfer, $storeName, $localeName, false, SharedSearchRankingConfig::CHANGE_SOURCE_AUTO_TUNE),
        );
    }

    /**
     * @param \Generated\Shared\Transfer\SearchRankingMetricTransfer $metricTransfer
     * @param string $storeName
     * @param string $localeName
     * @param bool $isChange
     * @param string $changeSource
     */
    protected function buildHistoryTransfer(
        SearchRankingMetricTransfer $metricTransfer,
        string $storeName,
        string $localeName,
        bool $isChange,
        string $changeSource,
    ): SearchRankingMetricHistoryTransfer {
        $weight = $this->repository->findMetricWeight($metricTransfer->getIdSearchRankingMetricOrFail(), $storeName, $localeName) ?? 0.0;

        $historyTransfer = (new SearchRankingMetricHistoryTransfer())
            ->setFkSearchRankingMetric($metricTransfer->getIdSearchRankingMetricOrFail())
            ->setMetricName($metricTransfer->getNameOrFail())
            ->setStoreName($storeName)
            ->setLocaleName($localeName)
            ->setWeight($weight)
            ->setFormula($metricTransfer->getFormulaOrFail())
            ->setIsActive($metricTransfer->getIsActive() ?? true)
            ->setIsHigherBetter($metricTransfer->getIsHigherBetter() ?? true)
            ->setIsLocaleScoped($metricTransfer->getIsLocaleScoped() ?? true)
            ->setIsChange($isChange)
            ->setChangeSource($changeSource);

        $digestTransfer = $this->repository->findMetricDigest(
            $metricTransfer->getIdSearchRankingMetricOrFail(),
            $storeName,
            $localeName,
        );

        if ($digestTransfer !== null) {
            $historyTransfer
                ->setMinValue($digestTransfer->getMinValue())
                ->setMaxValue($digestTransfer->getMaxValue())
                ->setMeanValue($digestTransfer->getMeanValue())
                ->setMedianValue($digestTransfer->getMedianValue())
                ->setSampleCount($digestTransfer->getSampleCount())
                ->setPercentiles($digestTransfer->getPercentiles())
                ->setFitRSquared($this->fitEvaluator->evaluateFit($metricTransfer->getFormulaOrFail(), $digestTransfer));
        }

        return $historyTransfer;
    }
}
