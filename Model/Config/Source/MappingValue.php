<?php

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MappingValue implements OptionSourceInterface
{

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 0,
                'label' => 'Attribute value'
            ],
            [
                'value' => 1,
                'label' => 'Fixed value'
            ]
        ];
    }
}
