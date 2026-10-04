<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Spirit\SkroutzFeed\Model\Tax;

/**
 * The final price with VAT, from the price index: special prices and catalog price rules included,
 * as a guest (customer group "NOT LOGGED IN") sees it.
 */
class Price implements FieldInterface
{
    /**
     * @var Tax
     */
    private $tax;

    /**
     * @param Tax $tax
     */
    public function __construct(Tax $tax)
    {
        $this->tax = $tax;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $price = (float)$product->getData('final_price');
        if ($price <= 0) {
            return null;
        }

        return number_format($this->tax->includeTax($price, (int)$product->getData('tax_class_id')), 2, '.', '');
    }
}
