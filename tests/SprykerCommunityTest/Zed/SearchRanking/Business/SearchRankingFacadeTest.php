<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ProductPageLoadTransfer;
use Generated\Shared\Transfer\SearchRankingConfigurationStorageTransfer;
use Generated\Shared\Transfer\SearchRankingEngineCompatibilityTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFormulaValidationResponseTransfer;
use Generated\Shared\Transfer\SearchRankingFullScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingFullScopeCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricDigestTransfer;
use Generated\Shared\Transfer\SearchRankingMetricHistoryTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use Generated\Shared\Transfer\SearchRankingNormalizationResultTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyPreviewTransfer;
use Generated\Shared\Transfer\SearchRankingScopeCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigCopyResultTransfer;
use Generated\Shared\Transfer\SearchRankingStoreConfigPreviewTransfer;
use SprykerCommunity\Zed\SearchRanking\Business\Compatibility\CompatibilityCheckerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Configuration\ConfigurationReaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Digest\MetricDigestBuilderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Fitting\CurrentMetricFitEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Formula\FormulaEvaluatorInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\WeightNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Normalizer\ProductMetricNormalizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\PageData\ScoresPageDataLoaderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Preview\FormulaPreviewBuilderInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Publisher\ProductAbstractScorePublisherInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Randomizer\MetricRandomizerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\FullScopeCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeCopyLockManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingBusinessFactory;
use SprykerCommunity\Zed\SearchRanking\Business\SearchRankingFacade;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Specificity\SpecificityWeightingStatusCheckerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepository;

/**
 * Every public method here really is a one-hop delegation to a factory-built collaborator or the
 * repository, and returns exactly what that collaborator returns, unmodified -- every collaborator's own
 * real logic already has its own dedicated test (`MetricWriterTest`, `ScopeCopyLockManagerTest`, etc.).
 * This test's only job is proving the Facade wires the RIGHT collaborator to the RIGHT arguments, same
 * shape as `SearchRankingClient`'s/`SearchFeedbackClient`'s own passthrough tests.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group SearchRankingFacadeTest
 * @group Portable
 */
class SearchRankingFacadeTest extends Unit
{
    public function testGetMetricCollectionComposesRepositoryCollectionWithAttachedWeights(): void
    {
        $collection = new SearchRankingMetricCollectionTransfer();
        $weighted = new SearchRankingMetricCollectionTransfer();

        $repositoryMock = $this->createRepositoryMock();
        $repositoryMock->method('getMetricCollection')->with('DE', 'de_DE')->willReturn($collection);
        $repositoryMock->method('attachWeights')->with($collection, 'DE', 'de_DE')->willReturn($weighted);

        $facade = $this->buildFacadeWithRepository($repositoryMock);

        $this->assertSame($weighted, $facade->getMetricCollection('DE', 'de_DE'));
    }

    public function testGetActiveMetricCollectionComposesRepositoryCollectionWithAttachedWeights(): void
    {
        $collection = new SearchRankingMetricCollectionTransfer();
        $weighted = new SearchRankingMetricCollectionTransfer();

        $repositoryMock = $this->createRepositoryMock();
        $repositoryMock->method('getActiveMetricCollection')->with('DE', 'de_DE')->willReturn($collection);
        $repositoryMock->method('attachWeights')->with($collection, 'DE', 'de_DE')->willReturn($weighted);

        $facade = $this->buildFacadeWithRepository($repositoryMock);

        $this->assertSame($weighted, $facade->getActiveMetricCollection('DE', 'de_DE'));
    }

    public function testGetConfigurationDelegatesToTheConfigurationReader(): void
    {
        $configurationTransfer = new SearchRankingConfigurationStorageTransfer();

        $configurationReaderMock = $this->createMock(ConfigurationReaderInterface::class);
        $configurationReaderMock->method('getConfiguration')->with('DE', 'de_DE')->willReturn($configurationTransfer);

        $factoryMock = $this->createFactoryMock(['createConfigurationReader'], ['createConfigurationReader' => $configurationReaderMock]);
        $facade = $this->buildFacadeWithFactory($factoryMock);

        $this->assertSame($configurationTransfer, $facade->getConfiguration('DE', 'de_DE'));
    }

    public function testGetRelevanceWeightDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getRelevanceWeight')->with('DE', 'de_DE')->willReturn(0.5);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(0.5, $facade->getRelevanceWeight('DE', 'de_DE'));
    }

    public function testSaveRelevanceWeightDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveRelevanceWeight')->with('DE', 'de_DE', 0.5);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveRelevanceWeight('DE', 'de_DE', 0.5);
    }

    public function testGetRelevanceSaturationPointDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getRelevanceSaturationPoint')->with('DE', 'de_DE')->willReturn(1.2);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(1.2, $facade->getRelevanceSaturationPoint('DE', 'de_DE'));
    }

    public function testSaveRelevanceSaturationPointDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveRelevanceSaturationPoint')->with('DE', 'de_DE', 1.2);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveRelevanceSaturationPoint('DE', 'de_DE', 1.2);
    }

    public function testGetSpecificityBlendWeightDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getSpecificityBlendWeight')->with('DE', 'de_DE')->willReturn(0.3);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(0.3, $facade->getSpecificityBlendWeight('DE', 'de_DE'));
    }

    public function testSaveSpecificityBlendWeightDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveSpecificityBlendWeight')->with('DE', 'de_DE', 0.3);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveSpecificityBlendWeight('DE', 'de_DE', 0.3);
    }

    public function testGetSpecificitySaturationPointDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getSpecificitySaturationPoint')->with('DE', 'de_DE')->willReturn(2.0);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(2.0, $facade->getSpecificitySaturationPoint('DE', 'de_DE'));
    }

    public function testSaveSpecificitySaturationPointDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveSpecificitySaturationPoint')->with('DE', 'de_DE', 2.0);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveSpecificitySaturationPoint('DE', 'de_DE', 2.0);
    }

    public function testGetSpecificityCurveExponentDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getSpecificityCurveExponent')->with('DE', 'de_DE')->willReturn(1.5);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(1.5, $facade->getSpecificityCurveExponent('DE', 'de_DE'));
    }

    public function testSaveSpecificityCurveExponentDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveSpecificityCurveExponent')->with('DE', 'de_DE', 1.5);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveSpecificityCurveExponent('DE', 'de_DE', 1.5);
    }

    public function testGetSpecificityWeightExponentDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getSpecificityWeightExponent')->with('DE', 'de_DE')->willReturn(0.8);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(0.8, $facade->getSpecificityWeightExponent('DE', 'de_DE'));
    }

    public function testSaveSpecificityWeightExponentDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveSpecificityWeightExponent')->with('DE', 'de_DE', 0.8);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveSpecificityWeightExponent('DE', 'de_DE', 0.8);
    }

    public function testGetSpecificityWeightShiftMagnitudeDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->method('getSpecificityWeightShiftMagnitude')->with('DE', 'de_DE')->willReturn(0.1);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $this->assertSame(0.1, $facade->getSpecificityWeightShiftMagnitude('DE', 'de_DE'));
    }

    public function testSaveSpecificityWeightShiftMagnitudeDelegatesToTheSettingManager(): void
    {
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        $settingManagerMock->expects($this->once())->method('saveSpecificityWeightShiftMagnitude')->with('DE', 'de_DE', 0.1);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSettingManager'], ['createSettingManager' => $settingManagerMock]));

        $facade->saveSpecificityWeightShiftMagnitude('DE', 'de_DE', 0.1);
    }

    public function testNormalizeActiveMetricWeightsDelegatesToTheWeightNormalizer(): void
    {
        $normalizerMock = $this->createMock(WeightNormalizerInterface::class);
        $normalizerMock->method('normalizeActiveWeights')->with('DE', 'de_DE')->willReturn(true);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createWeightNormalizer'], ['createWeightNormalizer' => $normalizerMock]));

        $this->assertTrue($facade->normalizeActiveMetricWeights('DE', 'de_DE'));
    }

    public function testFindMetricByIdDelegatesToTheRepository(): void
    {
        $metricTransfer = new SearchRankingMetricTransfer();

        $repositoryMock = $this->createRepositoryMock();
        $repositoryMock->method('findMetricById')->with(1, 'DE', 'de_DE')->willReturn($metricTransfer);

        $facade = $this->buildFacadeWithRepository($repositoryMock);

        $this->assertSame($metricTransfer, $facade->findMetricById(1, 'DE', 'de_DE'));
    }

    public function testFindMetricByNameDelegatesToTheRepositoryWithTheDefaultScope(): void
    {
        $metricTransfer = new SearchRankingMetricTransfer();

        $repositoryMock = $this->createRepositoryMock();
        $repositoryMock->method('findMetricByName')->with('relevance', 'DE', 'de_DE')->willReturn($metricTransfer);

        $facade = $this->buildFacadeWithRepository($repositoryMock);

        $this->assertSame($metricTransfer, $facade->findMetricByName('relevance'));
    }

    public function testSaveMetricDelegatesToTheMetricWriter(): void
    {
        $requested = new SearchRankingMetricTransfer();
        $saved = new SearchRankingMetricTransfer();

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->method('saveMetric')->with($requested, 'DE', 'de_DE')->willReturn($saved);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricWriter'], ['createMetricWriter' => $metricWriterMock]));

        $this->assertSame($saved, $facade->saveMetric($requested, 'DE', 'de_DE'));
    }

    public function testSaveMetricWeightDelegatesToTheMetricWriter(): void
    {
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->once())->method('saveMetricWeight')->with(1, 'DE', 'de_DE', 0.5, 'manual');

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricWriter'], ['createMetricWriter' => $metricWriterMock]));

        $facade->saveMetricWeight(1, 'DE', 'de_DE', 0.5, 'manual');
    }

    public function testResolveEffectiveWeightLocalesDelegatesToTheMetricWriter(): void
    {
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->method('resolveEffectiveWeightLocales')->with(1, 'DE', 'de_DE')->willReturn(['de_DE', 'de_AT']);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricWriter'], ['createMetricWriter' => $metricWriterMock]));

        $this->assertSame(['de_DE', 'de_AT'], $facade->resolveEffectiveWeightLocales(1, 'DE', 'de_DE'));
    }

    public function testDeleteMetricDelegatesToTheMetricWriter(): void
    {
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->once())->method('deleteMetric')->with(1);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricWriter'], ['createMetricWriter' => $metricWriterMock]));

        $facade->deleteMetric(1);
    }

    public function testValidateFormulaDelegatesToTheFormulaEvaluator(): void
    {
        $responseTransfer = new SearchRankingFormulaValidationResponseTransfer();

        $formulaEvaluatorMock = $this->createMock(FormulaEvaluatorInterface::class);
        $formulaEvaluatorMock->method('validate')->with('a + b')->willReturn($responseTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createFormulaEvaluator'], ['createFormulaEvaluator' => $formulaEvaluatorMock]));

        $this->assertSame($responseTransfer, $facade->validateFormula('a + b'));
    }

    public function testNormalizeProductMetricValuesDelegatesToTheProductMetricNormalizer(): void
    {
        $resultTransfer = new SearchRankingNormalizationResultTransfer();

        $normalizerMock = $this->createMock(ProductMetricNormalizerInterface::class);
        $normalizerMock->method('normalize')->with('DE', 'de_DE')->willReturn($resultTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createProductMetricNormalizer'], ['createProductMetricNormalizer' => $normalizerMock]));

        $this->assertSame($resultTransfer, $facade->normalizeProductMetricValues('DE', 'de_DE'));
    }

    public function testExpandProductPageLoadTransferWithScoresDelegatesToTheScoresPageDataLoader(): void
    {
        $productPageLoadTransfer = new ProductPageLoadTransfer();
        $expanded = new ProductPageLoadTransfer();

        $loaderMock = $this->createMock(ScoresPageDataLoaderInterface::class);
        $loaderMock->method('expandProductPageLoadTransfer')->with($productPageLoadTransfer)->willReturn($expanded);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScoresPageDataLoader'], ['createScoresPageDataLoader' => $loaderMock]));

        $this->assertSame($expanded, $facade->expandProductPageLoadTransferWithScores($productPageLoadTransfer));
    }

    public function testPublishScoredProductAbstractsDelegatesToTheProductAbstractScorePublisher(): void
    {
        $publisherMock = $this->createMock(ProductAbstractScorePublisherInterface::class);
        $publisherMock->method('publishScoredProductAbstracts')->willReturn(7);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createProductAbstractScorePublisher'], ['createProductAbstractScorePublisher' => $publisherMock]));

        $this->assertSame(7, $facade->publishScoredProductAbstracts());
    }

    public function testRebuildMetricDigestsDelegatesToTheMetricDigestBuilder(): void
    {
        $builderMock = $this->createMock(MetricDigestBuilderInterface::class);
        $builderMock->method('rebuildDigests')->with('DE', 'de_DE')->willReturn(4);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricDigestBuilder'], ['createMetricDigestBuilder' => $builderMock]));

        $this->assertSame(4, $facade->rebuildMetricDigests('DE', 'de_DE'));
    }

    public function testFindMetricDigestDelegatesToTheRepository(): void
    {
        $digestTransfer = new SearchRankingMetricDigestTransfer();

        $repositoryMock = $this->createRepositoryMock();
        $repositoryMock->method('findMetricDigest')->with(1, 'DE', 'de_DE')->willReturn($digestTransfer);

        $facade = $this->buildFacadeWithRepository($repositoryMock);

        $this->assertSame($digestTransfer, $facade->findMetricDigest(1, 'DE', 'de_DE'));
    }

    public function testPreviewFormulaDelegatesToTheFormulaPreviewBuilder(): void
    {
        $previewTransfer = new SearchRankingFormulaPreviewTransfer();

        $builderMock = $this->createMock(FormulaPreviewBuilderInterface::class);
        $builderMock->method('buildPreview')->with(1, 'a + b', true, 'DE', 'de_DE')->willReturn($previewTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createFormulaPreviewBuilder'], ['createFormulaPreviewBuilder' => $builderMock]));

        $this->assertSame($previewTransfer, $facade->previewFormula(1, 'a + b', true, 'DE', 'de_DE'));
    }

    public function testRandomizeRandomMetricIfActiveDelegatesToTheMetricRandomizer(): void
    {
        $randomizerMock = $this->createMock(MetricRandomizerInterface::class);
        $randomizerMock->method('randomizeIfActive')->with('DE', 'de_DE')->willReturn(true);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricRandomizer'], ['createMetricRandomizer' => $randomizerMock]));

        $this->assertTrue($facade->randomizeRandomMetricIfActive('DE', 'de_DE'));
    }

    public function testCheckEngineCompatibilityDelegatesToTheCompatibilityChecker(): void
    {
        $compatibilityTransfer = new SearchRankingEngineCompatibilityTransfer();

        $checkerMock = $this->createMock(CompatibilityCheckerInterface::class);
        $checkerMock->method('checkCompatibility')->willReturn($compatibilityTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createCompatibilityChecker'], ['createCompatibilityChecker' => $checkerMock]));

        $this->assertSame($compatibilityTransfer, $facade->checkEngineCompatibility());
    }

    public function testIsSpecificityWeightingEnabledDelegatesToTheSpecificityWeightingStatusChecker(): void
    {
        $checkerMock = $this->createMock(SpecificityWeightingStatusCheckerInterface::class);
        $checkerMock->method('isEnabled')->willReturn(true);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createSpecificityWeightingStatusChecker'], ['createSpecificityWeightingStatusChecker' => $checkerMock]));

        $this->assertTrue($facade->isSpecificityWeightingEnabled());
    }

    public function testRecordCheckOnlyDelegatesToTheMetricWriter(): void
    {
        $metricTransfer = new SearchRankingMetricTransfer();

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->once())->method('recordCheckOnly')->with($metricTransfer, 'DE', 'de_DE');

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createMetricWriter'], ['createMetricWriter' => $metricWriterMock]));

        $facade->recordCheckOnly($metricTransfer, 'DE', 'de_DE');
    }

    public function testFindLastMetricChangeHistoryEntryDelegatesToTheRepositoryWithTheDefaultScope(): void
    {
        $historyTransfer = new SearchRankingMetricHistoryTransfer();

        $repositoryMock = $this->createRepositoryMock();
        $repositoryMock->method('findLastMetricChangeHistoryEntry')->with(1, 'DE', 'de_DE')->willReturn($historyTransfer);

        $facade = $this->buildFacadeWithRepository($repositoryMock);

        $this->assertSame($historyTransfer, $facade->findLastMetricChangeHistoryEntry(1));
    }

    public function testEvaluateCurrentMetricFitDelegatesToTheCurrentMetricFitEvaluator(): void
    {
        $evaluatorMock = $this->createMock(CurrentMetricFitEvaluatorInterface::class);
        $evaluatorMock->method('evaluate')->with(1, 'DE', 'de_DE')->willReturn(0.9);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createCurrentMetricFitEvaluator'], ['createCurrentMetricFitEvaluator' => $evaluatorMock]));

        $this->assertSame(0.9, $facade->evaluateCurrentMetricFit(1, 'DE', 'de_DE'));
    }

    public function testEvaluateCurrentMetricFitAcrossLocalesDelegatesToTheCurrentMetricFitEvaluator(): void
    {
        $evaluatorMock = $this->createMock(CurrentMetricFitEvaluatorInterface::class);
        $evaluatorMock->method('evaluateAcrossLocales')->with(1, 'DE')->willReturn(['de_DE' => 0.9]);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createCurrentMetricFitEvaluator'], ['createCurrentMetricFitEvaluator' => $evaluatorMock]));

        $this->assertSame(['de_DE' => 0.9], $facade->evaluateCurrentMetricFitAcrossLocales(1, 'DE'));
    }

    public function testCopyScopeConfigurationDelegatesToTheScopeConfigCopier(): void
    {
        $resultTransfer = new SearchRankingScopeCopyResultTransfer();

        $copierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $copierMock->method('copyScopeConfiguration')->with('DE', 'de_DE', 'AT', 'de_AT', 'manual', true, 'scope_copy')->willReturn($resultTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeConfigCopier'], ['createScopeConfigCopier' => $copierMock]));

        $this->assertSame($resultTransfer, $facade->copyScopeConfiguration('DE', 'de_DE', 'AT', 'de_AT', 'manual', true));
    }

    public function testHasScopeConfigurationDelegatesToTheScopeConfigCopier(): void
    {
        $copierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $copierMock->method('hasScopeConfiguration')->with('DE', 'de_DE')->willReturn(true);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeConfigCopier'], ['createScopeConfigCopier' => $copierMock]));

        $this->assertTrue($facade->hasScopeConfiguration('DE', 'de_DE'));
    }

    public function testPreviewScopeConfigurationCopyDelegatesToTheScopeConfigCopier(): void
    {
        $previewTransfer = new SearchRankingScopeCopyPreviewTransfer();

        $copierMock = $this->createMock(ScopeConfigCopierInterface::class);
        $copierMock->method('previewScopeConfiguration')->with('DE', 'de_DE')->willReturn($previewTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeConfigCopier'], ['createScopeConfigCopier' => $copierMock]));

        $this->assertSame($previewTransfer, $facade->previewScopeConfigurationCopy('DE', 'de_DE'));
    }

    public function testCopyStoreConfigurationDelegatesToTheStoreConfigCopier(): void
    {
        $resultTransfer = new SearchRankingStoreConfigCopyResultTransfer();

        $copierMock = $this->createMock(StoreConfigCopierInterface::class);
        $copierMock->method('copyStoreConfiguration')->with('DE', 'de_DE', 'AT', 'de_AT', 'manual', true, 'scope_copy')->willReturn($resultTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createStoreConfigCopier'], ['createStoreConfigCopier' => $copierMock]));

        $this->assertSame($resultTransfer, $facade->copyStoreConfiguration('DE', 'de_DE', 'AT', 'de_AT', 'manual', true));
    }

    public function testHasStoreConfigurationDelegatesToTheStoreConfigCopier(): void
    {
        $copierMock = $this->createMock(StoreConfigCopierInterface::class);
        $copierMock->method('hasStoreConfiguration')->with('DE')->willReturn(true);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createStoreConfigCopier'], ['createStoreConfigCopier' => $copierMock]));

        $this->assertTrue($facade->hasStoreConfiguration('DE'));
    }

    public function testPreviewStoreConfigurationSyncDelegatesToTheStoreConfigCopier(): void
    {
        $previewTransfer = new SearchRankingStoreConfigPreviewTransfer();

        $copierMock = $this->createMock(StoreConfigCopierInterface::class);
        $copierMock->method('previewStoreConfiguration')->with('DE', 'de_DE')->willReturn($previewTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createStoreConfigCopier'], ['createStoreConfigCopier' => $copierMock]));

        $this->assertSame($previewTransfer, $facade->previewStoreConfigurationSync('DE', 'de_DE'));
    }

    public function testCopyFullScopeConfigurationDelegatesToTheFullScopeCopier(): void
    {
        $resultTransfer = new SearchRankingFullScopeCopyResultTransfer();

        $copierMock = $this->createMock(FullScopeCopierInterface::class);
        $copierMock->method('copyFullScopeConfiguration')->with('DE', 'de_DE', 'AT', 'de_AT', 'manual', true, 'scope_copy')->willReturn($resultTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createFullScopeCopier'], ['createFullScopeCopier' => $copierMock]));

        $this->assertSame($resultTransfer, $facade->copyFullScopeConfiguration('DE', 'de_DE', 'AT', 'de_AT', 'manual', true));
    }

    public function testHasFullScopeConfigurationDelegatesToTheFullScopeCopier(): void
    {
        $copierMock = $this->createMock(FullScopeCopierInterface::class);
        $copierMock->method('hasFullScopeConfiguration')->with('DE', 'de_DE', 'AT', 'de_AT')->willReturn(true);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createFullScopeCopier'], ['createFullScopeCopier' => $copierMock]));

        $this->assertTrue($facade->hasFullScopeConfiguration('DE', 'de_DE', 'AT', 'de_AT'));
    }

    public function testPreviewFullScopeConfigurationDelegatesToTheFullScopeCopier(): void
    {
        $previewTransfer = new SearchRankingFullScopeCopyPreviewTransfer();

        $copierMock = $this->createMock(FullScopeCopierInterface::class);
        $copierMock->method('previewFullScopeConfiguration')->with('DE', 'de_DE')->willReturn($previewTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createFullScopeCopier'], ['createFullScopeCopier' => $copierMock]));

        $this->assertSame($previewTransfer, $facade->previewFullScopeConfiguration('DE', 'de_DE'));
    }

    public function testGetActiveScopeCopyLocksDelegatesToTheScopeCopyLockManager(): void
    {
        $managerMock = $this->createMock(ScopeCopyLockManagerInterface::class);
        $managerMock->method('getActiveScopeCopyLocks')->willReturn([]);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeCopyLockManager'], ['createScopeCopyLockManager' => $managerMock]));

        $this->assertSame([], $facade->getActiveScopeCopyLocks());
    }

    public function testCreateScopeCopyLockDelegatesToTheScopeCopyLockManager(): void
    {
        $resultTransfer = new SearchRankingFullScopeCopyResultTransfer();

        $managerMock = $this->createMock(ScopeCopyLockManagerInterface::class);
        $managerMock->method('createScopeCopyLock')->with('DE', 'de_DE', 'AT', 'de_AT', true)->willReturn($resultTransfer);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeCopyLockManager'], ['createScopeCopyLockManager' => $managerMock]));

        $this->assertSame($resultTransfer, $facade->createScopeCopyLock('DE', 'de_DE', 'AT', 'de_AT', true));
    }

    public function testDeactivateScopeCopyLockDelegatesToTheScopeCopyLockManager(): void
    {
        $managerMock = $this->createMock(ScopeCopyLockManagerInterface::class);
        $managerMock->expects($this->once())->method('deactivateScopeCopyLock')->with(1);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeCopyLockManager'], ['createScopeCopyLockManager' => $managerMock]));

        $facade->deactivateScopeCopyLock(1);
    }

    public function testRunScopeCopyDailySyncDelegatesToTheScopeCopyLockManager(): void
    {
        $managerMock = $this->createMock(ScopeCopyLockManagerInterface::class);
        $managerMock->method('runDailySync')->willReturn(3);

        $facade = $this->buildFacadeWithFactory($this->createFactoryMock(['createScopeCopyLockManager'], ['createScopeCopyLockManager' => $managerMock]));

        $this->assertSame(3, $facade->runScopeCopyDailySync());
    }

    /**
     * @param array<string> $onlyMethods
     * @param array<string, mixed> $returnMap Method name => the value that factory method should return.
     */
    protected function createFactoryMock(array $onlyMethods, array $returnMap): SearchRankingBusinessFactory
    {
        $factoryMock = $this->getMockBuilder(SearchRankingBusinessFactory::class)
            ->onlyMethods($onlyMethods)
            ->getMock();

        foreach ($returnMap as $methodName => $returnValue) {
            $factoryMock->method($methodName)->willReturn($returnValue);
        }

        return $factoryMock;
    }

    protected function createRepositoryMock(): SearchRankingRepository
    {
        return $this->getMockBuilder(SearchRankingRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function buildFacadeWithFactory(SearchRankingBusinessFactory $factoryMock): SearchRankingFacade
    {
        $facade = new SearchRankingFacade();
        $facade->setFactory($factoryMock);

        return $facade;
    }

    protected function buildFacadeWithRepository(SearchRankingRepository $repositoryMock): SearchRankingFacade
    {
        $facade = new SearchRankingFacade();
        $facade->setRepository($repositoryMock);

        return $facade;
    }
}
