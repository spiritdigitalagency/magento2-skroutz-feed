<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * The Unique ID of a product in the feed: the Magento product ID unless another attribute is mapped.
 *
 * Every Unique ID of the feed (products, colors, variations) is built here. To change it, add a plugin on
 * get() or getForVariant(); Skroutz Analytics must then send the same ID with orders.
 *
 * @api
 */
class UniqueId
{
    /** "Variation ID" setting: the Unique ID attribute */
    public const SAME = '__same';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @param Config $config
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        Config $config,
        ModuleManager $moduleManager
    ) {
        $this->config = $config;
        $this->moduleManager = $moduleManager;
    }

    /**
     * The attribute holding the Unique ID.
     *
     * @param int|null $storeId defaults to the store view being generated
     * @return string
     */
    public function getAttributeCode(?int $storeId = null): string
    {
        return $this->config->get('feed_mapping/id', $storeId) ?: 'entity_id';
    }

    /**
     * The "Unique ID" attribute of Skroutz Analytics (Spirit_Skroutz), null when it is not installed or enabled.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAnalyticsAttributeCode(?int $storeId = null): ?string
    {
        if (!$this->moduleManager->isEnabled('Spirit_Skroutz') || !$this->config->get('analytics/status', $storeId)) {
            return null;
        }

        return $this->config->get('analytics/unique_id', $storeId) ?: 'entity_id';
    }

    /**
     * The Unique ID of a product, '' when the attribute is empty.
     *
     * @param Product $product
     * @return string
     */
    public function get(Product $product): string
    {
        return $this->value($product, $this->getAttributeCode());
    }

    /**
     * The attribute holding the Unique ID of size variations: "Variation ID", by default the Unique ID one.
     *
     * @param int|null $storeId defaults to the store view being generated
     * @return string
     */
    public function getVariationAttributeCode(?int $storeId = null): string
    {
        $code = (string)$this->config->get('feed_mapping/variation_id', $storeId);

        return $code === '' || $code === self::SAME ? $this->getAttributeCode($storeId) : $code;
    }

    /**
     * The Unique ID of a size variation (a child nested in <variations>), '' when the attribute is empty.
     *
     * @param Product $child
     * @return string
     */
    public function getForVariation(Product $child): string
    {
        return $this->value($child, $this->getVariationAttributeCode());
    }

    /**
     * The Unique ID of one color (or other option) of a configurable product, with its sizes nested.
     *
     * "<parent Unique ID>-<option id>", the format Skroutz recommends for color variations. Option ids do not
     * change when an option is renamed in the admin, only when it is deleted.
     *
     * @param Product $parent
     * @param string[] $optionIds one per option of the group, usually only the color
     * @return string
     */
    public function getForVariant(Product $parent, array $optionIds): string
    {
        return implode('-', array_merge([$this->get($parent)], array_values($optionIds)));
    }

    /**
     * The value of an ID attribute.
     *
     * @param Product $product
     * @param string $code
     * @return string
     */
    private function value(Product $product, string $code): string
    {
        return trim((string)($code === 'entity_id' ? $product->getId() : $product->getData($code)));
    }
}
