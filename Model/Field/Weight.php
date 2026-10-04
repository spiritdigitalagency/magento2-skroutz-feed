<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Magento\Directory\Helper\Data as DirectoryHelper;

/**
 * The weight in grams.
 */
class Weight implements FieldInterface
{
    /**
     * @var DirectoryHelper
     */
    private $directoryHelper;

    /**
     * @param DirectoryHelper $directoryHelper
     */
    public function __construct(DirectoryHelper $directoryHelper)
    {
        $this->directoryHelper = $directoryHelper;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $weight = (float)$product->getData('weight');
        if ($weight <= 0) {
            return null;
        }
        $grams = $this->directoryHelper->getWeightUnit() === 'lbs' ? $weight * 453.59237 : $weight * 1000;

        return (string)(int)round($grams);
    }
}
