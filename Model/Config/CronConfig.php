<?php

namespace Spirit\SkroutzFeed\Model\Config;

class CronConfig extends \Magento\Framework\App\Config\Value
{
    const CRON_STRING_PATH = 'crontab/default/jobs/spirit_skroutz_feed/schedule/cron_expr';

    const CRON_MODEL_PATH = 'crontab/default/jobs/spirit_skroutz_feed/schedule/run/model';

    protected $_configValueFactory;

    protected $_runModelPath = '';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    )
    {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function afterSave()
    {
        $time = $this->getData('groups/feed_settings/fields/time/value');
        $frequency = $this->getData('groups/feed_settings/fields/frequency/value');

        $frequencyHourly = \Spirit\SkroutzFeed\Model\Config\Source\Frequency::CRON_HOURLY;
        $frequencyWeekly = \Spirit\SkroutzFeed\Model\Config\Source\Frequency::CRON_WEEKLY;
        $frequencyMonthly = \Spirit\SkroutzFeed\Model\Config\Source\Frequency::CRON_MONTHLY;

        $cronExprArray = [
            intval($time[1]),   # Minute
            $frequency == $frequencyHourly ? '8-23' : intval($time[0]),    # Hour
            $frequency == $frequencyMonthly ? '1' : '*',    # Day of the Month
            '*',    # Month of the Year
            $frequency == $frequencyWeekly ? '1' : '*',     # Day of the Week
        ];

        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH
            )->save();
        } catch (\Exception $e) {
            throw new \Exception(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
