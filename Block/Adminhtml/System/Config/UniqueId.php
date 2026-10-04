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
use Spirit\SkroutzFeed\Model\AnalyticsCheck;
use Spirit\SkroutzFeed\Model\Config;

/**
 * The Unique ID setting, with what Skroutz Analytics sends in the same scope written under it.
 */
class UniqueId extends Field
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var AnalyticsCheck
     */
    private $analyticsCheck;

    /**
     * @param Context $context
     * @param Config $config
     * @param AnalyticsCheck $analyticsCheck
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        AnalyticsCheck $analyticsCheck,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->analyticsCheck = $analyticsCheck;
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $websiteId = (int)$this->getRequest()->getParam('website');
        $website = $websiteId
            ? $this->_storeManager->getWebsite($websiteId)
            : $this->_storeManager->getWebsite((int)$this->_storeManager->getDefaultStoreView()->getWebsiteId());
        $summary = $this->analyticsCheck->getSummary((int)$this->config->getStore($website)->getId());
        if ($summary !== null) {
            $element->setComment($element->getComment() . '<br><strong>' . $this->escapeHtml($summary) . '</strong>');
        }

        return parent::render($element);
    }
}
