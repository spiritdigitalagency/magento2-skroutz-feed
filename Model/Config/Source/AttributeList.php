<?php

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class AttributeList implements OptionSourceInterface
{

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * AttributeList constructor.
     * @param  CollectionFactory  $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToSelect('attribute_code')
                   ->addFieldToSelect('frontend_label')
                   ->addFieldToSelect('frontend_input')
                   ->addVisibleFilter()
                   ->removePriceFilter()
                   ->addFieldToFilter('is_user_defined', 1);
        $attributesArray = [
            [
                'value' => '',
                'label' => '-- Empty --'
            ],
            [
                'value' => 'entity_id',
                'label' => 'Product ID'
            ],
            [
                'value' => 'sku',
                'label' => 'SKU'
            ]
        ];
        foreach ($collection->getItems() as $attribute) {
            $attributeData = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel().' ('.$attribute->getAttributeCode().')'
            ];
            $attributesArray[] = $attributeData;
        }

        return $attributesArray;
    }
}
