<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;
use Spirit\SkroutzFeed\Model\Field\Availability as AvailabilityField;
use Spirit\SkroutzFeed\Model\Field\Gallery as GalleryField;

/**
 * Loads the catalog of a website in batches, with everything the feed needs attached to each product.
 *
 * Memory stays flat on any catalog size: products are read in keyset batches (entity_id > last id, no
 * OFFSET and no COUNT), stock, prices, categories, images and URLs come with one query per batch, and the
 * children of configurable products are loaded in chunks of at most $maxChildren products.
 */
class ProductLoader
{
    public const EXCLUDE_ATTRIBUTE = 'skroutz_exclude';

    private const VISIBLE = [
        Visibility::VISIBILITY_IN_CATALOG,
        Visibility::VISIBILITY_IN_SEARCH,
        Visibility::VISIBILITY_BOTH,
    ];

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var Gallery
     */
    private $gallery;

    /**
     * @var PriceTableResolver
     */
    private $priceTableResolver;

    /**
     * @var DimensionFactory
     */
    private $dimensionFactory;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UniqueId
     */
    private $uniqueId;

    /**
     * @var Stock
     */
    private $stock;

    /**
     * @var CategoryTree
     */
    private $categoryTree;

    /**
     * @var string[]
     */
    private $productTypes;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param EavConfig $eavConfig
     * @param Gallery $gallery
     * @param PriceTableResolver $priceTableResolver
     * @param DimensionFactory $dimensionFactory
     * @param TimezoneInterface $timezone
     * @param EventManager $eventManager
     * @param Config $config
     * @param UniqueId $uniqueId
     * @param Stock $stock
     * @param CategoryTree $categoryTree
     * @param string[] $productTypes the product types Skroutz can list (not bundle, grouped, virtual, downloadable)
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ResourceConnection $resource,
        MetadataPool $metadataPool,
        EavConfig $eavConfig,
        Gallery $gallery,
        PriceTableResolver $priceTableResolver,
        DimensionFactory $dimensionFactory,
        TimezoneInterface $timezone,
        EventManager $eventManager,
        Config $config,
        UniqueId $uniqueId,
        Stock $stock,
        CategoryTree $categoryTree,
        array $productTypes = ['simple', 'configurable']
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resource;
        $this->metadataPool = $metadataPool;
        $this->eavConfig = $eavConfig;
        $this->gallery = $gallery;
        $this->priceTableResolver = $priceTableResolver;
        $this->dimensionFactory = $dimensionFactory;
        $this->timezone = $timezone;
        $this->eventManager = $eventManager;
        $this->config = $config;
        $this->uniqueId = $uniqueId;
        $this->stock = $stock;
        $this->categoryTree = $categoryTree;
        $this->productTypes = $productTypes;
    }

    /**
     * Chunks of products with the children of their configurables.
     *
     * @param StoreInterface $store the store view of the feed
     * @param int $batchSize products per query
     * @param int $maxChildren children per chunk, the memory ceiling for stores with large configurables
     * @return \Generator<array{0: Product[], 1: array<int, Product[]>}> [products, parent id => children]
     */
    public function load(StoreInterface $store, int $batchSize = 500, int $maxChildren = 2000): \Generator
    {
        $lastId = 0;
        do {
            $collection = $this->createCollection($store)
                ->addAttributeToFilter('visibility', ['in' => self::VISIBLE])
                ->addAttributeToFilter('type_id', ['in' => $this->productTypes])
                ->addAttributeToFilter('entity_id', ['gt' => $lastId])
                ->setOrder('entity_id', Collection::SORT_ORDER_ASC)
                ->setPageSize($batchSize)
                ->setCurPage(1);
            $this->eventManager->dispatch('spirit_skroutzfeed_collection', [
                'collection' => $collection,
                'store' => $store,
            ]);
            $products = $collection->getItems();
            if (!$products) {
                return;
            }
            $batchCount = count($products);
            $lastId = max(array_map('intval', array_keys($products)));
            $products = $this->withoutVariants($products);
            $this->prepare($products, $store);
            $this->categoryTree->assign($products);
            $this->loadUrls($products, (int)$store->getId());
            $childIds = $this->getChildIds($products);

            $chunk = $chunkChildren = [];
            foreach ($products as $id => $product) {
                $chunk[$id] = $product;
                $chunkChildren += $childIds[$id] ?? [];
                if (count($chunkChildren) >= $maxChildren) {
                    yield [$chunk, $this->loadChildren($chunk, $childIds, $store)];
                    $chunk = $chunkChildren = [];
                }
            }
            if ($chunk) {
                yield [$chunk, $this->loadChildren($chunk, $childIds, $store)];
            }
        } while ($batchCount === $batchSize);
    }

    /**
     * A product collection of the website: enabled products with the attributes the feed reads.
     *
     * @param StoreInterface $store
     * @param string[] $extraAttributes
     * @return Collection
     */
    private function createCollection(StoreInterface $store, array $extraAttributes = []): Collection
    {
        $attributes = array_merge(
            $this->config->getAttributes(),
            [$this->uniqueId->getAttributeCode(), $this->uniqueId->getVariationAttributeCode()],
            $extraAttributes
        );
        $collection = $this->collectionFactory->create()
            ->setStoreId((int)$store->getId())
            ->addWebsiteFilter((int)$store->getWebsiteId())
            ->addAttributeToSelect(array_values(array_diff(array_unique($attributes), ['entity_id'])))
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
        if ($this->config->isAttribute(self::EXCLUDE_ATTRIBUTE)) {
            $collection->addAttributeToFilter(
                self::EXCLUDE_ATTRIBUTE,
                [['null' => true], ['neq' => 1]],
                'left'
            );
        }

        return $collection;
    }

    /**
     * Drop the children of configurable products: they are listed only as variations of their parent.
     *
     * A child of a disabled or hidden configurable is not listed on its own either: whoever turned the
     * configurable off meant the whole product.
     *
     * @param Product[] $products
     * @return Product[]
     */
    private function withoutVariants(array $products): array
    {
        $simpleIds = [];
        foreach ($products as $id => $product) {
            if ($product->getTypeId() !== Configurable::TYPE_CODE) {
                $simpleIds[] = $id;
            }
        }
        if (!$simpleIds) {
            return $products;
        }
        $connection = $this->resource->getConnection();
        $childIds = $connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from($this->resource->getTableName('catalog_product_super_link'), ['product_id'])
                ->where('product_id IN (?)', $simpleIds)
        );

        return array_diff_key($products, array_flip($childIds));
    }

    /**
     * Child ids of the configurable products, with their super attributes attached to each parent.
     *
     * @param Product[] $products
     * @return array parent id => [child id => child id]
     */
    private function getChildIds(array $products): array
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $parents = [];
        foreach ($products as $id => $product) {
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                $parents[(int)$product->getData($linkField)] = $id;
                $product->setData(RowBuilder::SUPER_ATTRIBUTES, []);
            }
        }
        if (!$parents) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_super_attribute'), ['product_id', 'attribute_id'])
            ->where('product_id IN (?)', array_keys($parents))
            ->order('position');
        foreach ($connection->fetchAll($select) as $row) {
            $product = $products[$parents[$row['product_id']]];
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, (int)$row['attribute_id']);
            $super = $product->getData(RowBuilder::SUPER_ATTRIBUTES);
            $super[(int)$row['attribute_id']] = (string)$attribute->getAttributeCode();
            $product->setData(RowBuilder::SUPER_ATTRIBUTES, $super);
        }
        $children = [];
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_super_link'), ['parent_id', 'product_id'])
            ->where('parent_id IN (?)', array_keys($parents));
        foreach ($connection->fetchAll($select) as $row) {
            $children[$parents[$row['parent_id']]][(int)$row['product_id']] = (int)$row['product_id'];
        }

        return $children;
    }

    /**
     * Load the children of the configurables in a chunk.
     *
     * @param Product[] $chunk
     * @param array $childIds parent id => child ids
     * @param StoreInterface $store
     * @return array parent id => children
     */
    private function loadChildren(array $chunk, array $childIds, StoreInterface $store): array
    {
        $ids = $superCodes = [];
        foreach ($chunk as $id => $product) {
            $ids += $childIds[$id] ?? [];
            $superCodes[] = array_values($product->getData(RowBuilder::SUPER_ATTRIBUTES) ?: []);
        }
        if (!$ids) {
            return [];
        }
        $products = $this->createCollection($store, array_unique(array_merge([], ...$superCodes)))
            ->addAttributeToFilter('entity_id', ['in' => array_values($ids)])
            ->getItems();
        $this->prepare($products, $store);
        $children = [];
        foreach ($chunk as $id => $parent) {
            foreach ($childIds[$id] ?? [] as $childId) {
                if (isset($products[$childId])) {
                    $products[$childId]->setData(AvailabilityField::PARENT, $parent);
                    $children[$id][] = $products[$childId];
                }
            }
        }

        return $children;
    }

    /**
     * Attach stock, prices and gallery images.
     *
     * @param Product[] $products
     * @param StoreInterface $store
     * @return void
     */
    private function prepare(array $products, StoreInterface $store): void
    {
        $this->stock->load($products, $store);
        $this->loadPrices($products, $store);
        $this->loadGallery($products, (int)$store->getId());
    }

    /**
     * Attach "final_price" and "tax_class_id" from the price index.
     *
     * Special prices and catalog price rules included, for guests (customer group "NOT LOGGED IN").
     *
     * Magento leaves out of stock products out of the index when the store hides them, so those fall back
     * to their price or active special price.
     *
     * @param Product[] $products
     * @param StoreInterface $store
     * @return void
     */
    private function loadPrices(array $products, StoreInterface $store): void
    {
        if (!$products) {
            return;
        }
        $websiteId = (string)$store->getWebsiteId();
        $table = $this->priceTableResolver->resolve('catalog_product_index_price', [
            $this->dimensionFactory->create(
                CustomerGroupDimensionProvider::DIMENSION_NAME,
                (string)Group::NOT_LOGGED_IN_ID
            ),
            $this->dimensionFactory->create(WebsiteDimensionProvider::DIMENSION_NAME, $websiteId),
        ]);
        $connection = $this->resource->getConnection();
        $prices = $connection->fetchAssoc(
            $connection->select()
                ->from($table, ['entity_id', 'final_price', 'tax_class_id'])
                ->where('website_id = ?', $websiteId)
                ->where('customer_group_id = ?', Group::NOT_LOGGED_IN_ID)
                ->where('entity_id IN (?)', array_keys($products))
        );
        $today = $this->timezone->date()->format('Y-m-d');
        foreach ($products as $id => $product) {
            if (isset($prices[$id])) {
                $product->setData('final_price', (float)$prices[$id]['final_price']);
                $product->setData('tax_class_id', (int)$prices[$id]['tax_class_id']);
                continue;
            }
            $price = (float)$product->getData('price');
            $special = $product->getData('special_price');
            $from = substr((string)$product->getData('special_from_date'), 0, 10);
            $to = substr((string)$product->getData('special_to_date'), 0, 10);
            if ($special !== null && $special !== '' && (float)$special < $price
                && ($from === '' || $from <= $today) && ($to === '' || $to >= $today)
            ) {
                $price = (float)$special;
            }
            $product->setData('final_price', $price);
        }
    }

    /**
     * Attach the product URL path of the store as data "request_path".
     *
     * Only the URL without category path: Collection::addUrlRewrite() may pick any of the category URLs,
     * and the link Skroutz knows must not change between two feeds.
     *
     * @param Product[] $products
     * @param int $storeId
     * @return void
     */
    private function loadUrls(array $products, int $storeId): void
    {
        if (!$products) {
            return;
        }
        $connection = $this->resource->getConnection();
        $paths = $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName('url_rewrite'), ['entity_id', 'request_path'])
                ->where('entity_type = ?', 'product')
                ->where('store_id = ?', $storeId)
                ->where('is_autogenerated = 1')
                ->where('redirect_type = 0')
                ->where('metadata IS NULL')
                ->where('entity_id IN (?)', array_keys($products))
        );
        foreach ($products as $id => $product) {
            $product->setData('request_path', $paths[$id] ?? null);
        }
    }

    /**
     * Attach the enabled gallery images of each product, in their admin order, as data "skroutz_gallery".
     *
     * Same query as Collection::addMediaGalleryData(), without its COUNT(*) of the whole collection.
     *
     * @param Product[] $products
     * @param int $storeId
     * @return void
     */
    private function loadGallery(array $products, int $storeId): void
    {
        if (!$products) {
            return;
        }
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $byLink = [];
        foreach ($products as $product) {
            $byLink[(int)$product->getData($linkField)] = $product;
        }
        $attributeId = (int)$this->eavConfig->getAttribute(Product::ENTITY, 'media_gallery')->getAttributeId();
        $select = $this->gallery->createBatchBaseSelect($storeId, $attributeId)
            ->where('entity.' . $linkField . ' IN (?)', array_keys($byLink));
        $files = [];
        foreach ($this->resource->getConnection()->fetchAll($select) as $row) {
            $disabled = $row['disabled'] ?? $row['disabled_default'] ?? 0;
            if (!$disabled && ($row['media_type'] ?? 'image') === 'image') {
                $position = (int)($row['position'] ?? $row['position_default'] ?? 0);
                $files[(int)$row[$linkField]][] = [$position, (string)$row['file']];
            }
        }
        foreach ($byLink as $link => $product) {
            $images = $files[$link] ?? [];
            usort($images, function (array $a, array $b): int {
                return $a[0] <=> $b[0];
            });
            $product->setData(GalleryField::KEY, array_column($images, 1));
        }
    }
}
