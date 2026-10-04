<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Spirit\SkroutzFeed\Model\Config\Source\Availability as AvailabilitySource;
use Spirit\SkroutzFeed\Model\Field\FieldInterface;

/**
 * Turns a catalog product into the <product> rows of the feed.
 *
 * A simple product is one row. Skroutz nests sizes only, so a configurable product is:
 * - when it varies by a size attribute: one row per combination of its other options (color, fit...), every
 *   color being its own product on Skroutz, with the sizes nested as <variations>. Unique ID
 *   "<parent>-<option ids>", or the parent's when it varies by size only;
 * - when it has no size attribute (color only, capacity...): one row per child, with the child's Unique ID.
 *
 * Row keys starting with "_" are metadata for the report and are not written.
 */
class RowBuilder
{
    public const SUPER_ATTRIBUTES = 'skroutz_super_attributes';

    /** Fields a child row never borrows from its parent; its gallery goes with its image */
    private const OWN_FIELDS = ['id', 'ean', 'additional_image'];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AttributeValue
     */
    private $attributeValue;

    /**
     * @var UniqueId
     */
    private $uniqueId;

    /**
     * @var FieldInterface[] element name => field
     */
    private $fields;

    /**
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param UniqueId $uniqueId
     * @param FieldInterface[] $fields element name => field, in the order they are written
     */
    public function __construct(
        Config $config,
        AttributeValue $attributeValue,
        UniqueId $uniqueId,
        array $fields = []
    ) {
        $this->config = $config;
        $this->attributeValue = $attributeValue;
        $this->uniqueId = $uniqueId;
        $this->fields = $fields;
    }

    /**
     * The rows of a product, none when its availability is "Hide from Skroutz".
     *
     * @param Product $product
     * @param Product[] $children the children of a configurable product
     * @return array[]
     */
    public function build(Product $product, array $children = []): array
    {
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return $this->isListed($product) ? [$this->row($product) + ['_mode' => 'simple']] : [];
        }
        $super = $product->getData(self::SUPER_ATTRIBUTES) ?: [];
        $sizes = array_intersect($super, $this->config->getCodes('size'));
        // Decided on all children, so a size going out of stock does not change how the product is written
        $nest = !$this->config->get('feed_products/single_size') || $this->countSizes($children, $sizes) > 1;
        $children = array_filter($children, function (Product $child): bool {
            return (float)$child->getData('final_price') > 0 && $this->isListed($child);
        });
        if (!$children) {
            return [];
        }
        if (!$sizes) {
            return $this->childRows($product, $children, $super);
        }
        // Every other option (color, fit, material...) makes a different product on Skroutz
        $options = array_diff_key($super, $sizes);
        $groups = $values = [];
        foreach ($children as $child) {
            $optionIds = [];
            foreach ($options as $attributeId => $code) {
                $optionIds[$attributeId] = (string)$child->getData($code);
            }
            $key = implode('-', $optionIds);
            $groups[$key][] = $child;
            $values[$key] = $optionIds;
        }
        $rows = [];
        foreach ($groups as $key => $group) {
            $rows[] = $this->groupRow($product, $group, $values[$key], $options, $sizes, $nest);
        }

        return $rows;
    }

    /**
     * The values of all fields for one product.
     *
     * @param Product $product
     * @return array
     */
    public function row(Product $product): array
    {
        $row = ['_sku' => (string)$product->getSku()];
        foreach ($this->fields as $name => $field) {
            $row[$name] = $field->getValue($product);
        }

        return $row;
    }

    /**
     * Whether a product is listed: its availability is not "Hide from Skroutz".
     *
     * @param Product $product
     * @return bool
     */
    public function isListed(Product $product): bool
    {
        return $this->value('availability', $product) !== AvailabilitySource::HIDE;
    }

    /**
     * One row for the children of a configurable that share their options other than size.
     *
     * Their sizes are nested as variations.
     *
     * @param Product $parent
     * @param Product[] $group
     * @param string[] $optionIds attribute id => option id of the group, empty when only sizes vary
     * @param string[] $options attribute id => code of the options other than size
     * @param string[] $sizes attribute id => code of the size attributes
     * @param bool $nest false for "one size" products when "Do Not Nest a Single Size" is on
     * @return array
     */
    private function groupRow(
        Product $parent,
        array $group,
        array $optionIds,
        array $options,
        array $sizes,
        bool $nest
    ): array {
        $row = $this->row($parent);
        $row['_mode'] = 'size';
        $first = reset($group);
        if ($optionIds) {
            $row['_mode'] = 'grouped';
            // Option ids, not labels: renaming a color in the admin does not change the Unique ID
            $row['id'] = $row['id'] === null ? null : $this->uniqueId->getForVariant($parent, $optionIds);
            $row['name'] = $this->variantName($row['name'], $first, $options);
            $colors = array_intersect($options, $this->config->getCodes('color'));
            $row['color'] = $colors ? $this->labels($first, $colors, ' / ') : null;
            $row['link'] = $this->withOptions($row['link'], $optionIds);
        }
        // The images of the color; for sizes only, those of the parent unless it has none
        if ($optionIds || ($row['image'] ?? null) === null) {
            foreach ($group as $child) {
                $image = $this->value('image', $child);
                if ($image !== null) {
                    $row['image'] = $image;
                    $row['additional_image'] = $this->value('additional_image', $child);
                    break;
                }
            }
        }
        $row['color'] = $row['color'] ?? $this->value('color', $first);
        $prices = $quantities = $availabilities = [];
        foreach ($group as $child) {
            $prices[] = (float)$this->value('price_with_vat', $child);
            $quantities[] = (int)$this->value('quantity', $child);
            $availabilities[] = (string)$this->value('availability', $child);
        }
        $row['price_with_vat'] = number_format(min($prices), 2, '.', '');
        $row['quantity'] = (string)min(Field\Quantity::MAX, array_sum($quantities));
        $row['availability'] = $this->fastest($availabilities);
        $row['shipping'] = $this->value('shipping', $first) ?? $row['shipping'] ?? null;
        if (count($group) === 1) {
            // One product behind the row: its own identifiers are the precise ones
            $row['mpn'] = $this->value('mpn', $first) ?? $row['mpn'];
            $row['ean'] = $this->value('ean', $first) ?? $row['ean'];
        } else {
            $row['mpn'] = $row['mpn'] ?? $this->value('mpn', $first);
            $row['ean'] = $row['ean'] ?? $this->value('ean', $first);
        }
        $sizeLabels = $variations = [];
        foreach ($group as $child) {
            // Products use one size attribute; should a product have two (e.g. EU and US), Skroutz wants "42/9"
            $size = $this->labels($child, $sizes, '/');
            $sizeOptions = [];
            foreach ($sizes as $attributeId => $code) {
                $sizeOptions[$attributeId] = (string)$child->getData($code);
            }
            $sizeLabels[] = $size;
            $variations[] = array_filter([
                'variationid' => $this->uniqueId->getForVariation($child),
                'link' => $this->withOptions($row['link'], $optionIds + $sizeOptions),
                'availability' => $this->value('availability', $child),
                'manufacturersku' => $this->value('mpn', $child) ?? $row['mpn'],
                'ean' => $this->value('ean', $child),
                'price_with_vat' => $this->value('price_with_vat', $child),
                'size' => $size,
                'quantity' => $this->value('quantity', $child),
                'outlet' => $this->value('outlet', $child) ?? $row['outlet'] ?? null,
            ], [$this, 'isFilled']);
        }
        $sizeLabels = array_values(array_unique(array_filter($sizeLabels, [$this, 'isFilled'])));
        $row['size'] = implode(',', $sizeLabels);
        if ($nest) {
            $row['variations'] = $variations;
        }

        return $row;
    }

    /**
     * The number of different sizes among the children of a configurable.
     *
     * @param Product[] $children
     * @param string[] $sizes size attribute codes
     * @return int
     */
    private function countSizes(array $children, array $sizes): int
    {
        $found = [];
        foreach ($children as $child) {
            $optionIds = [];
            foreach ($sizes as $code) {
                $optionIds[] = (string)$child->getData($code);
            }
            $found[implode('/', $optionIds)] = true;
        }

        return count($found);
    }

    /**
     * One row per child, for configurables without a size attribute (color only, capacity...).
     *
     * @param Product $parent
     * @param Product[] $children
     * @param string[] $super attribute id => code
     * @return array[]
     */
    private function childRows(Product $parent, array $children, array $super): array
    {
        $parentRow = $this->row($parent);
        $rows = [];
        foreach ($children as $child) {
            $row = $this->row($child);
            if (($row['image'] ?? null) === null) {
                // No image of its own: the parent's image and gallery
                $row['image'] = $parentRow['image'] ?? null;
                $row['additional_image'] = $parentRow['additional_image'] ?? null;
            }
            foreach ($parentRow as $name => $value) {
                if (!$this->isFilled($row[$name] ?? null) && !in_array($name, self::OWN_FIELDS, true)) {
                    $row[$name] = $value;
                }
            }
            // Spec by spec: the parent usually holds most of them
            if (is_array($parentRow['specifications'] ?? null) && is_array($row['specifications'])) {
                $row['specifications'] = array_replace($parentRow['specifications'], $row['specifications']);
            }
            $options = [];
            foreach ($super as $attributeId => $code) {
                $options[$attributeId] = (string)$child->getData($code);
            }
            $row['name'] = $this->variantName($parentRow['name'], $child, $super);
            $row['link'] = $this->withOptions($parentRow['link'], $options);
            $row['_mode'] = 'child';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The value of one field, null when the field is not registered.
     *
     * @param string $name
     * @param Product $product
     * @return mixed
     */
    private function value(string $name, Product $product)
    {
        return isset($this->fields[$name]) ? $this->fields[$name]->getValue($product) : null;
    }

    /**
     * The name of a grouped or child row: the parent name and the option values, as "Variant Name" sets.
     *
     * "values": "T-shirt Red M"; "labels": "T-shirt Color: Red, Size: M"; "none": "T-shirt".
     *
     * @param string|null $name the parent name
     * @param Product $child
     * @param string[] $codes the attributes the row differs by
     * @return string|null
     */
    private function variantName(?string $name, Product $child, array $codes): ?string
    {
        $mode = $this->config->get('feed_products/variant_name');
        if ($name === null || $mode === 'none') {
            return $name;
        }
        $parts = [];
        foreach ($codes as $code) {
            $value = $this->attributeValue->get($child, $code);
            if ($value !== null && $value !== '') {
                $parts[] = $mode === 'labels' ? $this->attributeValue->getLabel($code) . ': ' . $value : $value;
            }
        }

        return $parts ? $name . ' ' . implode($mode === 'labels' ? ', ' : ' ', $parts) : $name;
    }

    /**
     * The option labels of a product for some attributes.
     *
     * @param Product $product
     * @param string[] $codes
     * @param string $separator
     * @return string|null
     */
    private function labels(Product $product, array $codes, string $separator): ?string
    {
        $labels = [];
        foreach ($codes as $code) {
            $labels[] = $this->attributeValue->get($product, $code);
        }
        $labels = array_filter($labels, [$this, 'isFilled']);

        return $labels ? implode($separator, $labels) : null;
    }

    /**
     * A product link that preselects configurable options on the product page ("#93=50&144=167").
     *
     * @param string|null $link
     * @param string[] $options attribute id => option id
     * @return string|null
     */
    private function withOptions(?string $link, array $options): ?string
    {
        if ($link === null || !$options) {
            return $link;
        }
        $hash = [];
        foreach ($options as $attributeId => $optionId) {
            $hash[] = $attributeId . '=' . $optionId;
        }

        return strtok($link, '#') . '#' . implode('&', $hash);
    }

    /**
     * The fastest of several availability texts, in the order Skroutz lists them.
     *
     * @param string[] $availabilities
     * @return string|null
     */
    private function fastest(array $availabilities): ?string
    {
        $availabilities = array_values(array_filter($availabilities, [$this, 'isFilled']));
        if (!$availabilities) {
            return null;
        }
        usort($availabilities, function (string $a, string $b): int {
            return $this->rank($a) <=> $this->rank($b);
        });

        return $availabilities[0];
    }

    /**
     * The position of an availability text among the Skroutz values, unknown texts last.
     *
     * @param string $availability
     * @return int
     */
    private function rank(string $availability): int
    {
        $rank = array_search($availability, AvailabilitySource::VALUES, true);

        return $rank === false ? PHP_INT_MAX : $rank;
    }

    /**
     * Whether a field value is set.
     *
     * @param mixed $value
     * @return bool
     */
    private function isFilled($value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
