<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Spirit\SkroutzFeed\Model\UniqueId;

/**
 * The Unique ID (see UniqueId).
 */
class Id implements FieldInterface
{
    /**
     * @var UniqueId
     */
    private $uniqueId;

    /**
     * @param UniqueId $uniqueId
     */
    public function __construct(UniqueId $uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $id = $this->uniqueId->get($product);

        return $id === '' ? null : $id;
    }
}
