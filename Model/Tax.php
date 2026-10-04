<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * VAT rates by product tax class, for the store's default tax destination.
 */
class Tax
{
    /**
     * @var Calculation
     */
    private $calculation;

    /**
     * @var TaxConfig
     */
    private $taxConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array<string, float> "store id/tax class id" => rate
     */
    private $rates = [];

    /**
     * @param Calculation $calculation
     * @param TaxConfig $taxConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Calculation $calculation,
        TaxConfig $taxConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->calculation = $calculation;
        $this->taxConfig = $taxConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * The VAT rate (e.g. 24.0) of a product tax class.
     *
     * @param int $taxClassId
     * @return float
     */
    public function getRate(int $taxClassId): float
    {
        $store = $this->storeManager->getStore();
        $key = $store->getId() . '/' . $taxClassId;
        if (!isset($this->rates[$key])) {
            $request = $this->calculation->getRateRequest(null, null, null, (int)$store->getId());
            $this->rates[$key] = (float)$this->calculation->getRate($request->setProductClassId($taxClassId));
        }

        return $this->rates[$key];
    }

    /**
     * A catalog price with VAT included.
     *
     * @param float $price
     * @param int $taxClassId
     * @return float
     */
    public function includeTax(float $price, int $taxClassId): float
    {
        if ($this->taxConfig->priceIncludesTax((int)$this->storeManager->getStore()->getId())) {
            return $price;
        }

        return $price * (1 + $this->getRate($taxClassId) / 100);
    }
}
