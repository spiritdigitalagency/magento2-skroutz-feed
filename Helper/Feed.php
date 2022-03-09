<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Spatie\ArrayToXml\ArrayToXml;

class Feed extends AbstractHelper
{
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
        $this->taxCalculation = $taxCalculation;
        $this->categoryTree = $categoryTree;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        parent::__construct($context);
        $this->mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA );
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    public function getTreeByCategoryId($categoryId)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $category = $this->categoryRepository->get($categoryId, $storeId);
        $categoryTree = $this->categoryTree->setStoreId($storeId)->loadBreadcrumbsArray($category->getPath());

        $categoryTreepath = array();
        foreach($categoryTree as $eachCategory){
            echo $eachCategory['name'];
            $categoryTreepath[] = $eachCategory['name'];
        }
        $categoryTree = implode(' > ',$categoryTreepath);
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
         *
         * status - yes no
         * attribute code
         */
        foreach ($collection as $product) {
            $entry = $this->getSkroutzProduct($product);
            $entry['manufacturer'] = "EUR";
            $entry['mpn'] = "EUR";
            $entry['ean'] = "EUR";
            $entry['quantity'] = 0;
            $entry['availability'] = "EUR";
            $entry['size'] = "EUR"; // hard like my ..
            $entry['weight'] = $product->getWeight();
            $entry['color'] = "EUR";
            $entry['description'] = $product->getCustomAttribute('description');
            $feed['products']['product'][] = $entry;
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
            'id' => $product->getId(),
            'name' => [ '_cdata' => $product->getName() ],
            'link' => $product->getProductUrl(),
            'price_with_vat' => $product->getFinalPrice(),
            'vat' => $this->getTaxPercentage($product),
        ];
        if ($product->getCategoryIds() && count($product->getCategoryIds())){
            $entry['category']['_cdata'] = $this->getTreeByCategoryId($product->getCategoryIds()[0]);
        }
        $this->getImages($product, $entry);
        $this->getStockData($product, $entry);
        return $entry;
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @param $entry
     */
    protected function getStockData($product, &$entry){
        $entry['instock'] = $product->isSalable() ? 'Y' : 'N';
        $stockItem = $product->getExtensionAttributes()->getStockItem();
        if (!empty($stockItem)){
            $entry['quantity'] = $stockItem->getQty();
        }
        return $entry;
    }

    /**
     * @param $product \Magento\Catalog\Model\Product
     * @param $entry
     */
    protected function getImages($product, &$entry){
        $entry['image']['_cdata'] = $this->mediaUrl . 'catalog/product' . $product->getData('image');
        foreach ($product->getMediaGalleryImages() as $image){
            $entry['__custom:additionalimage:' . $image->getId()]['_cdata'] = $image->getUrl();
        }
    }
}

