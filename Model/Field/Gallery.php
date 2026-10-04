<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;

/**
 * The gallery images other than the base image, up to the 15 images Skroutz accepts in total.
 */
class Gallery implements FieldInterface
{
    public const KEY = 'skroutz_gallery';

    /**
     * @var Image
     */
    private $image;

    /**
     * @param Image $image
     */
    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $base = (string)$product->getData('image');
        $urls = [];
        foreach ($product->getData(self::KEY) ?: [] as $file) {
            if ($file !== $base && count($urls) < 14) {
                $urls[$file] = $this->image->getUrl($file);
            }
        }

        return $urls ? array_values($urls) : null;
    }
}
