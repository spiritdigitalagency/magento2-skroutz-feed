<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Spirit\SkroutzFeed\Model\Field\Availability;
use Spirit\SkroutzFeed\Model\Product\Attribute\Source\Availability as AvailabilitySource;
use Spirit\SkroutzFeed\Model\ProductLoader;
use Spirit\SkroutzFeed\Model\Stock;

/**
 * Per product settings in a "Skroutz" group of every attribute set, "Exclude from Skroutz" and the
 * availability text of each stock state. All can be changed in bulk with Catalog > Products > Update attributes.
 */
class AddProductAttributes implements DataPatchInterface, PatchRevertableInterface
{
    private const AVAILABILITY_LABELS = [
        Stock::IN_STOCK => 'Skroutz Availability (In Stock)',
        Stock::BACKORDER => 'Skroutz Availability (On Backorder)',
        Stock::OUT_OF_STOCK => 'Skroutz Availability (Out of Stock)',
    ];

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $common = [
            'group' => 'Skroutz',
            'global' => Attribute::SCOPE_WEBSITE,
            'required' => false,
            'user_defined' => true,
            'visible' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'apply_to' => 'simple,configurable',
        ];
        $eavSetup->addAttribute(Product::ENTITY, ProductLoader::EXCLUDE_ATTRIBUTE, $common + [
            'type' => 'int',
            'label' => 'Exclude from Skroutz',
            'input' => 'boolean',
            'source' => Boolean::class,
            'default' => '0',
            'sort_order' => 10,
        ]);
        $sortOrder = 20;
        foreach (Availability::ATTRIBUTES as $state => $code) {
            $eavSetup->addAttribute(Product::ENTITY, $code, $common + [
                'type' => 'varchar',
                'label' => self::AVAILABILITY_LABELS[$state],
                'input' => 'select',
                'source' => AvailabilitySource::class,
                'sort_order' => $sortOrder += 10,
            ]);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, ProductLoader::EXCLUDE_ATTRIBUTE);
        foreach (Availability::ATTRIBUTES as $code) {
            $eavSetup->removeAttribute(Product::ENTITY, $code);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
