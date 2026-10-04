<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Framework\FlagManager;

/**
 * The last generation report of each store and the generations requested from the admin.
 */
class State
{
    private const REPORT = 'spirit_skroutzfeed_report_';
    private const REQUESTS = 'spirit_skroutzfeed_requests';

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @param FlagManager $flagManager
     */
    public function __construct(FlagManager $flagManager)
    {
        $this->flagManager = $flagManager;
    }

    /**
     * The report of the last generation of a store.
     *
     * @param int $storeId
     * @return array|null
     */
    public function getReport(int $storeId): ?array
    {
        $report = $this->flagManager->getFlagData(self::REPORT . $storeId);

        return is_array($report) ? $report : null;
    }

    /**
     * Save the report of a generation.
     *
     * @param int $storeId
     * @param array $report
     * @return void
     */
    public function saveReport(int $storeId, array $report): void
    {
        $this->flagManager->saveFlag(self::REPORT . $storeId, $report);
    }

    /**
     * Ask cron to generate the feed of some stores within the next minute.
     *
     * @param int[] $storeIds
     * @return void
     */
    public function request(array $storeIds): void
    {
        $this->flagManager->saveFlag(
            self::REQUESTS,
            array_values(array_unique(array_merge($this->getRequests(), $storeIds)))
        );
    }

    /**
     * The stores waiting for a requested generation.
     *
     * @return int[]
     */
    public function getRequests(): array
    {
        return array_map('intval', (array)$this->flagManager->getFlagData(self::REQUESTS));
    }

    /**
     * Take the requested stores, clearing the requests.
     *
     * @return int[]
     */
    public function takeRequests(): array
    {
        $requests = $this->getRequests();
        if ($requests) {
            $this->flagManager->deleteFlag(self::REQUESTS);
        }

        return $requests;
    }
}
