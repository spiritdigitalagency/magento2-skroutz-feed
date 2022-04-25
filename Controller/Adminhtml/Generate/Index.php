<?php

namespace Spirit\SkroutzFeed\Controller\Adminhtml\Generate;

use Magento\Backend\App\Action\Context;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var \Spirit\SkroutzFeed\Helper\Feed
     */
    protected $feed;

    public function __construct(
        Context $context,
        \Spirit\SkroutzFeed\Helper\Feed $feed
    ) {
        $this->feed = $feed;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->feed->generate();
        $this->_redirect('*/*/');
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Spirit_SkroutzFeed::skroutz_feed');
    }
}
