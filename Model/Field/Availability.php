<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Spirit\SkroutzFeed\Model\Config;
use Spirit\SkroutzFeed\Model\Stock;

/**
 * The availability text for the stock state of the product (in stock, on backorder, out of stock).
 *
 * Taken from the product's own "Skroutz Availability" attribute of that state, then its configurable
 * parent's, then the configuration. "Hide from Skroutz" (Availability source HIDE) keeps it out of the feed.
 */
class Availability implements FieldInterface
{
    /** Per product attribute of each stock state */
    public const ATTRIBUTES = [
        Stock::IN_STOCK => 'skroutz_availability',
        Stock::BACKORDER => 'skroutz_availability_backorder',
        Stock::OUT_OF_STOCK => 'skroutz_availability_out_of_stock',
    ];

    /** Product data: the configurable parent of a child product */
    public const PARENT = 'skroutz_parent';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Stock
     */
    private $stock;

    /**
     * @param Config $config
     * @param Stock $stock
     */
    public function __construct(
        Config $config,
        Stock $stock
    ) {
        $this->config = $config;
        $this->stock = $stock;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $state = $this->stock->getState($product);
        $code = self::ATTRIBUTES[$state];
        $value = (string)$product->getData($code);
        $parent = $product->getData(self::PARENT);
        if ($value === '' && $parent instanceof Product) {
            $value = (string)$parent->getData($code);
        }
        if ($value === '') {
            $value = $this->config->get('feed_stock/' . $state);
        }

        return $value === '' ? null : $value;
    }
}
