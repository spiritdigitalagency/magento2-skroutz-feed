<?php

namespace Spirit\SkroutzFeed\Controller\Adminhtml\Generate;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Index implements HttpPostActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    /**
     * @var Json
     */
    protected $serializer;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Http
     */
    protected $http;

    /**
     * @var \Spirit\SkroutzFeed\Helper\Feed
     */
    protected $feed;

    public function __construct(
        PageFactory $resultPageFactory,
        Json $json,
        LoggerInterface $logger,
        Http $http,
        \Spirit\SkroutzFeed\Helper\Feed $feed
    ) {
        $this->feed = $feed;
        $this->resultPageFactory = $resultPageFactory;
        $this->serializer = $json;
        $this->logger = $logger;
        $this->http = $http;
    }

    public function execute()
    {
        $response = [
            'message' => 'Feed generated!'
        ];
        try {
            $this->feed->generate();
            return $this->jsonResponse($response);
        } catch (LocalizedException $e) {
            $response['message'] = $e->getMessage();
            return $this->jsonResponse($response);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $response['message'] = $e->getMessage();
            return $this->jsonResponse($response);
        }
    }

    /**
     * Create json response
     *
     * @return ResultInterface
     */
    public function jsonResponse($response = '')
    {
        $this->http->getHeaders()->clearHeaders();
        $this->http->setHeader('Content-Type', 'application/json');
        return $this->http->setBody($this->serializer->serialize($response));
    }
}
