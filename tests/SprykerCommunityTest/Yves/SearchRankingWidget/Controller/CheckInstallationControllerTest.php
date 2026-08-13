<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchRankingWidget\Controller;

use Codeception\Test\Unit;
use ReflectionMethod;
use RuntimeException;
use Spryker\Yves\Kernel\View\View;
use SprykerCommunity\Shared\SearchRanking\Plugin\SeeSearchRankingRandomImpactPermissionPlugin;
use SprykerCommunity\Shared\SearchRanking\SearchRankingConfig as SharedSearchRankingConfig;
use SprykerCommunity\Yves\SearchRankingWidget\Controller\CheckInstallationController;
use SprykerCommunity\Yves\SearchRankingWidget\Dependency\Client\SearchRankingWidgetToCatalogClientInterface;
use SprykerCommunity\Yves\SearchRankingWidget\SearchRankingWidgetFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Most of this controller's checks are pure functions over one already-fetched catalog search result, so
 * they are exercised directly rather than against a booted application; only the probe itself and the
 * two environment checks need a stand-in collaborator. Mirrors the sibling spryker-community/search-debug
 * and spryker-community/search-feedback packages' identical tests for their own CheckInstallationControllers.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchRankingWidget
 * @group Controller
 * @group CheckInstallationControllerTest
 * Add your own group annotations below this line
 * @group Portable
 */
class CheckInstallationControllerTest extends Unit
{
    public function testIndexActionReturnsAForbiddenResponseWhenThePermissionIsMissing(): void
    {
        // Arrange
        $controller = $this->getMockBuilder(CheckInstallationController::class)
            ->onlyMethods(['can', 'renderView'])
            ->getMock();
        $controller->method('can')->with(SeeSearchRankingRandomImpactPermissionPlugin::KEY)->willReturn(false);
        $controller->expects($this->once())
            ->method('renderView')
            ->with(
                '@SearchRankingWidget/views/check-installation/permission-denied.twig',
                [],
                $this->callback(fn (Response $response): bool => $response->getStatusCode() === Response::HTTP_FORBIDDEN),
            )
            ->willReturn(new Response('', Response::HTTP_FORBIDDEN));

        // Act
        $result = $controller->indexAction();

        // Assert
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(Response::HTTP_FORBIDDEN, $result->getStatusCode());
    }

    public function testIndexActionReturnsTheViewWithChecksWhenPermitted(): void
    {
        // Arrange
        $checks = [['label' => 'a check', 'passed' => true, 'remedy' => null]];
        $controller = $this->getMockBuilder(CheckInstallationController::class)
            ->onlyMethods(['can', 'runChecks'])
            ->getMock();
        $controller->method('can')->with(SeeSearchRankingRandomImpactPermissionPlugin::KEY)->willReturn(true);
        $controller->method('runChecks')->willReturn($checks);

        // Act
        $result = $controller->indexAction();

        // Assert
        $this->assertInstanceOf(View::class, $result);
        $this->assertSame(['checks' => $checks], $result->getData());
        $this->assertSame('@SearchRankingWidget/views/check-installation/check-installation.twig', $result->getTemplate());
    }

    public function testRunChecksReturnsAllFourChecksInOrder(): void
    {
        // Arrange
        $controller = $this->getMockBuilder(CheckInstallationController::class)
            ->onlyMethods(['probeRandomImpactResult', 'checkGlossary', 'checkFrontendAssets'])
            ->getMock();
        $controller->method('probeRandomImpactResult')->willReturn([SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => true]);
        $controller->method('checkGlossary')->willReturn(['label' => 'glossary', 'passed' => true, 'remedy' => null]);
        $controller->method('checkFrontendAssets')->willReturn(['label' => 'assets', 'passed' => true, 'remedy' => null]);

        // Act
        $checks = $this->invoke($controller, 'runChecks');

        // Assert
        $this->assertCount(4, $checks);
        $this->assertStringContainsString('RandomImpactResultFormatterPlugin', $checks[0]['label']);
        $this->assertStringContainsString('random tie-breaker metric', $checks[1]['label']);
        $this->assertSame('glossary', $checks[2]['label']);
        $this->assertSame('assets', $checks[3]['label']);
    }

    public function testCheckResultFormatterFailsWhenTheSearchResultCarriesNoRandomImpactKey(): void
    {
        // Act
        $check = $this->invoke($this->createController(), 'checkResultFormatter', null);

        // Assert
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('CatalogDependencyProvider', (string)$check['remedy']);
    }

    public function testCheckResultFormatterPassesOnAnEmptyPayloadBecauseThePluginStillRan(): void
    {
        // Act — an empty payload is the formatter's own documented "nothing to show" answer, which still
        // proves it is registered; only a missing key means it never ran.
        $check = $this->invoke($this->createController(), 'checkResultFormatter', []);

        // Assert
        $this->assertTrue($check['passed']);
        $this->assertNull($check['remedy']);
    }

    public function testCheckRandomImpactIsActiveFailsWhenThePayloadIsMissingOrInactive(): void
    {
        // Act
        $missing = $this->invoke($this->createController(), 'checkRandomImpactIsActive', null);
        $inactive = $this->invoke($this->createController(), 'checkRandomImpactIsActive', [SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => false]);

        // Assert
        $this->assertFalse($missing['passed']);
        $this->assertFalse($inactive['passed']);
        $this->assertStringContainsString('synchronized', (string)$inactive['remedy']);
    }

    public function testCheckRandomImpactIsActivePassesOnlyForABooleanTrue(): void
    {
        // Act — a truthy-but-not-true value (the formatter always writes a real bool) must not pass.
        $active = $this->invoke($this->createController(), 'checkRandomImpactIsActive', [SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => true]);
        $truthy = $this->invoke($this->createController(), 'checkRandomImpactIsActive', [SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => '1']);

        // Assert
        $this->assertTrue($active['passed']);
        $this->assertFalse($truthy['passed']);
    }

    public function testProbeRandomImpactResultReturnsNullWhenTheCatalogSearchThrows(): void
    {
        // Arrange — an unreachable search engine must degrade to a reported failure, never a 500 on a
        // page whose entire purpose is diagnosing a broken installation.
        $catalogClientMock = $this->createMock(SearchRankingWidgetToCatalogClientInterface::class);
        $catalogClientMock->method('catalogSearch')->willThrowException(new RuntimeException('engine down'));

        // Act
        $result = $this->invoke($this->createController($catalogClientMock), 'probeRandomImpactResult');

        // Assert
        $this->assertNull($result);
    }

    public function testProbeRandomImpactResultReturnsNullWhenTheKeyIsAbsentAndThePayloadWhenPresent(): void
    {
        // Arrange
        $payload = [SharedSearchRankingConfig::RANDOM_IMPACT_KEY_IS_ACTIVE => true];
        $withoutKeyMock = $this->createMock(SearchRankingWidgetToCatalogClientInterface::class);
        $withoutKeyMock->method('catalogSearch')->willReturn(['someOtherFormatter' => []]);
        $withKeyMock = $this->createMock(SearchRankingWidgetToCatalogClientInterface::class);
        $withKeyMock->method('catalogSearch')->willReturn([SharedSearchRankingConfig::RANDOM_IMPACT_RESULT_KEY => $payload]);

        // Act & Assert
        $this->assertNull($this->invoke($this->createController($withoutKeyMock), 'probeRandomImpactResult'));
        $this->assertSame($payload, $this->invoke($this->createController($withKeyMock), 'probeRandomImpactResult'));
    }

    public function testCheckGlossaryFailsWhenTheKeyResolvesToItself(): void
    {
        // Arrange — Spryker's translator returns the key itself for a missing translation, which is
        // exactly the silent failure this check exists for.
        $controller = $this->getMockBuilder(CheckInstallationController::class)->onlyMethods(['translate'])->getMock();
        $controller->method('translate')->willReturnArgument(0);

        // Act
        $check = $this->invoke($controller, 'checkGlossary');

        // Assert
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('data:import glossary', (string)$check['remedy']);
    }

    public function testCheckGlossaryPassesWhenTheKeyResolvesToRealText(): void
    {
        // Arrange
        $controller = $this->getMockBuilder(CheckInstallationController::class)->onlyMethods(['translate'])->getMock();
        $controller->method('translate')->willReturn('Show random impact');

        // Act
        $check = $this->invoke($controller, 'checkGlossary');

        // Assert
        $this->assertTrue($check['passed']);
        $this->assertNull($check['remedy']);
    }

    public function testCheckFrontendAssetsDistinguishesNoBundleFoundFromABundleWithoutThisPackage(): void
    {
        // Arrange
        $noBundle = $this->getMockBuilder(CheckInstallationController::class)->onlyMethods(['isAssetProbePresentInBuiltCss'])->getMock();
        $noBundle->method('isAssetProbePresentInBuiltCss')->willReturn(null);
        $notBundled = $this->getMockBuilder(CheckInstallationController::class)->onlyMethods(['isAssetProbePresentInBuiltCss'])->getMock();
        $notBundled->method('isAssetProbePresentInBuiltCss')->willReturn(false);
        $bundled = $this->getMockBuilder(CheckInstallationController::class)->onlyMethods(['isAssetProbePresentInBuiltCss'])->getMock();
        $bundled->method('isAssetProbePresentInBuiltCss')->willReturn(true);

        // Act
        $noBundleCheck = $this->invoke($noBundle, 'checkFrontendAssets');
        $notBundledCheck = $this->invoke($notBundled, 'checkFrontendAssets');
        $bundledCheck = $this->invoke($bundled, 'checkFrontendAssets');

        // Assert — both failures are real, but they need different remedies.
        $this->assertFalse($noBundleCheck['passed']);
        $this->assertStringContainsString('never been built', (string)$noBundleCheck['remedy']);
        $this->assertFalse($notBundledCheck['passed']);
        $this->assertStringContainsString('random-impact-badge', (string)$notBundledCheck['remedy']);
        $this->assertTrue($bundledCheck['passed']);
        $this->assertNull($bundledCheck['remedy']);
    }

    /**
     * @param \SprykerCommunity\Yves\SearchRankingWidget\Dependency\Client\SearchRankingWidgetToCatalogClientInterface|null $catalogClient
     */
    protected function createController(?SearchRankingWidgetToCatalogClientInterface $catalogClient = null): CheckInstallationController
    {
        $factoryMock = $this->createMock(SearchRankingWidgetFactory::class);
        $factoryMock->method('getCatalogClient')->willReturn(
            $catalogClient ?? $this->createMock(SearchRankingWidgetToCatalogClientInterface::class),
        );

        $controller = $this->getMockBuilder(CheckInstallationController::class)->onlyMethods(['getFactory'])->getMock();
        $controller->method('getFactory')->willReturn($factoryMock);

        return $controller;
    }

    /**
     * @param \SprykerCommunity\Yves\SearchRankingWidget\Controller\CheckInstallationController $controller
     * @param string $methodName
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    protected function invoke(CheckInstallationController $controller, string $methodName, ...$arguments)
    {
        $method = new ReflectionMethod(CheckInstallationController::class, $methodName);

        return $method->invoke($controller, ...$arguments);
    }
}
