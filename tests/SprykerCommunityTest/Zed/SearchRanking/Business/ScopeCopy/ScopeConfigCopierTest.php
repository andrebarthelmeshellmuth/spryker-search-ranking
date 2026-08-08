<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRanking\Business\ScopeCopy;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchRankingMetricCollectionTransfer;
use Generated\Shared\Transfer\SearchRankingMetricTransfer;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Zed\SearchRanking\Business\Metric\MetricWriterInterface;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopier;
use SprykerCommunity\Zed\SearchRanking\Business\ScopeCopy\ScopeConfigCopierInterface;
use SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface;
use SprykerCommunity\Zed\SearchRanking\Persistence\SearchRankingRepositoryInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchRanking
 * @group Business
 * @group ScopeCopy
 * @group ScopeConfigCopierTest
 * Add your own group annotations below this line
 */
class ScopeConfigCopierTest extends Unit
{
    /**
     * Every {@see \SprykerCommunity\Zed\SearchRanking\Business\Setting\SettingManagerInterface} save*
     * method that has a matching SETTING_KEY_* constant is expected to be copyable — this is the direct
     * regression test for a real bug found in review: `specificityCurveExponent` was added as a Zed
     * setting but never added to `ScopeConfigCopier::SETTING_COPIERS`, so Scope Copy silently left a
     * customized value behind on the source scope instead of copying it to the target. Enumerating every
     * setter here means a FUTURE setting addition that repeats the same mistake fails this test instead
     * of failing silently in production.
     *
     * @var array<string, string>
     */
    protected const EXPECTED_SETTING_COPIERS = [
        SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT => 'saveRelevanceWeight',
        SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT => 'saveRelevanceSaturationPoint',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT => 'saveSpecificityBlendWeight',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT => 'saveSpecificitySaturationPoint',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_CURVE_EXPONENT => 'saveSpecificityCurveExponent',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT => 'saveSpecificityWeightExponent',
        SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE => 'saveSpecificityWeightShiftMagnitude',
    ];

    public function testFailsWhenSourceAndTargetScopeAreTheSame(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->expects($this->never())->method('saveMetricWeight');
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'DE',
            'de_DE',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertFalse($resultTransfer->getIsSuccess());
        $this->assertNotNull($resultTransfer->getErrorMessage());
    }

    public function testIsBlockedByExistingDataWhenTargetAlreadyHasConfigurationAndOverwriteNotConfirmed(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->with('AT', 'de_AT')->willReturn(true);
        $repositoryMock->expects($this->never())->method('getMetricCollection');

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsBlockedByExistingData());
    }

    /**
     * The direct-target check alone would say "not blocked" (AT/fr_AT itself has no saved config), but a
     * not-locale-scoped metric being copied would fan out onto AT/de_AT too — which already has its own
     * weight for that metric. Regression test for the real bug: the guard used to only see the ONE
     * selected target scope and missed this sibling-locale collision entirely.
     */
    public function testIsBlockedWhenANotLocaleScopedMetricWouldFanOutOntoASiblingLocaleThatAlreadyHasAWeight(): void
    {
        // Arrange
        $storeOnlyMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('units_sold')->setIsLocaleScoped(false);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->with('AT', 'fr_AT')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->with('DE')->willReturn(
            (new SearchRankingMetricCollectionTransfer())->addMetric($storeOnlyMetric),
        );
        $repositoryMock->method('findMetricWeight')->willReturnMap([
            [1, 'DE', 'de_DE', 0.9],
            [1, 'AT', 'de_AT', 0.4],
        ]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->method('resolveEffectiveWeightLocales')->with(1, 'AT', 'fr_AT')->willReturn(['de_AT', 'fr_AT']);
        $metricWriterMock->expects($this->never())->method('saveMetricWeight');

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'fr_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsBlockedByExistingData());
    }

    /**
     * Same setup as the sibling-collision test above, but the metric is locale-scoped — resolveEffective-
     * WeightLocales() returns only the direct target, so there is no sibling to collide with and the copy
     * proceeds. Proves the new guard doesn't over-block locale-scoped metrics.
     */
    public function testIsNotBlockedWhenTheMetricIsLocaleScopedEvenIfASiblingLocaleHasAWeight(): void
    {
        // Arrange
        $localeScopedMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('top_seller')->setIsLocaleScoped(true);

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->with('AT', 'fr_AT')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->with('DE')->willReturn(
            (new SearchRankingMetricCollectionTransfer())->addMetric($localeScopedMetric),
        );
        $repositoryMock->method('findMetricWeight')->willReturnMap([
            [1, 'DE', 'de_DE', 0.9],
            [1, 'AT', 'de_AT', 0.4],
        ]);
        $repositoryMock->method('findSettingValue')->willReturn(null);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->method('resolveEffectiveWeightLocales')->with(1, 'AT', 'fr_AT')->willReturn(['fr_AT']);
        $metricWriterMock->expects($this->once())->method('saveMetricWeight')->with(1, 'AT', 'fr_AT', 0.9, SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY);

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'fr_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsSuccess());
        $this->assertNotTrue($resultTransfer->getIsBlockedByExistingData());
    }

    /**
     * Only a metric with an explicitly-saved weight in the SOURCE scope is copied — one the source itself
     * never touched must stay untouched in the target too, rather than writing an explicit 0.0.
     */
    public function testCopiesOnlyMetricWeightsExplicitlySavedInTheSourceScope(): void
    {
        // Arrange
        $configuredMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('top_seller');
        $neverWeightedMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(2)->setName('pdp_impressions');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->with('DE')->willReturn(
            (new SearchRankingMetricCollectionTransfer())
                ->addMetric($configuredMetric)
                ->addMetric($neverWeightedMetric),
        );
        $repositoryMock->method('findMetricWeight')->willReturnMap([
            [1, 'DE', 'de_DE', 0.8],
            [2, 'DE', 'de_DE', null],
        ]);
        $repositoryMock->method('findSettingValue')->willReturn(null);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        // Locale-scoped (the default) — the guard's own resolveEffectiveWeightLocales() lookup for the
        // blast-radius check returns just the direct target, same as the real write itself would.
        $metricWriterMock->method('resolveEffectiveWeightLocales')->willReturn(['de_AT']);
        $metricWriterMock->expects($this->once())
            ->method('saveMetricWeight')
            ->with(1, 'AT', 'de_AT', 0.8, SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY);

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsSuccess());
        $this->assertSame(1, $resultTransfer->getMetricWeightCopiedCount());
    }

    /**
     * Every setting this class claims to support (the same list `SettingsForm` exposes as Zed-editable)
     * must actually reach the setting manager when the source scope has an explicitly saved value — proof
     * that `SETTING_COPIERS` hasn't silently fallen behind the real list of settings again.
     */
    public function testCopiesEverySupportedSettingWhenExplicitlySavedInTheSourceScope(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->willReturn(new SearchRankingMetricCollectionTransfer());
        $repositoryMock->method('findSettingValue')->willReturn('1.8');

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        foreach (static::EXPECTED_SETTING_COPIERS as $setterMethodName) {
            $settingManagerMock->expects($this->once())->method($setterMethodName)->with('AT', 'de_AT', 1.8);
        }

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertTrue($resultTransfer->getIsSuccess());
        $this->assertSame(count(static::EXPECTED_SETTING_COPIERS), $resultTransfer->getSettingCopiedCount());
    }

    /**
     * A setting never explicitly saved in the source scope (findSettingValue returns null) must be left
     * alone in the target, same "absence means untouched" convention metric weights use.
     */
    public function testSkipsASettingNeverExplicitlySavedInTheSourceScope(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->willReturn(new SearchRankingMetricCollectionTransfer());
        $repositoryMock->method('findSettingValue')->willReturn(null);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        foreach (static::EXPECTED_SETTING_COPIERS as $setterMethodName) {
            $settingManagerMock->expects($this->never())->method($setterMethodName);
        }

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_MIRROR,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertSame(0, $resultTransfer->getSettingCopiedCount());
    }

    /**
     * The real regression this covers: MODE_COPY_ONLY_OVERLAP must leave a weight/setting alone that the
     * TARGET has never independently adopted, rather than creating a new row for it the way MODE_MIRROR
     * would — counted in skippedCount, not an error. Same semantics as
     * {@see \SprykerCommunityTest\Zed\SearchRanking\Business\ScopeCopy\StoreConfigCopierTest::testCopyOnlyOverlapModeSkipsAMetricTheTargetHasNeverAdopted()}.
     */
    public function testCopyOnlyOverlapModeSkipsAWeightAndSettingTheTargetHasNeverAdopted(): void
    {
        // Arrange
        $notYetAdoptedByTarget = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('top_seller');
        $alreadyAdoptedByTarget = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(2)->setName('pdp_impressions');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('hasScopeConfiguration')->willReturn(false);
        $repositoryMock->method('getMetricCollection')->with('DE', 'de_DE')->willReturn(
            (new SearchRankingMetricCollectionTransfer())
                ->addMetric($notYetAdoptedByTarget)
                ->addMetric($alreadyAdoptedByTarget),
        );
        $repositoryMock->method('findMetricWeight')->willReturnMap([
            [1, 'DE', 'de_DE', 0.8],
            [1, 'AT', 'de_AT', null],
            [2, 'DE', 'de_DE', 0.5],
            [2, 'AT', 'de_AT', 0.3],
        ]);
        $repositoryMock->method('findSettingValue')->willReturnMap([
            [SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT, 'DE', 'de_DE', '0.6'],
            [SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT, 'AT', 'de_AT', null],
            [SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_CURVE_EXPONENT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE, 'DE', 'de_DE', null],
        ]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $metricWriterMock->method('resolveEffectiveWeightLocales')->willReturn(['de_AT']);
        $metricWriterMock->expects($this->once())
            ->method('saveMetricWeight')
            ->with(2, 'AT', 'de_AT', 0.5, SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY);

        $settingManagerMock = $this->createMock(SettingManagerInterface::class);
        foreach (static::EXPECTED_SETTING_COPIERS as $setterMethodName) {
            $settingManagerMock->expects($this->never())->method($setterMethodName);
        }

        // Act
        $resultTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->copyScopeConfiguration(
            'DE',
            'de_DE',
            'AT',
            'de_AT',
            ScopeConfigCopierInterface::MODE_COPY_ONLY_OVERLAP,
            false,
            SharedSearchRankingConfig::CHANGE_SOURCE_SCOPE_COPY,
        );

        // Assert
        $this->assertSame(1, $resultTransfer->getMetricWeightCopiedCount());
        $this->assertSame(0, $resultTransfer->getSettingCopiedCount());
        $this->assertSame(2, $resultTransfer->getSkippedCount());
    }

    public function testHasScopeConfigurationDelegatesToTheRepository(): void
    {
        // Arrange
        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('hasScopeConfiguration')->with('AT', 'de_AT')->willReturn(true);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $result = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))->hasScopeConfiguration('AT', 'de_AT');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * The preview must mirror {@see ScopeConfigCopier::copyScopeConfiguration()}'s own selection exactly:
     * only explicitly-saved metric weights/settings, keyed by name/human label, never a resolved/defaulted
     * value.
     */
    public function testPreviewReturnsOnlyExplicitlySavedMetricWeightsAndSettingsKeyedForDisplay(): void
    {
        // Arrange
        $configuredMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(1)->setName('top_seller');
        $neverWeightedMetric = (new SearchRankingMetricTransfer())->setIdSearchRankingMetric(2)->setName('pdp_impressions');

        $repositoryMock = $this->createMock(SearchRankingRepositoryInterface::class);
        $repositoryMock->method('getMetricCollection')->with('DE')->willReturn(
            (new SearchRankingMetricCollectionTransfer())
                ->addMetric($configuredMetric)
                ->addMetric($neverWeightedMetric),
        );
        $repositoryMock->method('findMetricWeight')->willReturnMap([
            [1, 'DE', 'de_DE', 0.8],
            [2, 'DE', 'de_DE', null],
        ]);
        $repositoryMock->method('findSettingValue')->willReturnMap([
            [SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_WEIGHT, 'DE', 'de_DE', '0.6'],
            [SharedSearchRankingConfig::SETTING_KEY_RELEVANCE_SATURATION_POINT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_BLEND_WEIGHT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_SATURATION_POINT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_CURVE_EXPONENT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_EXPONENT, 'DE', 'de_DE', null],
            [SharedSearchRankingConfig::SETTING_KEY_SPECIFICITY_WEIGHT_SHIFT_MAGNITUDE, 'DE', 'de_DE', null],
        ]);

        $metricWriterMock = $this->createMock(MetricWriterInterface::class);
        $settingManagerMock = $this->createMock(SettingManagerInterface::class);

        // Act
        $previewTransfer = (new ScopeConfigCopier($repositoryMock, $metricWriterMock, $settingManagerMock))
            ->previewScopeConfiguration('DE', 'de_DE');

        // Assert
        $this->assertSame(['top_seller' => 0.8], $previewTransfer->getMetricWeights());
        $this->assertSame(['Relevance weight' => 0.6], $previewTransfer->getSettings());
    }
}
