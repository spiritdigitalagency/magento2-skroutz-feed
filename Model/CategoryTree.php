<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;

/**
 * The category paths of a store, loaded once per feed instead of once per product.
 */
class CategoryTree
{
    public const KEY = 'skroutz_category_ids';

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var array<int, string> category id => "Parent > Child" path names
     */
    private $paths = [];

    /**
     * @var array<int, int> category id => level
     */
    private $levels = [];

    /**
     * @var array<int, bool> the categories of the filter, with their descendants
     */
    private $filtered = [];

    /**
     * @var bool true: only products in the filter categories; false: all but them
     */
    private $include = false;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resource
     * @param Config $config
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ResourceConnection $resource,
        Config $config
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resource;
        $this->config = $config;
    }

    /**
     * Load the active categories under the store's root category.
     *
     * @param StoreInterface $store
     * @return void
     */
    public function load(StoreInterface $store): void
    {
        $rootPath = '1/' . (int)$store->getRootCategoryId() . '/';
        $collection = $this->collectionFactory->create()
            ->setStoreId((int)$store->getId())
            ->addAttributeToSelect(['name', 'is_active'])
            ->addFieldToFilter('path', ['like' => $rootPath . '%']);
        $names = [];
        $active = [];
        $categoryPaths = [];
        foreach ($collection as $category) {
            $id = (int)$category->getId();
            $names[$id] = trim((string)$category->getName());
            $active[$id] = (bool)$category->getIsActive();
            $categoryPaths[$id] = array_map('intval', array_slice(explode('/', (string)$category->getPath()), 2));
        }
        $filterIds = array_flip(array_map('intval', array_filter(
            explode(',', $this->config->get('feed_products/categories'))
        )));
        $this->include = $filterIds && $this->config->get('feed_products/category_filter') === 'include';
        $this->paths = $this->levels = $this->filtered = [];
        foreach ($categoryPaths as $id => $ids) {
            if (array_intersect_key(array_flip($ids), $filterIds)) {
                $this->filtered[$id] = true;
            }
            $pathNames = [];
            foreach ($ids as $pathId) {
                if (empty($active[$pathId]) || ($names[$pathId] ?? '') === '') {
                    continue 2;
                }
                $pathNames[] = $names[$pathId];
            }
            $this->paths[$id] = implode(' > ', $pathNames);
            $this->levels[$id] = count($ids);
        }
    }

    /**
     * Attach the category ids of each product as data "skroutz_category_ids", with one query.
     *
     * @param \Magento\Catalog\Model\Product[] $products keyed by product id
     * @return void
     */
    public function assign(array $products): void
    {
        if (!$products) {
            return;
        }
        $connection = $this->resource->getConnection();
        $ids = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('catalog_category_product'), ['product_id', 'category_id'])
                ->where('product_id IN (?)', array_keys($products))
        ) as $row) {
            $ids[(int)$row['product_id']][] = (int)$row['category_id'];
        }
        foreach ($products as $id => $product) {
            $product->setData(self::KEY, $ids[$id] ?? []);
        }
    }

    /**
     * The path of the deepest active category, the most specific one Skroutz can classify by.
     *
     * @param int[] $categoryIds
     * @return string|null
     */
    public function getPath(array $categoryIds): ?string
    {
        $best = null;
        foreach ($categoryIds as $id) {
            if (isset($this->paths[$id]) && ($best === null || $this->levels[$id] > $this->levels[$best])) {
                $best = $id;
            }
        }

        return $best === null ? null : $this->paths[$best];
    }

    /**
     * Whether the category filter lets a product with these categories into the feed.
     *
     * @param int[] $categoryIds
     * @return bool
     */
    public function isAllowed(array $categoryIds): bool
    {
        foreach ($categoryIds as $id) {
            if (isset($this->filtered[$id])) {
                return $this->include;
            }
        }

        return !$this->include;
    }
}
