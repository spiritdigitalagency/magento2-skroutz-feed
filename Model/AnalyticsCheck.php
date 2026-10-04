<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

/**
 * Checks that Skroutz Analytics (Spirit_Skroutz) reports orders with the Unique IDs of the feed.
 *
 * Skroutz matches the product_id of each ordered item to the Unique ID of a feed product, and the product
 * reviews widget does the same. An ID that is not in the feed is an order Skroutz cannot attribute.
 */
class AnalyticsCheck
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var UniqueId
     */
    private $uniqueId;

    /**
     * @param Config $config
     * @param UniqueId $uniqueId
     */
    public function __construct(
        Config $config,
        UniqueId $uniqueId
    ) {
        $this->config = $config;
        $this->uniqueId = $uniqueId;
    }

    /**
     * What Skroutz Analytics sends in a store view, null when it is not installed or not enabled.
     *
     * @param int $storeId
     * @return string|null
     */
    public function getSummary(int $storeId): ?string
    {
        $code = $this->uniqueId->getAnalyticsAttributeCode($storeId);
        if ($code === null) {
            return null;
        }

        return (string)($this->sendsParent($storeId)
            ? __('Skroutz Analytics sends "%1" as Unique ID, of the parent for configurable products.', $code)
            : __(
                'Skroutz Analytics sends "%1" as Unique ID, of the ordered variation for configurable products.',
                $code
            ));
    }

    /**
     * Warnings for a store view, empty when Analytics is not installed or not enabled.
     *
     * @param int $storeId the store view of the feed
     * @param mixed[]|null $report the last generation report, for warnings about the products actually listed
     * @return string[]
     */
    public function getWarnings(int $storeId, ?array $report = null): array
    {
        $analyticsId = $this->uniqueId->getAnalyticsAttributeCode($storeId);
        if ($analyticsId === null) {
            return [];
        }
        $warnings = [];
        $feedId = $this->uniqueId->getAttributeCode($storeId);
        if ($feedId !== $analyticsId) {
            $warnings[] = (string)__(
                'The feed sends "%1" as Unique ID but Skroutz Analytics sends "%2", so Skroutz cannot match '
                . 'your orders to your products. Use the same attribute in both.',
                $feedId,
                $analyticsId
            );
        }
        if ($this->config->get('feed_mapping/name', $storeId) !== 'name') {
            $warnings[] = (string)__(
                'Skroutz Analytics sends the product name of the order, while the feed name comes from "%1". '
                . 'Skroutz requires the two names to match.',
                $this->config->get('feed_mapping/name', $storeId)
            );
        }
        $modes = $report['modes'] ?? [];
        $sendsParent = $this->sendsParent($storeId);
        if (!$sendsParent && !empty($modes['size'])) {
            $warnings[] = (string)__(
                '%1 configurable products are listed once, with their sizes nested, under the parent Unique ID. '
                . 'Skroutz Analytics sends the Unique ID of the ordered variation: set its "Variation Unique IDs" '
                . 'to "Send parent Unique ID".',
                $modes['size']
            );
        }
        if ($sendsParent && !empty($modes['child'])) {
            $warnings[] = (string)__(
                '%1 children of configurable products without sizes are listed as products with their own Unique ID. '
                . 'Skroutz Analytics sends the parent Unique ID for them, so their orders are not matched.',
                $modes['child']
            );
        }
        if (!empty($modes['grouped'])) {
            $warnings[] = (string)__(
                '%1 colors of configurable products are listed with the Unique ID "parent ID-option ID". '
                . 'Skroutz Analytics does not send this ID yet, so orders of these products are not matched and '
                . 'the reviews widget shows nothing on their pages.',
                $modes['grouped']
            );
        }

        return $warnings;
    }

    /**
     * Whether Analytics reports configurable products with the Unique ID of the parent (its default).
     *
     * @param int $storeId
     * @return bool
     */
    private function sendsParent(int $storeId): bool
    {
        return $this->config->get('analytics/variation_unique_id', $storeId) !== '0';
    }
}
