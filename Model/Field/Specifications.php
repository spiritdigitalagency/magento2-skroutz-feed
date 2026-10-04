<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Spirit\SkroutzFeed\Model\AttributeValue;
use Spirit\SkroutzFeed\Model\Config;

/**
 * The product characteristics, written as <specifications><spec name="Label">value</spec></specifications>.
 *
 * Skroutz takes free names: the label of each attribute in the store view, unless di.xml renames it
 * (argument "labels", attribute code => name). To add specifications computed per product, or to drop some,
 * add an afterGetValue() plugin: it receives the name => value array and the product.
 */
class Specifications implements FieldInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var AttributeValue
     */
    private $attributeValue;

    /**
     * @var string[]
     */
    private $labels;

    /**
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param string[] $labels attribute code => specification name, instead of the attribute label
     */
    public function __construct(
        Config $config,
        AttributeValue $attributeValue,
        array $labels = []
    ) {
        $this->config = $config;
        $this->attributeValue = $attributeValue;
        $this->labels = $labels;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $specs = [];
        foreach ($this->config->getSpecifications() as $code) {
            $value = $this->attributeValue->get($product, $code);
            if ($value !== null) {
                $specs[$this->labels[$code] ?? $this->attributeValue->getLabel($code)] = $value;
            }
        }

        return $specs ?: null;
    }
}
