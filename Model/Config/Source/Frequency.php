<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * How often cron regenerates the feed, in minutes.
 */
class Frequency implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 30, 'label' => __('Every 30 minutes')],
            ['value' => 60, 'label' => __('Every hour')],
            ['value' => 120, 'label' => __('Every 2 hours')],
            ['value' => 180, 'label' => __('Every 3 hours')],
            ['value' => 360, 'label' => __('Every 6 hours')],
            ['value' => 720, 'label' => __('Every 12 hours')],
            ['value' => 1440, 'label' => __('Once a day')],
            ['value' => 0, 'label' => __('Only with "Generate now" or the CLI command')],
        ];
    }
}
