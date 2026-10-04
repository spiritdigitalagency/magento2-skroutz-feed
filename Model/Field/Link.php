<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;

/**
 * The https link of the product page, from the URL rewrite loaded with the collection.
 */
class Link implements FieldInterface
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
        if ((int)$product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
            return null;
        }
        $path = $product->getData('request_path') ?: 'catalog/product/view/id/' . $product->getId();

        /** @var Store $store */
        $store = $this->storeManager->getStore();

        return $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) . $path;
    }
}
