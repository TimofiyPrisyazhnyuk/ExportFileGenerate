<?php

namespace app\export;

use app\exports\indexFiles\CommonConstants;
use app\models\Language;
use yii\helpers\Json;

/**
 * Class FileProvider
 *
 * @author Prisyazhnyuk Timofiy
 * @package app\exports\export
 */
class FileProvider
{
    /**
     * @var bool|object
     */
    private $_loadAdditionalData;

    /**
     * Gets new instance of AdditionalDataLoader class.
     *
     * @return AdditionalDataLoader
     */
    protected function getAdditionalDataLoader()
    {
        if ($this->_loadAdditionalData === null) {
            $this->_loadAdditionalData = new AdditionalDataLoader();
        }

        return $this->_loadAdditionalData;
    }

    /**
     * Gets filtered product_info value.
     *
     * @param string $value
     *
     * @return false|array
     */
    public function getResultProductData($value)
    {
        $decodedValue = Json::decode($value);

        if (empty($decodedValue['product']['product_id'])) {
            return false;
        }
        $additionalData = $this->loadAdditionalData($decodedValue['product']);
        $baseData = [
            'product' => $decodedValue['product'],
            'ean' => $this->getProductEanCodes($decodedValue['product_ean_codes']),
            'title' => $this->getProductTitleByLang($decodedValue['product_summary_title']),
        ];
        if (empty($baseData['product']['prod_id']) || empty($baseData['product']['name']) || !isset($baseData['product']['active'], $baseData['product']['quality'])
            || $baseData['title'] === null || $additionalData['categoryName'] === null || $additionalData['supplierName'] === null) {
            return false;
        }
        $resultData = $this->composeResultData($baseData, $additionalData);
        // if product has more different original MPN
        if (!empty($decodedValue['product_map'])) {
            $resultData = $this->getResultByOriginalMpn($decodedValue['product_map'], $resultData,
                $decodedValue['product']['supplier_id']);
        }

        return $resultData;
    }

    /**
     * Form string with ean code by separate.
     *
     * @param array $productEan
     *
     * @return string
     */
    protected function getProductEanCodes(array $productEan)
    {
        $ean = null;

        if (!empty($productEan)) {
            $productEanCodes = array_column($productEan, 'ean_code');

            if (!empty($productEanCodes)) {
                $ean = implode(CommonConstants::CSV_CELL_CONTENT_SEPARATOR, $productEanCodes);
            }
        }

        return $ean;
    }

    /**
     * Form international product Title.
     *
     * @param array $productTitle
     *
     * @return string
     */
    protected function getProductTitleByLang(array $productTitle)
    {
        $title = null;

        if (!empty($productTitle[Language::LANGUAGE_ID_INT]['summary_title'])) {
            $title = $productTitle[Language::LANGUAGE_ID_INT]['summary_title'];
        }

        return $title;
    }

    /**
     * Load additional data.
     *
     * @param array $productInfo
     *
     * @return array
     */
    public function loadAdditionalData(array $productInfo)
    {
        $loadAdditionalData = $this->getAdditionalDataLoader();

        $additionalData = [
            'supplierName' => $loadAdditionalData->getSupplierName($productInfo['supplier_id']),
            'categoryName' => $loadAdditionalData->getCategoryName($productInfo['catid']),
            'familyName' => $loadAdditionalData->getFamilyName($productInfo['family_id']),
        ];

        return $additionalData;
    }

    /**
     * Compose result data for array.
     *
     * @param array $productInfo
     * @param array $additionalData
     *
     * @return array
     */
    protected function composeResultData(array $productInfo, array $additionalData)
    {
        $result = [
            $productInfo['product']['prod_id'],
            $additionalData['supplierName'],
            $productInfo['product']['quality'],
            $additionalData['categoryName'],
            $productInfo['product']['name'],
            $productInfo['ean'],
            $productInfo['product']['active'],
            $additionalData['familyName'],
            $productInfo['title'],
        ];

        return $result;
    }

    /**
     * Get array result product data by MPNs
     *
     * @param array $productMpn
     * @param array $result
     * @param string $supplierId
     *
     * @return array
     */
    protected function getResultByOriginalMpn(array $productMpn, array $result, $supplierId)
    {
        $resultData = [];
        $idsProductWithSupplier = array_column($productMpn, 'supplier_id', 'prod_id');

        foreach ($idsProductWithSupplier as $prodId => $suppId) {
            $mpn = (string)$prodId;

            if ($mpn !== $result[0]) {
                $updateResult = $result;
                $updateResult[0] = $mpn;

                if ($supplierId !== $suppId) {
                    $loadAdditionalData = $this->getAdditionalDataLoader();
                    $supplierName = $loadAdditionalData->getSupplierName($suppId);
                    $updateResult[1] = $supplierName;
                }
                $resultData[] = $updateResult;
            }
        }
        if (empty($resultData)) {
            return $result;
        }
        $resultData[] = $result;

        return $resultData;
    }
}
