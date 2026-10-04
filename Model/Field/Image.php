<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The base image.
 */
class Image implements FieldInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $file = (string)$product->getData('image');

        return $file === '' || $file === 'no_selection' ? null : $this->getUrl($file);
    }

    /**
     * The public URL of a catalog image file.
     *
     * @param string $file e.g. "/a/b/abc.jpg"
     * @return string
     */
    public function getUrl(string $file): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true)
            . 'catalog/product/' . ltrim($file, '/');
    }
}
