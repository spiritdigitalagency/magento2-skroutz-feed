<?php

namespace Spirit\SkroutzFeed\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class GenerateButton extends Field
{
    const CONFIG_NAMESPACE = 'spirit_skroutz';

    protected $_template = 'Spirit_SkroutzFeed::system/config/generate_button.phtml';

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function getFeedUrl(){
        $mediaUrl = $this ->_storeManager-> getStore()->getBaseUrl();
        $filename = $this->getConfig('feed_settings/filename') ?? 'skroutz';
        $filename = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $filename);
        $filename = mb_ereg_replace("([\.]{2,})", '', $filename);
        return $mediaUrl . "feeds/$filename.xml";
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('skroutz_feed/generate/index');
    }

    public function getButtonHtml()
    {
        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData([
            'id' => 'generate_feed', 'label' => __('Generate Feed'),
        ])->toHtml();
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @param  string  $key
     *
     * @return mixed
     */
    protected function getConfig(string $key)
    {
        return $this->_scopeConfig->getValue(self::CONFIG_NAMESPACE."/$key", ScopeInterface::SCOPE_STORE);
    }
}
