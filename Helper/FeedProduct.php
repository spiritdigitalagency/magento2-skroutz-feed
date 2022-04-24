<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class FeedProduct extends AbstractHelper
{
    const CONFIG_NAMESPACE = 'spirit_skroutz';

    /**
     * @var \Magento\Tax\Model\Calculation
     */
    protected $taxCalculation;

    /**
     * @var \Magento\Catalog\Api\CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\Tree
     */
    protected $categoryTree;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var string
     */
    protected $mediaUrl;

    /**
     * FeedProduct constructor.
     * @param  \Magento\Framework\App\Helper\Context  $context
     * @param  \Magento\Tax\Model\Calculation  $taxCalculation
     * @param  \Magento\Catalog\Api\CategoryRepositoryInterface  $categoryRepository
     * @param  \Magento\Catalog\Model\ResourceModel\Category\Tree  $categoryTree
     * @param  \Magento\Store\Model\StoreManagerInterface  $storeManager
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Tax\Model\Calculation $taxCalculation,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\Tree $categoryTree,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->taxCalculation = $taxCalculation;
        $this->categoryTree = $categoryTree;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        parent::__construct($context);
        $this->mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA );
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function getConfig(string $key)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_NAMESPACE . "/$key",
            ScopeInterface::SCOPE_STORE
        );
    }

    protected function getTreeByCategoryId($categoryId)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $category = $this->categoryRepository->get($categoryId, $storeId);
        $categoryTree = $this->categoryTree->setStoreId($storeId)->loadBreadcrumbsArray($category->getPath());

        $categoryTreePath = [];
        foreach($categoryTree as $eachCategory){
            $categoryTreePath[] = $eachCategory['name'];
        }
        $categoryTree = implode(' > ',$categoryTreePath);
        return $categoryTree;
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @return mixed
     */
    public function getName($product)
    {
        return $product->getName();
    }

    public function getId($product)
    {
        return $product->getId();
    }

    public function getLink($product)
    {
        return $product->getProductUrl();
    }

    public function getPriceWithVat($product)
    {
        return $product->getFinalPrice();
    }

    public function getVat($product)
    {
        $request = $this->taxCalculation->getRateRequest(null, null, null, $this->storeManager->getStore());
        return $this->taxCalculation->getRate($request->setProductClassId($product->getTaxClassId()));
    }

    public function getWeight($product)
    {
        return $product->getWeight();
    }

    public function getDescription($product)
    {
        if ($product->getCustomAttribute('description')){
            return $product->getCustomAttribute('description')->getValue();
        }
        return null;
    }

    public function getInStock($product)
    {
        return $product->isSalable() ? 'Y' : 'N';
    }

    public function getQuantity($product)
    {
        $stockItem = $product->getExtensionAttributes()->getStockItem();
        if (!empty($stockItem)) {
            return $stockItem->getQty();
        }
        return null;
    }

    public function getImage($product)
    {
        if ($product->getData('image')) {
            return $this->mediaUrl.'catalog/product'.$product->getData('image');
        }
        return null;
    }

    public function getAdditionalImages($product)
    {
        $images = [];
        foreach ($product->getMediaGalleryImages() as $image) {
            $images[$image->getId()] = $image->getUrl();
        }
        return $images;
    }

    public function getAvailability($product)
    {
        return $product->getData($this->getConfig('feed_mappings/availability'));
    }

    public function getMpn($product)
    {
        return $product->getData($this->getConfig('feed_mappings/mpn'));
    }

    public function getEan($product)
    {
        return $product->getData($this->getConfig('feed_mappings/ean'));
    }

    public function getManufacturer($product)
    {
        return $product->getData($this->getConfig('feed_mappings/manufacturer'));
    }
}

