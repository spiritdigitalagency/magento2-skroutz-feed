<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The store views a website feed can be generated from.
 */
class StoreView implements OptionSourceInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     */
    public function __construct(StoreManagerInterface $storeManager, RequestInterface $request)
    {
        $this->storeManager = $storeManager;
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('Default store view of the website')]];
        // In a website scope, only the store views of that website
        $websiteId = (int)$this->request->getParam('website');
        foreach ($this->storeManager->getStores() as $store) {
            if ($websiteId && (int)$store->getWebsiteId() !== $websiteId) {
                continue;
            }
            $options[] = [
                'value' => $store->getId(),
                'label' => $this->storeManager->getWebsite($store->getWebsiteId())->getName()
                    . ' / ' . $store->getName(),
            ];
        }

        return $options;
    }
}
