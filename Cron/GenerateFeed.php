<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Cron;

class GenerateFeed
{

    /**
     * @var \Spirit\SkroutzFeed\Helper\Feed
     */
    protected $feed;

    protected $logger;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Spirit\SkroutzFeed\Helper\Feed $feed,
        \Psr\Log\LoggerInterface $logger)
    {
        $this->feed = $feed;
        $this->logger = $logger;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->feed->generate();
        $this->logger->info("Cronjob GenerateFeed is executed.");
    }
}
