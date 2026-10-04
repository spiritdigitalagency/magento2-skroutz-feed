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
 * The quantity available for sale, 0 to 10,000,000 as Skroutz requires.
 */
class Quantity implements FieldInterface
{
    public const MAX = 10000000;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $stock = $product->getData(Stock::KEY);
        $qty = $stock['managed'] ? $stock['qty'] : (float)$this->config->get('feed_stock/unmanaged_qty');

        return (string)min(self::MAX, max(0, (int)floor($qty)));
    }
}
