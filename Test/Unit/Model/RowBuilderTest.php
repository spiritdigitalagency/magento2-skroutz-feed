<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;
use Spirit\SkroutzFeed\Model\AttributeValue;
use Spirit\SkroutzFeed\Model\Config;
use Spirit\SkroutzFeed\Model\Config\Source\Availability;
use Spirit\SkroutzFeed\Model\Field\FieldInterface;
use Spirit\SkroutzFeed\Model\RowBuilder;
use Spirit\SkroutzFeed\Model\UniqueId;

class RowBuilderTest extends TestCase
{
    /**
     * Color and size: one row per color, Unique ID "parent-option id", sizes nested.
     */
    public function testListsColorsWithNestedSizes(): void
    {
        $parent = $this->parent('10', 'Shoe', [93 => 'color', 144 => 'shoe_size']);
        $children = [
            $this->child('11', ['color' => 50, 'shoe_size' => 42], '30.00', '2'),
            $this->child('12', ['color' => 50, 'shoe_size' => 43], '32.00', '1'),
            $this->child('13', ['color' => 51, 'shoe_size' => 42], '31.00', '5'),
            $this->child('14', ['color' => 51, 'shoe_size' => 43], '31.00', '0', Availability::HIDE),
        ];
        $rows = $this->builder()->build($parent, $children);

        $this->assertCount(2, $rows);
        [$red, $blue] = $rows;
        $this->assertSame('10-50', $red['id']);
        $this->assertSame('Shoe label-50', $red['name']);
        $this->assertSame('label-50', $red['color']);
        $this->assertSame('https://x.gr/p10.html#93=50', $red['link']);
        $this->assertSame('30.00', $red['price_with_vat']);
        $this->assertSame('3', $red['quantity']);
        $this->assertSame('label-42,label-43', $red['size']);
        $this->assertSame('https://x.gr/p10.html#93=50&144=42', $red['variations'][0]['link']);
        $this->assertSame('11', $red['variations'][0]['variationid']);
        $this->assertCount(1, $blue['variations'], 'the hidden size is left out');
    }

    /**
     * Products use different size attributes: each configurable is grouped by the one it has.
     */
    public function testUsesTheSizeAttributeOfEachProduct(): void
    {
        $parent = $this->parent('20', 'Shirt', [144 => 'clothing_size']);
        $rows = $this->builder()->build($parent, [
            $this->child('21', ['clothing_size' => 7], '20.00', '1'),
            $this->child('22', ['clothing_size' => 8, 'image' => 'https://x.gr/22.jpg'], '20.00', '1'),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('20', $rows[0]['id']);
        $this->assertSame('label-7,label-8', $rows[0]['size']);
        $this->assertCount(2, $rows[0]['variations']);
        $this->assertSame('https://x.gr/22.jpg', $rows[0]['image'], 'a parent without an image takes one of a child');
    }

    /**
     * Size and another option (fit): one row per fit, sizes nested, not one row with the fits mixed.
     */
    public function testGroupsOtherOptionsLikeColors(): void
    {
        $parent = $this->parent('30', 'Jeans', [150 => 'fit', 144 => 'clothing_size']);
        $rows = $this->builder()->build($parent, [
            $this->child('31', ['fit' => 1, 'clothing_size' => 7], '50.00', '1'),
            $this->child('32', ['fit' => 1, 'clothing_size' => 8], '50.00', '1'),
            $this->child('33', ['fit' => 2, 'clothing_size' => 7], '55.00', '1'),
        ]);

        $this->assertSame(['30-1', '30-2'], array_column($rows, 'id'));
        $this->assertNull($rows[0]['color'], 'a fit is not a color');
        $this->assertCount(2, $rows[0]['variations']);
    }

    /**
     * "One size" items: with the setting, each color is a plain product with its size and no variations.
     */
    public function testDoesNotNestASingleSize(): void
    {
        $parent = $this->parent('40', 'Hat', [93 => 'color', 144 => 'clothing_size']);
        $children = [
            $this->child('41', ['color' => 50, 'clothing_size' => 99], '15.00', '3'),
            $this->child('42', ['color' => 51, 'clothing_size' => 99], '15.00', '0', Availability::HIDE),
        ];

        $rows = $this->builder(true)->build($parent, $children);
        $this->assertCount(1, $rows);
        $this->assertSame('40-50', $rows[0]['id']);
        $this->assertSame('label-99', $rows[0]['size']);
        $this->assertArrayNotHasKey('variations', $rows[0]);
        $this->assertSame('C41', $rows[0]['mpn'], 'one product behind the row: its own MPN');

        $this->assertArrayHasKey('variations', $this->builder(false)->build($parent, $children)[0]);
    }

    /**
     * No size attribute (capacity): one row per child, with its own Unique ID and the parent's details.
     */
    public function testListsChildrenWithoutSizes(): void
    {
        $parent = $this->parent('50', 'Phone', [200 => 'capacity']);
        $parent->setData('manufacturer', 'Acme');
        $parent->setData('ean', '5200000000001');
        $parent->setData('specifications', ['Material' => 'Aluminium', 'Sale' => 'Yes']);
        $parent->setData('image', 'https://x.gr/50.jpg');
        $parent->setData('additional_image', ['https://x.gr/50b.jpg']);
        $child = $this->child('51', ['capacity' => 128], '500.00', '3');
        $child->setData('specifications', ['Sale' => 'No']);
        $own = $this->child('52', ['capacity' => 256], '600.00', '1');
        $own->setData('image', 'https://x.gr/52.jpg');
        $rows = $this->builder()->build($parent, [$child, $own]);

        $this->assertCount(2, $rows);
        $this->assertSame('https://x.gr/50.jpg', $rows[0]['image'], 'no image of its own: those of the parent');
        $this->assertSame(['https://x.gr/50b.jpg'], $rows[0]['additional_image']);
        $this->assertSame('https://x.gr/52.jpg', $rows[1]['image']);
        $this->assertNull($rows[1]['additional_image'], 'never the gallery of the parent under its own image');
        $this->assertSame('51', $rows[0]['id']);
        $this->assertSame('Phone label-128', $rows[0]['name']);
        $this->assertSame('Acme', $rows[0]['manufacturer']);
        $this->assertNull($rows[0]['ean'], 'a variant never borrows the EAN of its parent');
        $this->assertSame('https://x.gr/p50.html#200=128', $rows[0]['link']);
        $this->assertSame(['Material' => 'Aluminium', 'Sale' => 'No'], $rows[0]['specifications']);
    }

    public function testHidesSimpleProducts(): void
    {
        $product = $this->product(['sku' => 'S', 'id' => '1', 'type_id' => 'simple',
            'availability' => Availability::HIDE]);

        $this->assertSame([], $this->builder()->build($product));
    }

    private function parent(string $id, string $name, array $super): Product
    {
        return $this->product(['sku' => 'P' . $id, 'id' => $id, 'type_id' => 'configurable', 'name' => $name,
            'link' => 'https://x.gr/p' . $id . '.html', 'skroutz_super_attributes' => $super]);
    }

    /**
     * A child: option ids by attribute code (their labels are "label-<id>") and its field values.
     */
    private function child(
        string $id,
        array $options,
        string $price,
        string $qty,
        string $availability = 'Άμεσα διαθέσιμο'
    ): Product {
        return $this->product($options + ['sku' => 'C' . $id, 'id' => $id, 'type_id' => 'simple', 'mpn' => 'C' . $id,
            'final_price' => $price, 'price_with_vat' => $price, 'quantity' => $qty, 'availability' => $availability]);
    }

    private function product(array $data): Product
    {
        // A real product without its constructor; the real getSku() asks the product type
        $product = new class extends Product {
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            public function __construct()
            {
            }

            public function getSku()
            {
                return $this->getData('sku');
            }
        };
        $product->setData($data);

        return $product;
    }

    /**
     * A builder whose fields return the product data of the same name.
     */
    private function builder(bool $singleSize = false): RowBuilder
    {
        $config = $this->createStub(Config::class);
        $config->method('getCodes')->willReturnMap([
            ['color', ['color']],
            ['size', ['shoe_size', 'clothing_size']],
        ]);
        $config->method('get')->willReturnMap([
            ['feed_products/variant_name', null, 'values'],
            ['feed_products/single_size', null, $singleSize ? '1' : '0'],
        ]);
        $attributeValue = $this->createStub(AttributeValue::class);
        $attributeValue->method('get')->willReturnCallback(function (Product $product, string $code) {
            $value = $product->getData($code);
            return $value === null ? null : 'label-' . $value;
        });
        $uniqueId = $this->createStub(UniqueId::class);
        $uniqueId->method('getForVariant')->willReturnCallback(function (Product $parent, array $options) {
            return implode('-', array_merge([$parent->getData('id')], $options));
        });
        $uniqueId->method('getForVariation')->willReturnCallback(function (Product $child) {
            return (string)$child->getData('id');
        });
        $fields = [];
        foreach (['id', 'name', 'link', 'image', 'price_with_vat', 'availability', 'manufacturer', 'mpn', 'ean',
                     'color', 'quantity', 'specifications', 'additional_image'] as $name) {
            $fields[$name] = new class($name) implements FieldInterface {
                /**
                 * @var string
                 */
                private $name;

                public function __construct(string $name)
                {
                    $this->name = $name;
                }

                public function getValue(Product $product)
                {
                    $value = $product->getData($this->name);
                    return $value === null || is_array($value) ? $value : (string)$value;
                }
            };
        }

        return new RowBuilder($config, $attributeValue, $uniqueId, $fields);
    }
}
