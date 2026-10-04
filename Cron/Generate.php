<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Spirit\SkroutzFeed\Model\Config;
use Spirit\SkroutzFeed\Model\Generator;
use Spirit\SkroutzFeed\Model\State;

/**
 * Every minute: generate the feeds that are due by frequency or were requested from the admin.
 */
class Generate
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var State
     */
    private $state;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param State $state
     * @param Generator $generator
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config,
        State $state,
        Generator $generator,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->state = $state;
        $this->generator = $generator;
        $this->logger = $logger;
    }

    /**
     * Generate the due feeds.
     *
     * @return void
     */
    public function execute(): void
    {
        $requested = $this->state->takeRequests();
        $frequency = $this->config->getFrequency() * 60;
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int)$website->getId();
            if (!$this->config->isEnabled($websiteId)) {
                continue;
            }
            $report = $this->state->getReport($websiteId);
            // ponytail: 30s of slack so a feed due "every hour" does not slip a minute every run
            $due = in_array($websiteId, $requested, true)
                || ($frequency > 0 && (!$report || time() - (int)$report['started'] >= $frequency - 30));
            if (!$due) {
                continue;
            }
            try {
                $this->generator->generate($website);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
