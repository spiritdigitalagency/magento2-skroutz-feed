<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Fixed options declared in di.xml (virtual types).
 */
class Options implements OptionSourceInterface
{
    /**
     * @var string[]
     */
    private $options;

    /**
     * @param string[] $options value => label
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $result = [];
        foreach ($this->options as $value => $label) {
            $result[] = ['value' => $value, 'label' => __($label)];
        }

        return $result;
    }
}
