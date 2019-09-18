<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UrlRewrite\Model\StoreSwitcher;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreSwitcher;
use Magento\Framework\ObjectManagerInterface as ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * Test store switching
 */
class RewriteUrlTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var StoreSwitcher
     */
    private $storeSwitcher;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Class dependencies initialization
     *
     * @return void
     */
    protected function setUp()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeSwitcher = $this->objectManager->get(StoreSwitcher::class);
        $this->productRepository = $this->objectManager->create(ProductRepositoryInterface::class);
        $this->storeManager = $this->objectManager->create(StoreManagerInterface::class);
    }

    /**
     * Test switching stores with non-existent cms pages and then redirecting to the homepage
     *
     * @magentoDataFixture Magento/UrlRewrite/_files/url_rewrite.php
     * @magentoDataFixture Magento/Catalog/_files/category_product.php
     * @return void
     * @throws StoreSwitcher\CannotSwitchStoreException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testSwitchToNonExistingPage(): void
    {
        $fromStore = $this->getStoreByCode('default');
        $toStore = $this->getStoreByCode('fixture_second_store');

        $this->setBaseUrl($toStore);

        $product = $this->productRepository->get('simple333');

        $redirectUrl = "http://domain.com/{$product->getUrlKey()}.html";
        $expectedUrl = $toStore->getBaseUrl();

        $this->assertEquals($expectedUrl, $this->storeSwitcher->switch($fromStore, $toStore, $redirectUrl));
    }

    /**
     * Testing store switching with existing cms pages
     *
     * @magentoDataFixture Magento/UrlRewrite/_files/url_rewrite.php
     * @return void
     * @throws StoreSwitcher\CannotSwitchStoreException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testSwitchToExistingPage(): void
    {
        $fromStore = $this->getStoreByCode('default');
        $toStore = $this->getStoreByCode('fixture_second_store');

        $redirectUrl = "http://localhost/index.php/page-c/";
        $expectedUrl = "http://localhost/index.php/page-c-on-2nd-store";

        $this->assertEquals($expectedUrl, $this->storeSwitcher->switch($fromStore, $toStore, $redirectUrl));
    }

    /**
     * Testing store switching using cms pages with the same url_key but with different page_id
     *
     * @magentoDataFixture Magento/Cms/_files/two_cms_page_with_same_url_for_different_stores.php
     * @magentoDbIsolation disabled
     * @return void
     */
    public function testSwitchCmsPageToAnotherStore(): void
    {
        $fromStore = $this->getStoreByCode('default');
        $toStore = $this->getStoreByCode('fixture_second_store');
        $redirectUrl = "http://localhost/index.php/page100/";
        $expectedUrl = "http://localhost/index.php/page100/";
        $this->assertEquals($expectedUrl, $this->storeSwitcher->switch($fromStore, $toStore, $redirectUrl));
    }

    /**
     * Set base url to store.
     *
     * @param StoreInterface $targetStore
     * @return void
     */
    private function setBaseUrl(StoreInterface $targetStore): void
    {
        $configValue = $this->objectManager->create(Value::class);
        $configValue->load('web/unsecure/base_url', 'path');
        $baseUrl = 'http://domain.com/';
        if (!$configValue->getPath()) {
            $configValue->setPath('web/unsecure/base_url');
        }
        $configValue->setValue($baseUrl);
        $configValue->setScope(ScopeInterface::SCOPE_STORES);
        $configValue->setScopeId($targetStore->getId());
        $configValue->save();

        $reinitibleConfig = $this->objectManager->create(ReinitableConfigInterface::class);
        $reinitibleConfig->reinit();
    }

    /**
     * Get store object by storeCode
     *
     * @param string $storeCode
     * @return StoreInterface
     */
    private function getStoreByCode(string $storeCode): StoreInterface
    {
        /** @var StoreRepositoryInterface */
        return $this->storeManager->getStore($storeCode);
    }
}
