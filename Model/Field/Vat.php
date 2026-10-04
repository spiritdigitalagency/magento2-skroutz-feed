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
use Spirit\SkroutzFeed\Model\Tax;

/**
 * The VAT rate, from the product tax class or a mapped or fixed value.
 */
class Vat extends Mapped
{
    /**
     * @var Tax
     */
    private $tax;

    /**
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param Tax $tax
     * @param string $field
     */
    public function __construct(
        Config $config,
        AttributeValue $attributeValue,
        Tax $tax,
        string $field = 'vat'
    ) {
        parent::__construct($config, $attributeValue, $field, 'decimal');
        $this->tax = $tax;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        if ($this->config->getMapping($this->field) !== Config::AUTO) {
            return parent::getValue($product);
        }

        return number_format($this->tax->getRate((int)$product->getData('tax_class_id')), 2, '.', '');
    }
}
