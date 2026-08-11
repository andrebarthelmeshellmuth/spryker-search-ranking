<?php

/**
 * This file is part of the spryker-community/search-ranking package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchRankingGuiPresentation;

use Codeception\Actor;
use Exception;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\MetricFormPage;
use SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PageObject\MetricListPage;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(\SprykerCommunityTest\Zed\SearchRankingGuiPresentation\PHPMD)
 */
class SearchRankingGuiPresentationTester extends Actor
{
    use _generated\SearchRankingGuiPresentationTesterActions;

    /**
     * @var string
     */
    public const DEFAULT_STORE_NAME = 'DE';

    /**
     * @var string
     */
    public const DEFAULT_LOCALE_NAME = 'de_DE';

    /**
     * @param string $selector
     */
    public function tryToSeeElement(string $selector): bool
    {
        try {
            $this->seeElement($selector);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @param string $storeName
     * @param string $localeName
     */
    public function scopedListUrl(string $storeName = self::DEFAULT_STORE_NAME, string $localeName = self::DEFAULT_LOCALE_NAME): string
    {
        return sprintf('%s?storeName=%s&localeName=%s', MetricListPage::URL, $storeName, $localeName);
    }

    /**
     * Creates a metric through the real Create form and returns the numeric id the app assigned it,
     * read back off the redirect URL - not guessed.
     *
     * @param string $name
     * @param float $weight
     * @param string $formula
     */
    public function createMetric(string $name, float $weight, string $formula): int
    {
        $this->amOnPage($this->scopedListUrl());
        $this->click(MetricListPage::CREATE_METRIC_LINK_TEXT);

        $this->fillField('#' . MetricFormPage::FIELD_NAME, $name);
        $this->fillField('#' . MetricFormPage::FIELD_WEIGHT, (string)$weight);
        $this->fillField('#' . MetricFormPage::FIELD_FORMULA, $formula);
        $this->click(MetricFormPage::SELECTOR_SUBMIT);

        $this->seeInCurrentUrl(MetricListPage::URL);
        $this->see(sprintf(MetricFormPage::FLASH_MESSAGE_CREATED_FORMAT, $name));

        // Create redirects to the plain list without an id in the URL, unlike Edit - the id has to be
        // read off the new row's own Edit link instead.
        return $this->grabIdFromEditLinkByName($name);
    }

    /**
     * @param string $name
     */
    protected function grabIdFromEditLinkByName(string $name): int
    {
        $editLinkXpath = "//td[contains(., '" . $name . "')]/ancestor::tr//a[contains(@class, 'btn-edit')]";

        $this->waitForElementVisible(MetricListPage::SELECTOR_TABLE, 10);
        $this->waitForElementVisible($editLinkXpath, 10);

        $href = $this->grabAttributeFrom($editLinkXpath, 'href');
        preg_match('/id-search-ranking-metric=(\d+)/', $href, $matches);

        return (int)($matches[1] ?? 0);
    }

    /**
     * @param int $idSearchRankingMetric
     */
    public function deleteMetric(int $idSearchRankingMetric): void
    {
        $this->amOnPage($this->scopedListUrl());
        $this->waitForElementVisible(MetricListPage::SELECTOR_TABLE, 10);
        $deleteButtonXpath = "//tr[.//a[contains(@href, 'id-search-ranking-metric=" . $idSearchRankingMetric . "')]]//button[@data-qa='delete-button']";
        $this->waitForElementVisible($deleteButtonXpath, 10);
        $this->click($deleteButtonXpath);
        $this->see('Metric was deleted.');
    }
}
