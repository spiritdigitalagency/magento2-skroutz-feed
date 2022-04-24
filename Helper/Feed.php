<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Spatie\ArrayToXml\ArrayToXml;

class Feed extends AbstractHelper
{
    const CONFIG_NAMESPACE = 'spirit_skroutz';

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $file;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;


    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $dateTime;

    /**
     * @var \Magento\Framework\Convert\ConvertArray
     */
    protected $convertArray;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var FeedProduct
     */
    protected $feedProductHelper;

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
     * Feed constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $dateTime
     * @param \Magento\Framework\Convert\ConvertArray $convertArray
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Tax\Model\Calculation $taxCalculation
     * @param \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository
     * @param \Magento\Catalog\Model\ResourceModel\Category\Tree $categoryTree
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $dateTime,
        \Magento\Framework\Convert\ConvertArray $convertArray,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Spirit\SkroutzFeed\Helper\FeedProduct $feedProduct,
        \Magento\Tax\Model\Calculation $taxCalculation,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\Tree $categoryTree,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->file = $file;
        $this->filesystem = $filesystem;
        $this->dateTime = $dateTime;
        $this->convertArray = $convertArray;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->feedProductHelper = $feedProduct;
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
    public function getConfig(string $key)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_NAMESPACE . "/$key",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getConfig('feed_settings/status');
    }

    public function getTreeByCategoryId($categoryId)
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


    protected function getTaxPercentage($product){
        $request = $this->taxCalculation->getRateRequest(null, null, null, $this->storeManager->getStore());
        return $this->taxCalculation->getRate($request->setProductClassId($product->getTaxClassId()));
    }

    /**
     * filter enabled only
     * filter saleable only
     * filter website / store
     */
    public function generate(){
        $collection = $this->productCollectionFactory->create();
//        $collection->addWebsiteFilter($websiteId);
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('status', 1);
        $feed = [
            'created_at' => $this->dateTime->date()->format('Y-m-d H:i:s'),
            'products' => []
        ];
        $i = 1;
        /**
         * @var $product \Magento\Catalog\Model\Product
         */
        foreach ($collection as $product) {
            $feed['products']['product'][] = $this->getSkroutzProduct($product);
            if ($i++>3){
                break;
            }
        }
        $xmlFeed = ArrayToXml::convert($feed, 'feed', true, 'UTF-8');
        var_dump($xmlFeed);
        $feedDirectory = $this->filesystem->getDirectoryRead(DirectoryList::PUB)->getAbsolutePath('feeds');
        if (!$this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->isDirectory($feedDirectory)) {
            $this->file->mkdir($feedDirectory, 0775);
        }
        $this->file->write( "$feedDirectory/skroutz.xml", $xmlFeed);
    }

    protected function getSkroutzProduct($product){
        $entry = [
            'id' => $this->feedProductHelper->getId($product),
            'name' => ['_cdata' => $this->feedProductHelper->getName($product)],
            'link' => ['_cdata' => $this->feedProductHelper->getLink($product)],
            'image' => ['_cdata' => $this->feedProductHelper->getImage($product)],
            'price_with_vat' => $this->feedProductHelper->getPriceWithVat($product),
            'vat' => $this->feedProductHelper->getVat($product),
            'availability' => ['_cdata' => $this->feedProductHelper->getAvailability($product)],
            'manufacturer' => ['_cdata' => $this->feedProductHelper->getManufacturer($product)],
            'mpn' => $this->feedProductHelper->getMpn($product),
            'instock' => $this->feedProductHelper->getInStock($product),
            'weight' => $this->feedProductHelper->getWeight($product)
        ];
        foreach ($this->feedProductHelper->getAdditionalImages($product) as $id => $image) {
            $entry['__custom:additionalimage:'.$id]['_cdata'] = $image;
        }
        if ($product->getCategoryIds() && count($product->getCategoryIds())){
            $entry['category']['_cdata'] = $this->getTreeByCategoryId($product->getCategoryIds()[0]);
        }
        if ($description = $this->feedProductHelper->getDescription($product)){
            $entry['description']['_cdata'] = $description;
        }
        if ($qty = $this->feedProductHelper->getQuantity($product)){
            $entry['quantity'] = $qty;
        }
        return $entry;
    }
}

