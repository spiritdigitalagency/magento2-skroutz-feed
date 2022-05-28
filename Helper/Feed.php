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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var string
     */
    protected $mediaUrl;

    /**
     * Feed constructor.
     * @param  \Magento\Framework\App\Helper\Context  $context
     * @param  \Magento\Framework\Filesystem\Io\File  $file
     * @param  \Magento\Framework\Filesystem  $filesystem
     * @param  \Magento\Framework\Stdlib\DateTime\TimezoneInterface  $dateTime
     * @param  \Magento\Framework\Convert\ConvertArray  $convertArray
     * @param  \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory  $productCollectionFactory
     * @param  FeedProduct  $feedProduct
     * @param  \Magento\Store\Model\StoreManagerInterface  $storeManager
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
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->file = $file;
        $this->filesystem = $filesystem;
        $this->dateTime = $dateTime;
        $this->convertArray = $convertArray;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->feedProductHelper = $feedProduct;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function generate($storeId = null,$page = 1, $limit = 500)
    {
        /**
         * @TODO check multistore behavior
         */
        $storeId = $this->storeManager->getStore($storeId)->getId();
        $this->mediaUrl = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        if (!$this->getConfig('feed_settings/status')) {
            return;
        }
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect('*');
        if ($this->getConfig('feed_settings/exclude_disabled')) {
            $collection->addAttributeToFilter('status',
                \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        }
        if ($this->getConfig('feed_settings/exclude_no_image')) {
            $collection->addAttributeToFilter('small_image', ['neq' => 'no_selection']);
        }
        if ($this->getConfig('feed_settings/exclude_outofstock')) {
            $collection->setFlag('has_stock_status_filter', false);
        }
        if ($this->getConfig('feed_settings/exclude_not_visible')) {
            $collection->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        }
        if ($categories = $this->getConfig('feed_settings/exclude_categories')) {
            $collection->addCategoriesFilter(['nin' => explode(',', $categories)]);
        }
        $collection->setPageSize($limit)->setCurPage($page);
        $feed = [
            'created_at' => $this->dateTime->date()->format('Y-m-d H:i:s'), 'products' => []
        ];
        while ($page <= $collection->getLastPageNumber()) {
            $collection->clear()->setCurPage($page);
            foreach ($collection->getItems() as $product) {
                $feed['products']['product'][] = $this->getSkroutzProduct($product);
            }
            $page++;
        }
        $xmlFeed = ArrayToXml::convert($feed, 'feed', true, 'UTF-8');
        $this->file->write($this->getFeedFile(), $xmlFeed);
    }

    public function getFeedFile()
    {
        $feedDirectory = $this->filesystem->getDirectoryRead(DirectoryList::PUB)->getAbsolutePath('feeds');
        /**
         * @TODO validate filename
         * @TODO check multistore filename
         */
        $filename = $this->getConfig('feed_settings/filename') ?? 'skroutz';
        if (!$this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->isDirectory($feedDirectory)) {
            $this->file->mkdir($feedDirectory, 0775);
        }
        return "$feedDirectory/$filename.xml";
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

    /**
     * @param $product
     * @return array
     */
    protected function getSkroutzProduct($product)
    {
        $entry = [
            'id' => $this->feedProductHelper->getId($product),
            'name' => ['_cdata' => $this->feedProductHelper->getName($product)],
            'link' => ['_cdata' => $this->feedProductHelper->getLink($product)],
            'image' => ['_cdata' => $this->feedProductHelper->getImage($product)],
            'price_with_vat' => $this->feedProductHelper->getPriceWithVat($product),
            'vat' => $this->feedProductHelper->getVat($product),
            'availability' => ['_cdata' => $this->feedProductHelper->getAvailability($product)],
            'manufacturer' => ['_cdata' => $this->feedProductHelper->getManufacturer($product)],
            'mpn' => ['_cdata' => $this->feedProductHelper->getMpn($product)],
            'instock' => $this->feedProductHelper->getInStock($product),
            'weight' => $this->feedProductHelper->getWeight($product)
        ];
        foreach ($this->feedProductHelper->getAdditionalImages($product) as $id => $image) {
            $entry['__custom:additionalimage:'.$id]['_cdata'] = $image;
        }
        if ($category_path = $this->feedProductHelper->getCategoryPath($product)) {
            $entry['category']['_cdata'] = $category_path;
        }
        if ($color = $this->feedProductHelper->getColor($product)) {
            $entry['color']['_cdata'] = $color;
        }
        if ($size = $this->feedProductHelper->getSize($product)) {
            $entry['size']['_cdata'] = $size;
        }
        if ($ean = $this->feedProductHelper->getEan($product)) {
            $entry['ean']['_cdata'] = $ean;
        }
        if ($description = $this->feedProductHelper->getDescription($product)) {
            $entry['description']['_cdata'] = $description;
        }
        if ($qty = $this->feedProductHelper->getQuantity($product)) {
            $entry['quantity'] = $qty;
        }
        return $entry;
    }
}
