<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Spirit\SkroutzFeed\Model\AttributeValue;
use Spirit\SkroutzFeed\Model\Config;

/**
 * The shipping cost: an attribute, a fixed value, or calculated from weight and price:
 * the base cost up to a weight, plus a cost per extra kilo, free above a price.
 */
class Shipping extends Mapped
{
    /**
     * @var Price
     */
    private $price;

    /**
     * @var Weight
     */
    private $weight;

    /**
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param Price $price
     * @param Weight $weight
     * @param string $field
     */
    public function __construct(
        Config $config,
        AttributeValue $attributeValue,
        Price $price,
        Weight $weight,
        string $field = 'shipping'
    ) {
        parent::__construct($config, $attributeValue, $field, 'decimal');
        $this->price = $price;
        $this->weight = $weight;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        if ($this->config->getMapping($this->field) !== Config::AUTO) {
            return parent::getValue($product);
        }
        $base = $this->config->get('feed_mapping/shipping_cost');
        if ($base === '') {
            return null;
        }
        $freeOver = (float)$this->config->get('feed_mapping/shipping_free_over');
        if ($freeOver > 0 && (float)$this->price->getValue($product) >= $freeOver) {
            return '0.00';
        }
        $kilos = (float)$this->weight->getValue($product) / 1000;
        $extraKilos = max(0, (int)ceil($kilos - (float)$this->config->get('feed_mapping/shipping_weight')));

        return number_format(
            (float)$base + $extraKilos * (float)$this->config->get('feed_mapping/shipping_extra_kg'),
            2,
            '.',
            ''
        );
    }
}
