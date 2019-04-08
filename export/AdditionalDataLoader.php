<?php

namespace app\export;

use app\models\cassandra\CassandraCache;
use app\models\Language;

/**
 * Class AdditionalDataLoader
 *
 * @author Prisyazhnyuk Timofiy
 * @package app\exports\export
 */
class AdditionalDataLoader
{
    /**
     * @var array
     */
    private $_suppliersName = [];

    /**
     * @var array
     */
    private $_categoriesName = [];

    /**
     * @var array
     */
    private $_familiesName = [];

    /**
     * Gets supplier name.
     *
     * @param int $supplierId
     *
     * @return null|string
     */
    public function getSupplierName($supplierId)
    {
        if (!isset($this->_suppliersName[$supplierId]) && !$this->loadSupplierName($supplierId)) {
            return null;
        }

        return $this->_suppliersName[$supplierId];
    }

    /**
     * Loads supplier info.
     *
     * @param int $supplierId
     *
     * @return bool
     */
    protected function loadSupplierName($supplierId)
    {
        $supplier = CassandraCache::findSupplierCache($supplierId);

        if ($supplier === false) {
            return false;
        }

        $this->_suppliersName[$supplier->supplierId] = $supplier->name;

        return true;
    }

    /**
     * Gets supplier name.
     *
     * @param int $categoryId
     *
     * @return null|string
     */
    public function getCategoryName($categoryId)
    {
        if (!isset($this->_categoriesName[$categoryId]) && !$this->loadCategoryName($categoryId)) {
            return null;
        }

        return $this->_categoriesName[$categoryId];
    }

    /**
     * Loads category name.
     *
     * @param int $categoryId
     *
     * @return bool
     */
    protected function loadCategoryName($categoryId)
    {
        $category = CassandraCache::findCategoryCache($categoryId);

        if ($category === false || !isset($category->pluralNames[Language::LANGUAGE_ID_EN])) {
            return false;
        }

        $this->_categoriesName[$category->categoryId] = $category->pluralNames[Language::LANGUAGE_ID_EN];

        return true;
    }

    /**
     * Gets family name.
     *
     * @param int $familyId
     *
     * @return null|string
     */
    public function getFamilyName($familyId)
    {
        if (!isset($this->_familiesName[$familyId]) && !$this->loadFamilyName($familyId)) {
            return null;
        }

        return $this->_familiesName[$familyId];
    }

    /**
     * Loads family name.
     *
     * @param int $familyId
     *
     * @return bool
     */
    protected function loadFamilyName($familyId)
    {
        $family = CassandraCache::findFamilyCache($familyId);

        if ($family === false || !isset($family->names[Language::LANGUAGE_ID_EN])) {
            return false;
        }

        $this->_familiesName[$family->familyId] = $family->names[Language::LANGUAGE_ID_EN];

        return true;
    }
}
