<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Field;

use Magento\Catalog\Model\Product;
use Spirit\SkroutzFeed\Model\AttributeValue;
use Spirit\SkroutzFeed\Model\CategoryTree;
use Spirit\SkroutzFeed\Model\Config;

/**
 * The category path, from the deepest category of the product or a mapped attribute.
 */
class Category extends Mapped
{
    /**
     * @var CategoryTree
     */
    private $categoryTree;

    /**
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param CategoryTree $categoryTree
     * @param string $field
     */
    public function __construct(
        Config $config,
        AttributeValue $attributeValue,
        CategoryTree $categoryTree,
        string $field = 'category'
    ) {
        parent::__construct($config, $attributeValue, $field);
        $this->categoryTree = $categoryTree;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        if ($this->config->getMapping($this->field) !== Config::AUTO) {
            return parent::getValue($product);
        }

        return $this->categoryTree->getPath($product->getData(CategoryTree::KEY) ?: []);
    }
}
