<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Config\Backend;

use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\CronException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * The "Schedule" setting. It refuses a cron expression Magento cron cannot read,
 * which would stop the feed silently.
 */
class Schedule extends Value
{
    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ScheduleFactory $scheduleFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ScheduleFactory $scheduleFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->scheduleFactory = $scheduleFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        $expression = trim((string)preg_replace('/\s+/', ' ', (string)$this->getValue()));
        if ($expression !== '' && !$this->runsWithinADay($expression)) {
            throw new LocalizedException(__(
                '"%1" does not generate the feed in the next 24 hours, and Skroutz requires an update every day. '
                . 'For example 50 6-23 * * *',
                $expression
            ));
        }
        $this->setValue($expression);

        return parent::beforeSave();
    }

    /**
     * Whether Magento cron reads the expression and runs it at least once in the next 24 hours.
     *
     * Catches what cron would otherwise ignore or never match, like "every hour" or "99 * * * *".
     *
     * @param string $expression
     * @return bool
     */
    private function runsWithinADay(string $expression): bool
    {
        $minute = (int)(floor(time() / 60) * 60);
        try {
            $schedule = $this->scheduleFactory->create()->setCronExpr($expression);
            for ($i = 0; $i < 1440; $i++) {
                if ($schedule->setScheduledAt($minute + $i * 60)->trySchedule()) {
                    return true;
                }
            }
        } catch (CronException $e) {
            return false;
        }

        return false;
    }
}
