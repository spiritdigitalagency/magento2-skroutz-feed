<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
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
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    protected $attributeList;

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
        CollectionFactory $collectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->taxCalculation = $taxCalculation;
        $this->categoryTree = $categoryTree;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context);
        $this->mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $this->attributeList = $this->collectionFactory->create()->addFieldToSelect('attribute_code')->addFieldToSelect('frontend_label')->addFieldToSelect('frontend_input')->addVisibleFilter()->removePriceFilter()->addFieldToFilter('is_user_defined',
            1);
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
        return $product->getData('sku');
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

    public function getCategoryPath($product)
    {
        if ($product->getCategoryIds() && count($product->getCategoryIds())) {
            $storeId = $this->storeManager->getStore()->getId();
            $category = $this->categoryRepository->get($product->getCategoryIds()[0], $storeId);
            $categoryTree = $this->categoryTree->setStoreId($storeId)->loadBreadcrumbsArray($category->getPath());

            $categoryTreePath = [];
            foreach ($categoryTree as $eachCategory) {
                $categoryTreePath[] = $eachCategory['name'];
            }
            return implode(' > ', $categoryTreePath);
        }
        return '';
    }

    public function getDescription($product)
    {
        if ($product->getCustomAttribute('description')) {
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
        if (0 == $this->getConfig('feed_mappings/availability_mapping')) {
            return $this->getAttributeValue($product, $this->getConfig('feed_mappings/availability_attribute'));
        }
        if (1 == $this->getConfig('feed_mappings/availability_mapping')) {
            return $this->getConfig('feed_mappings/availability_fixed');
        }
        if ($product->isSaleable()) {
            return 'Delivery up to 30 days';
        }
        return 'Delivery 1 to 3 days';
    }

    /**
     * @param  string  $key
     *
     * @return mixed
     */
    protected function getConfig(string $key)
    {
        return $this->scopeConfig->getValue(self::CONFIG_NAMESPACE."/$key", ScopeInterface::SCOPE_STORE);
    }

    protected function getAttributeValue($product, $code)
    {
        if (empty($code)) {
            return null;
        }
        $attribute = null;
        foreach ($this->attributeList as $attribute) {
            if ($code == $attribute->getAttributeCode()) {
                if ($attribute->getFrontendInput() == 'select') {
                    return $product->getAttributeText($code);
                }
                break;
            }
        }
        return $product->getData($code);
    }

    public function getMpn($product)
    {
        return $this->getAttributeValue($product, $this->getConfig('feed_mappings/mpn'));
    }

    public function getEan($product)
    {
        return $this->getAttributeValue($product, $this->getConfig('feed_mappings/ean'));
    }

    public function getColor($product)
    {
        return $this->getAttributeValue($product, $this->getConfig('feed_mappings/color'));
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @return mixed
     */
    public function getSize($product)
    {
        if ($product->getTypeId() == 'configurable') {
            //        if ($product->canConfigure()) {
            $sizes = [];
            //            $product->canConfigure()
            if (!empty($product->getOptions())) {
                var_dump($product->getOptions());
                die;
            }
            $_children = $product->getTypeInstance()->getUsedProducts($product);
            /**
             * @var $_children ProductInterface[]
             */
            foreach ($_children as $child_product) {
                $sizes[] = $this->getAttributeValue($child_product, $this->getConfig('feed_mappings/size'));
            }
            return implode(',', $sizes);
        }
        return $this->getAttributeValue($product, $this->getConfig('feed_mappings/size'));
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @return mixed
     */
    public function getManufacturer($product)
    {
        if (0 == $this->getConfig('feed_mappings/manufacturer_mapping')) {
            return $this->getAttributeValue($product, $this->getConfig('feed_mappings/manufacturer_attribute'));
        }
        return $this->getConfig('feed_mappings/manufacturer_fixed');
    }

    protected function getTreeByCategoryId($categoryId)
    {
        return $categoryTree;
    }
}

