<?php

namespace Spirit\SkroutzFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ExcludeCategories implements OptionSourceInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * ExcludeCategories constructor.
     * @param  \Magento\Store\Model\StoreManagerInterface  $storeManager
     * @param  \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory  $collectionFactory
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collectionFactory
    ) {
        $this->_storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create()
            ->setStore($this->_storeManager->getStore())
            ->addAttributeToSelect('*');
        $attributesArray = [
            ['value' => '', 'label' => __('-- No Selection --')]
        ];
        foreach ($collection->getItems() as $category) {
            if ($category->getId() < 3) {
                continue;
            }
            $attributesArray[] = [
                'value' => $category->getId(),
                'label' => str_repeat('- ', $category->getLevel() - 2).$category->getName()
            ];
        }
        return $attributesArray;
    }
}
