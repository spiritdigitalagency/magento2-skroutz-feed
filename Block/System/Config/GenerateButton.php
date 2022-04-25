<?php

namespace Spirit\SkroutzFeed\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateButton extends Field
{
    protected $_template = 'Spirit_SkroutzFeed::system/config/generate_button.phtml';

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function getActionUrl()
    {
        return $this->getUrl('skroutz_feed/generate/index');
    }

    public function getButtonHtml()
    {
        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData([
            'id' => 'btn_id', 'label' => __('Generate Feed'),
        ])->toHtml();
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}
