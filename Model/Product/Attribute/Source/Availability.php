<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Product\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Spirit\SkroutzFeed\Model\Config\Source\Availability as AvailabilitySource;

/**
 * Options of the per product availability attributes. The Skroutz texts are the stored values,
 * so the feed writes them as they are.
 */
class Availability extends AbstractSource
{
    /**
     * @inheritdoc
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [['value' => '', 'label' => __('Use the configuration')]];
            foreach (AvailabilitySource::VALUES as $value) {
                $this->_options[] = ['value' => $value, 'label' => $value];
            }
            $this->_options[] = ['value' => AvailabilitySource::HIDE, 'label' => __('Hide from Skroutz')];
        }

        return $this->_options;
    }
}
