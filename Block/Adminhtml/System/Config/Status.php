<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Spirit\SkroutzFeed\Model\AnalyticsCheck;
use Spirit\SkroutzFeed\Model\Config;
use Spirit\SkroutzFeed\Model\State;

/**
 * The feed URLs, the last generation report and the "Generate now" button, per website of the scope.
 */
class Status extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Spirit_SkroutzFeed::system/config/status.phtml';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var State
     */
    private $state;

    /**
     * @var AnalyticsCheck
     */
    private $analyticsCheck;

    /**
     * @param Context $context
     * @param Config $config
     * @param State $state
     * @param AnalyticsCheck $analyticsCheck
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        State $state,
        AnalyticsCheck $analyticsCheck,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->state = $state;
        $this->analyticsCheck = $analyticsCheck;
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * The websites of the current scope with the feed enabled.
     *
     * @return WebsiteInterface[]
     */
    public function getWebsites(): array
    {
        $websiteId = (int)$this->getRequest()->getParam('website');
        $websites = [];
        foreach ($this->_storeManager->getWebsites() as $website) {
            $id = (int)$website->getId();
            if ((!$websiteId || $id === $websiteId) && $this->config->isEnabled($id)) {
                $websites[] = $website;
            }
        }

        return $websites;
    }

    /**
     * The store view the feed of a website is generated from.
     *
     * @param WebsiteInterface $website
     * @return StoreInterface
     */
    public function getStore(WebsiteInterface $website): StoreInterface
    {
        return $this->config->getStore($website);
    }

    /**
     * The last report of a website.
     *
     * @param WebsiteInterface $website
     * @return array|null
     */
    public function getReport(WebsiteInterface $website): ?array
    {
        return $this->state->getReport((int)$website->getId());
    }

    /**
     * Whether a generation of the website waits for cron.
     *
     * @param WebsiteInterface $website
     * @return bool
     */
    public function isRequested(WebsiteInterface $website): bool
    {
        return in_array((int)$website->getId(), $this->state->getRequests(), true);
    }

    /**
     * What Skroutz Analytics sends, and the warnings about it, for a website.
     *
     * @param WebsiteInterface $website
     * @param array|null $report
     * @return string[]
     */
    public function getAnalytics(WebsiteInterface $website, ?array $report): array
    {
        $storeId = (int)$this->config->getStore($website)->getId();

        return [
            'summary' => $this->analyticsCheck->getSummary($storeId),
            'warnings' => $this->analyticsCheck->getWarnings($storeId, $report),
        ];
    }

    /**
     * The URL of the "Generate now" action.
     *
     * @return string
     */
    public function getGenerateUrl(): string
    {
        return $this->getUrl('spirit_skroutzfeed/feed/generate', [
            'website' => (int)$this->getRequest()->getParam('website'),
        ]);
    }

    /**
     * A date of the report in the admin's locale.
     *
     * @param int $timestamp
     * @return string
     */
    public function formatTime(int $timestamp): string
    {
        return $this->_localeDate->formatDateTime(
            (new \DateTime())->setTimestamp($timestamp),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT
        );
    }
}
