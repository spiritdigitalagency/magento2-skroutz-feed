<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Stock of a batch of products, read with a few queries.
 *
 * With MSI, quantities are the salable quantities of the stock assigned to the website, which is the stock
 * index plus the reservations of orders not yet shipped. Without MSI, the legacy stock item. Both minus the
 * "Out-of-Stock Threshold".
 */
class Stock
{
    public const KEY = 'skroutz_stock';

    public const IN_STOCK = 'in_stock';
    public const BACKORDER = 'backorder';
    public const OUT_OF_STOCK = 'out_of_stock';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array<string, int|null> website code => MSI stock id
     */
    private $stockIds = [];

    /**
     * @param ResourceConnection $resource
     * @param StockConfigurationInterface $stockConfiguration
     * @param ModuleManager $moduleManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResourceConnection $resource,
        StockConfigurationInterface $stockConfiguration,
        ModuleManager $moduleManager,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->stockConfiguration = $stockConfiguration;
        $this->moduleManager = $moduleManager;
        $this->storeManager = $storeManager;
    }

    /**
     * Attach the stock of each product as data "skroutz_stock", with qty, in_stock, managed and backorders.
     *
     * @param Product[] $products keyed by product id
     * @param StoreInterface $store
     * @return void
     */
    public function load(array $products, StoreInterface $store): void
    {
        if (!$products) {
            return;
        }
        $storeId = (int)$store->getId();
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAssoc(
            $connection->select()
                ->from($this->resource->getTableName('cataloginventory_stock_item'), [
                    'product_id', 'qty', 'is_in_stock', 'use_config_manage_stock', 'manage_stock',
                    'use_config_backorders', 'backorders', 'use_config_min_qty', 'min_qty',
                ])
                ->where('product_id IN (?)', array_keys($products))
                ->where('website_id = ?', $this->stockConfiguration->getDefaultScopeId())
        );
        $salable = $this->getSalable($products, $store);
        $manage = (bool)$this->stockConfiguration->getManageStock($storeId);
        $backorders = (int)$this->stockConfiguration->getBackorders($storeId);
        $minQty = (float)$this->stockConfiguration->getMinQty($storeId);
        foreach ($products as $id => $product) {
            $row = $rows[$id] ?? null;
            $qty = (float)($row['qty'] ?? 0);
            $inStock = (bool)($row['is_in_stock'] ?? false);
            $msi = $salable[(string)$product->getSku()] ?? null;
            if ($msi) {
                [$qty, $inStock] = $msi;
            }
            $product->setData(self::KEY, [
                'qty' => $qty - ($row && !$row['use_config_min_qty'] ? (float)$row['min_qty'] : $minQty),
                'in_stock' => $inStock,
                'managed' => $row && !$row['use_config_manage_stock'] ? (bool)$row['manage_stock'] : $manage,
                'backorders' => ($row && !$row['use_config_backorders'] ? (int)$row['backorders'] : $backorders) > 0,
            ]);
        }
    }

    /**
     * In stock, on backorder or out of stock.
     *
     * @param Product $product
     * @return string
     */
    public function getState(Product $product): string
    {
        $stock = $product->getData(self::KEY);
        if (!$stock['managed'] || ($stock['in_stock'] && $stock['qty'] > 0)) {
            return self::IN_STOCK;
        }

        return $stock['in_stock'] && $stock['backorders'] ? self::BACKORDER : self::OUT_OF_STOCK;
    }

    /**
     * MSI salable quantity and status by SKU, for the stock of the store's website.
     *
     * @param Product[] $products
     * @param StoreInterface $store
     * @return array<string, array{0: float, 1: bool}> sku => [quantity, is salable]
     */
    private function getSalable(array $products, StoreInterface $store): array
    {
        $stockId = $this->getStockId($store);
        if ($stockId === null) {
            return [];
        }
        $skus = [];
        foreach ($products as $product) {
            $skus[] = (string)$product->getSku();
        }
        $connection = $this->resource->getConnection();
        // The index table as Magento\InventoryIndexer names it. Stock 1 is a view of the legacy index
        $index = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('inventory_stock_' . $stockId), ['sku', 'quantity', 'is_salable'])
                ->where('sku IN (?)', $skus)
        );
        $reserved = $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName('inventory_reservation'), ['sku', 'SUM(quantity)'])
                ->where('stock_id = ?', $stockId)
                ->where('sku IN (?)', $skus)
                ->group('sku')
        );
        $salable = [];
        foreach ($index as $row) {
            $salable[(string)$row['sku']] = [
                (float)$row['quantity'] + (float)($reserved[$row['sku']] ?? 0),
                (bool)$row['is_salable'],
            ];
        }

        return $salable;
    }

    /**
     * The MSI stock of the store's website, null without MSI.
     *
     * @param StoreInterface $store
     * @return int|null
     */
    private function getStockId(StoreInterface $store): ?int
    {
        $websiteCode = (string)$this->storeManager->getWebsite((int)$store->getWebsiteId())->getCode();
        if (!array_key_exists($websiteCode, $this->stockIds)) {
            $this->stockIds[$websiteCode] = null;
            if ($this->moduleManager->isEnabled('Magento_InventorySales')) {
                $connection = $this->resource->getConnection();
                $stockId = $connection->fetchOne(
                    $connection->select()
                        ->from($this->resource->getTableName('inventory_stock_sales_channel'), ['stock_id'])
                        ->where('type = ?', 'website')
                        ->where('code = ?', $websiteCode)
                );
                $this->stockIds[$websiteCode] = $stockId ? (int)$stockId : null;
            }
        }

        return $this->stockIds[$websiteCode];
    }
}
