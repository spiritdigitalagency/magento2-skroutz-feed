<?php

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ManufacturerMapping implements OptionSourceInterface
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
