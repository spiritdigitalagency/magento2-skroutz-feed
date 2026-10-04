<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;

/**
 * Reads a product attribute as the text a customer sees, with option labels in the store's language.
 *
 * Option labels are loaded once per attribute and store, not once per product.
 */
class AttributeValue
{
    private const OPTION_INPUTS = ['select', 'multiselect', 'boolean'];

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var int
     */
    private $storeId = 0;

    /**
     * @var array<string, array<string, string>|null> attribute code => option value => label, null for free text
     */
    private $options = [];

    /**
     * @var string[] attribute code => backend type
     */
    private $backendTypes = [];

    /**
     * @param EavConfig $eavConfig
     */
    public function __construct(EavConfig $eavConfig)
    {
        $this->eavConfig = $eavConfig;
    }

    /**
     * Forget the labels of the previous store.
     *
     * @param int $storeId
     * @return void
     */
    public function setStore(int $storeId): void
    {
        $this->storeId = $storeId;
        $this->options = [];
    }

    /**
     * The value of an attribute, null when the product has none.
     *
     * @param Product $product
     * @param string $code
     * @return string|null
     */
    public function get(Product $product, string $code): ?string
    {
        $raw = $code === 'entity_id' ? $product->getId() : $product->getData($code);
        if ($raw === null || $raw === '' || $raw === false || is_array($raw)) {
            return null;
        }
        $labels = $this->getOptions($code);
        if ($labels === null) {
            return $this->format($code, (string)$raw);
        }
        $values = [];
        foreach (explode(',', (string)$raw) as $value) {
            if (($labels[$value] ?? '') !== '') {
                $values[] = $labels[$value];
            }
        }

        return $values ? implode(', ', $values) : null;
    }

    /**
     * The label of an attribute in the current store.
     *
     * @param string $code
     * @return string
     */
    public function getLabel(string $code): string
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
        if (!$attribute->getId()) {
            return $code;
        }
        $labels = $attribute->getStoreLabels();

        return (string)($labels[$this->storeId] ?? $attribute->getFrontendLabel());
    }

    /**
     * A free text value as a customer reads it, "12.5" rather than "12.500000" and a date without its time.
     *
     * @param string $code
     * @param string $raw
     * @return string
     */
    private function format(string $code, string $raw): string
    {
        if (!array_key_exists($code, $this->backendTypes)) {
            $attribute = $code === 'entity_id' ? null : $this->eavConfig->getAttribute(Product::ENTITY, $code);
            $this->backendTypes[$code] = $attribute ? (string)$attribute->getBackendType() : '';
        }
        if ($this->backendTypes[$code] === 'decimal' && is_numeric($raw)) {
            return rtrim(rtrim(number_format((float)$raw, 4, '.', ''), '0'), '.');
        }
        if ($this->backendTypes[$code] === 'datetime' && substr($raw, 10) === ' 00:00:00') {
            return substr($raw, 0, 10);
        }

        return $raw;
    }

    /**
     * Option labels of a select attribute, null for attributes without options.
     *
     * @param string $code
     * @return array<string, string>|null
     */
    private function getOptions(string $code): ?array
    {
        if (array_key_exists($code, $this->options)) {
            return $this->options[$code];
        }
        $this->options[$code] = null;
        $attribute = $code === 'entity_id' ? null : $this->eavConfig->getAttribute(Product::ENTITY, $code);
        if ($attribute && $attribute->getId()
            && (in_array($attribute->getFrontendInput(), self::OPTION_INPUTS, true) || $attribute->getSourceModel())
        ) {
            $attribute->setStoreId($this->storeId);
            $labels = [];
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                if (!is_array($option['value'] ?? null)) {
                    $labels[(string)$option['value']] = trim((string)$option['label']);
                }
            }
            $this->options[$code] = $labels;
        }

        return $this->options[$code];
    }
}
