<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The availability texts of the Skroutz specification, fastest first, and optionally "Hide from Skroutz".
 */
class Availability implements OptionSourceInterface
{
    public const VALUES = [
        'Άμεσα διαθέσιμο',
        'Διαθέσιμο από 1 έως 3 ημέρες',
        'Διαθέσιμο από 4 έως 6 ημέρες',
        'Διαθέσιμο από 4 έως 10 ημέρες',
        'Διαθέσιμο από 7 έως 12 ημέρες',
        'Διαθέσιμο από 10 έως 30 ημέρες',
    ];

    /** The product is not listed */
    public const HIDE = '__hide';

    /**
     * @var bool
     */
    private $hide;

    /**
     * @param bool $hide whether "Hide from Skroutz" is an option
     */
    public function __construct(bool $hide = false)
    {
        $this->hide = $hide;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::VALUES as $value) {
            $options[] = ['value' => $value, 'label' => $value];
        }
        if ($this->hide) {
            $options[] = ['value' => self::HIDE, 'label' => __('Hide from Skroutz')];
        }

        return $options;
    }
}
