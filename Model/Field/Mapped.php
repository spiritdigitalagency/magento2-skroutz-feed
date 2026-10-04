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
 * A field the admin maps to a product attribute or to a fixed value (Field Mapping settings).
 */
class Mapped implements FieldInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var AttributeValue
     */
    protected $attributeValue;

    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    private $type;

    /**
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param string $field the setting under spirit_skroutz/feed_mapping
     * @param string $type text, decimal, date, ean or yesno
     */
    public function __construct(
        Config $config,
        AttributeValue $attributeValue,
        string $field,
        string $type = 'text'
    ) {
        $this->config = $config;
        $this->attributeValue = $attributeValue;
        $this->field = $field;
        $this->type = $type;
    }

    /**
     * @inheritdoc
     */
    public function getValue(Product $product)
    {
        $source = $this->config->getMapping($this->field);
        if ($source === '' || $source === Config::AUTO) {
            return null;
        }
        if ($source === Config::FIXED) {
            $value = $this->config->getFixed($this->field);
        } else {
            // Color and size may list several attributes: the first one the product has a value for
            foreach ($this->config->getCodes($this->field) as $code) {
                $value = $this->type === 'yesno'
                    ? (string)$product->getData($code)
                    : $this->attributeValue->get($product, $code);
                if ($value !== null && $value !== '') {
                    break;
                }
            }
        }

        return !isset($value) || $value === '' ? null : $this->format(trim($value));
    }

    /**
     * Format a value for its type, null when it is not valid.
     *
     * @param string $value
     * @return string|null
     */
    private function format(string $value): ?string
    {
        switch ($this->type) {
            case 'decimal':
                return is_numeric($value) ? number_format((float)$value, 2, '.', '') : null;
            case 'date':
                return substr($value, 0, 10);
            case 'ean':
                // Skroutz takes 8 to 14 digits (EAN-8, UPC, EAN-13, ISBN-13, GTIN-14)
                $digits = preg_replace('/\D+/', '', $value);
                return in_array(strlen($digits), [8, 12, 13, 14], true) ? $digits : null;
            case 'yesno':
                return in_array(strtolower($value), ['1', 'y', 'yes', 'true'], true) ? 'Y' : 'N';
            default:
                return $value;
        }
    }
}
