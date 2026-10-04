<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;
use Spirit\SkroutzFeed\Model\Config;

/**
 * The sources a feed field can be mapped to: product attributes, plus the special values a field allows.
 *
 * Variants are virtual types in di.xml (Unique ID, automatic fields, attribute lists).
 */
class Attribute implements OptionSourceInterface
{
    private const SKIPPED_INPUTS = ['gallery', 'media_image', 'image', 'weee'];

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var array<string, string>
     */
    private $special;

    /**
     * @var bool
     */
    private $fixed;

    /**
     * @var bool
     */
    private $none;

    /**
     * @param CollectionFactory $collectionFactory
     * @param array $special value => label, listed first
     * @param bool $fixed whether the field takes a fixed value
     * @param bool $none whether the field can be left out
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        array $special = [],
        bool $fixed = true,
        bool $none = true
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->special = $special;
        $this->fixed = $fixed;
        $this->none = $none;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        if ($this->none) {
            $options[] = ['value' => '', 'label' => __('-- Not exported --')];
        }
        foreach ($this->special as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }
        if ($this->fixed) {
            $options[] = ['value' => Config::FIXED, 'label' => __('Fixed value')];
        }
        $attributes = [];
        $collection = $this->collectionFactory->create()
            ->addFieldToSelect(['attribute_code', 'frontend_label', 'frontend_input'])
            ->addFieldToFilter('frontend_label', ['notnull' => true])
            ->setOrder('frontend_label', 'ASC');
        foreach ($collection as $attribute) {
            if (!in_array($attribute->getFrontendInput(), self::SKIPPED_INPUTS, true)) {
                $attributes[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')',
                ];
            }
        }
        if ($attributes) {
            $options[] = ['value' => $attributes, 'label' => __('Product attributes')];
        }

        return $options;
    }
}
