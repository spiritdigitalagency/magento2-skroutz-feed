<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;

/**
 * One element of a feed product.
 *
 * Fields are registered in di.xml on Spirit\SkroutzFeed\Model\RowBuilder (argument "fields", keyed by
 * element name). Add an item to export a new element, or replace one to change how it is computed.
 *
 * @api
 */
interface FieldInterface
{
    /**
     * The value of the element, as a string, a list of strings (repeated element), a name => value map
     * (specifications), or null to leave the element out.
     *
     * @param Product $product
     * @return string|string[]|null
     */
    public function getValue(Product $product);
}
