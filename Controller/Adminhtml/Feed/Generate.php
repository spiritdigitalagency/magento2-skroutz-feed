<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Spirit\SkroutzFeed\Model\Config;
use Spirit\SkroutzFeed\Model\State;

/**
 * "Generate now": queues the feed for cron, which starts it within a minute.
 *
 * Generating inside the admin request would time out on large catalogs.
 */
class Generate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Spirit_Skroutz::config';

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
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param State $state
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Config $config,
        State $state
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->state = $state;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $websiteId = (int)$this->getRequest()->getParam('website');
        $websiteIds = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $id = (int)$website->getId();
            if (($websiteId === 0 || $websiteId === $id) && $this->config->isEnabled($id)) {
                $websiteIds[] = $id;
            }
        }
        $this->state->request($websiteIds);
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $result->setData([
            'message' => $websiteIds
                ? __(
                    'The feed will be generated within a minute, if Magento cron is running. '
                    . 'Reload the page later to see the report.'
                )
                : __('The feed is not enabled in this scope. Enable it and save the configuration first.'),
        ]);
    }
}
