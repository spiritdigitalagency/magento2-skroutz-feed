<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Feed settings. One feed per website, generated from one of its store views.
 *
 * During generation the settings are read for that store view, so website values and their defaults apply.
 */
class Config
{
    public const SECTION = 'spirit_skroutz';

    /** Mapping value: write the "Fixed value" field instead of an attribute */
    public const FIXED = '__fixed';

    /** Mapping value: let the module compute the field */
    public const AUTO = '__auto';

    /** Specifications: the attributes shown on the product page ("Visible on Catalog Pages on Storefront") */
    public const SPECS_STOREFRONT = 'storefront';

    /** Specifications: the attributes shown on the product page, except those selected in the configuration */
    public const SPECS_STOREFRONT_EXCEPT = 'storefront_except';

    /** Specifications: the attributes selected in the configuration */
    public const SPECS_SELECTED = 'selected';

    /** Mapped fields that may list several attributes (the first with a value is used) */
    public const MULTI_FIELDS = ['color', 'size'];

    /** Attributes never exported as specifications: Skroutz has a field for them, or they are not specs */
    private const NOT_SPECS = ['sku', 'name', 'description', 'short_description', 'price', 'special_price', 'cost',
        'weight', 'manufacturer', 'color', 'size', 'country_of_manufacture', 'url_key', 'meta_title',
        'news_from_date', 'news_to_date', 'special_from_date', 'special_to_date'];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @var int|null
     */
    private $storeId;

    /**
     * @var array<string, mixed>
     */
    private $cache = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param AttributeCollectionFactory $attributeCollectionFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EavConfig $eavConfig,
        StoreManagerInterface $storeManager,
        AttributeCollectionFactory $attributeCollectionFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->eavConfig = $eavConfig;
        $this->storeManager = $storeManager;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * Pin the store view whose settings the next reads return.
     *
     * @param int $storeId
     * @return void
     */
    public function setStore(int $storeId): void
    {
        $this->storeId = $storeId;
        $this->cache = [];
    }

    /**
     * A setting of the Skroutz section, memoized: the feed reads the same few paths for every product.
     *
     * @param string $path e.g. "feed_mapping/name"
     * @param int|null $storeId defaults to the pinned store view
     * @return string
     */
    public function get(string $path, ?int $storeId = null): string
    {
        $storeId = $storeId ?? $this->storeId;
        $key = $storeId . '/' . $path;
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = trim((string)$this->scopeConfig->getValue(
                self::SECTION . '/' . $path,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));
        }

        return $this->cache[$key];
    }

    /**
     * A setting of a website.
     *
     * @param string $path
     * @param int $websiteId
     * @return string
     */
    public function getWebsiteValue(string $path, int $websiteId): string
    {
        return trim((string)$this->scopeConfig->getValue(
            self::SECTION . '/' . $path,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        ));
    }

    /**
     * Whether the feed of a website is enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isEnabled(int $websiteId): bool
    {
        return (bool)$this->getWebsiteValue('feed/status', $websiteId);
    }

    /**
     * The store view the feed of a website is generated from: its language, prices and URLs.
     *
     * @param WebsiteInterface $website
     * @return StoreInterface
     */
    public function getStore(WebsiteInterface $website): StoreInterface
    {
        $storeId = (int)$this->getWebsiteValue('feed/store', (int)$website->getId());
        foreach ($this->storeManager->getStores() as $store) {
            if ((int)$store->getId() === $storeId && (int)$store->getWebsiteId() === (int)$website->getId()) {
                return $store;
            }
        }

        return $this->storeManager->getStore((int)$this->storeManager->getGroup(
            (string)$website->getDefaultGroupId()
        )->getDefaultStoreId());
    }

    /**
     * The feed file name without extension, the website code when none is set.
     *
     * @param WebsiteInterface $website
     * @return string
     */
    public function getFileName(WebsiteInterface $website): string
    {
        $name = (string)preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '',
            $this->getWebsiteValue('feed/filename', (int)$website->getId())
        );

        return $name !== '' ? $name : (string)$website->getCode();
    }

    /**
     * Another enabled website writing its feed to the same file, if any.
     *
     * @param WebsiteInterface $website
     * @return WebsiteInterface|null
     */
    public function getFileNameClash(WebsiteInterface $website): ?WebsiteInterface
    {
        $name = $this->getFileName($website);
        foreach ($this->storeManager->getWebsites() as $other) {
            if ((int)$other->getId() !== (int)$website->getId()
                && $this->isEnabled((int)$other->getId())
                && $this->getFileName($other) === $name
            ) {
                return $other;
            }
        }

        return null;
    }

    /**
     * The source of a feed field: an attribute code, one of the special values, or '' for "not exported".
     *
     * Color and size may list several attribute codes, separated by commas.
     *
     * @param string $field
     * @return string
     */
    public function getMapping(string $field): string
    {
        return $this->get('feed_mapping/' . $field);
    }

    /**
     * The attribute codes of a field that takes several attributes (color, size).
     *
     * @param string $field
     * @return string[]
     */
    public function getCodes(string $field): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->getMapping($field)))));
    }

    /**
     * The fixed value of a feed field.
     *
     * @param string $field
     * @return string
     */
    public function getFixed(string $field): string
    {
        return $this->get('feed_mapping/' . $field . '_fixed');
    }

    /**
     * Codes of the existing attributes the feed reads, to select them in the product collection.
     *
     * @return string[]
     */
    public function getAttributes(): array
    {
        $lists = [[
            'name', 'image', 'visibility', 'weight', 'price', 'special_price', 'special_from_date',
            'special_to_date', 'tax_class_id', ProductLoader::EXCLUDE_ATTRIBUTE,
        ]];
        foreach (['id', 'name', 'description', 'category', 'manufacturer', 'mpn', 'ean', 'color', 'size', 'vat',
                     'shipping', 'wholesale_price', 'season', 'size_fit', 'outlet', 'author', 'expiration_date',
                     'country_of_origin'] as $field) {
            $lists[] = $this->getCodes($field);
        }
        $lists[] = $this->getSpecifications();
        $lists[] = Field\Availability::ATTRIBUTES;

        return array_values(array_filter(array_unique(array_merge(...$lists)), [$this, 'isAttribute']));
    }

    /**
     * Attribute codes exported as specifications.
     *
     * @return string[]
     */
    public function getSpecifications(): array
    {
        $key = $this->storeId . '/specifications';
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $mode = $this->get('feed_mapping/specifications_mode');
        $codes = [];
        if ($mode === self::SPECS_STOREFRONT || $mode === self::SPECS_STOREFRONT_EXCEPT) {
            $collection = $this->attributeCollectionFactory->create()
                ->addFieldToSelect('attribute_code')
                ->addFieldToFilter('is_visible_on_front', '1');
            foreach ($collection as $attribute) {
                $codes[] = (string)$attribute->getAttributeCode();
            }
            if ($mode === self::SPECS_STOREFRONT_EXCEPT) {
                $codes = array_diff($codes, $this->getCodes('specifications'));
            }
        } elseif ($mode === self::SPECS_SELECTED) {
            $codes = $this->getCodes('specifications');
        }
        $mapped = [];
        foreach (['name', 'description', 'manufacturer', 'mpn', 'ean', 'color', 'size', 'season', 'size_fit',
                     'outlet', 'author', 'expiration_date', 'country_of_origin', 'wholesale_price'] as $field) {
            $mapped[] = $this->getCodes($field);
        }
        $this->cache[$key] = array_values(array_diff($codes, self::NOT_SPECS, array_merge([], ...$mapped)));

        return $this->cache[$key];
    }

    /**
     * Whether a mapping value is an existing product attribute.
     *
     * @param string $code
     * @return bool
     */
    public function isAttribute(string $code): bool
    {
        if ($code === '' || strpos($code, '__') === 0) {
            return false;
        }
        if ($code === 'entity_id') {
            return true;
        }
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);

        return (bool)$attribute->getId();
    }
}
