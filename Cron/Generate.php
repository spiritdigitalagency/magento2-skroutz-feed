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
 * Generates the feeds on schedule, and those requested with "Generate now".
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
     * The scheduled run: the feeds of every enabled website.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->generate(null);
    }

    /**
     * Every minute: the feeds requested with "Generate now".
     *
     * @return void
     */
    public function executeRequests(): void
    {
        $requested = $this->state->takeRequests();
        if ($requested) {
            $this->generate($requested);
        }
    }

    /**
     * Generate the feeds of the enabled websites.
     *
     * @param int[]|null $websiteIds null for all
     * @return void
     */
    private function generate(?array $websiteIds): void
    {
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int)$website->getId();
            if (!$this->config->isEnabled($websiteId)
                || ($websiteIds !== null && !in_array($websiteId, $websiteIds, true))
            ) {
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
