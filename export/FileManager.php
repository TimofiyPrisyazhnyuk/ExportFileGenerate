<?php

namespace app\export;

use app\helpers\ArchiveHelper;
use app\helpers\FileHelper;
use app\export\CommonConstants;
use Yii;

/**
 * Class ProdIdDManager
 *
 * @author Prisyazhnyuk Timofiy
 * @package app\exports\export
 */
class FileManager
{
    const CSV_FIELD_DELIMITER = "\t\t\t";
    const ROW_SEPARATOR = "\n";
    const PRODUCT_ID_FROM = 0;
    const FILE_NAME = 'prodId_export_';

    /**
     * @var null|string
     */
    protected $baseDirPath;

    /**
     * @var null|string
     */
    protected $fullFilePath;

    /**
     * @var null|string
     */
    protected $fullFilePathGz;

    /**
     * @var null|bool
     */
    protected $testMode;

    /**
     * @var array
     */
    private $_columnNames = [
        'Part number',
        'Brand',
        'Quality',
        'Category',
        'Model Name',
        'EAN',
        'Market Presence',
        'Family',
        'Title',
    ];

    /**
     * Enable test mode.
     *
     * @param bool $testMode
     */
    public function setTestMode($testMode)
    {
        $this->testMode = $testMode;
    }

    /**
     * Generate new file - prodid_d.txt.
     *
     * @throws \ErrorException
     * @throws \app\components\cassandra\Exception
     * @throws \app\exceptions\BlobStorageException
     */
    public function run()
    {
        $file = $this->createExportFile();

        $this->writeColumnNamesToFile($file);
        $this->writeDataToFile($file);
        $this->closeFileResource($file);

        if (!$this->testMode) {
            $this->copyFinalFileToStore();
        }
        print "Generated export file successfully Done. \n";
    }

    /**
     * Crete export file.
     *
     * @return bool|resource
     * @throws \ErrorException
     */
    public function createExportFile()
    {
        $baseDirectoryPath = $this->getBaseDirPath();
        $dateGeneratedFile = date('YmdHis');

        $fullFileName = $baseDirectoryPath . DIRECTORY_SEPARATOR . self::FILE_NAME . $dateGeneratedFile . '.' . CommonConstants::EXTENSION_TXT;
        $file = $this->createFileResource($fullFileName);
        $this->fullFilePath = $fullFileName;

        if (!is_resource($file)) {
            throw new \ErrorException('Failed to create ProductId export files in directory ' . $baseDirectoryPath);
        }

        return $file;
    }

    /**
     * Gets base dir path.
     *
     * @return string
     */
    protected function getBaseDirPath()
    {
        if ($this->baseDirPath === null) {
            $this->baseDirPath = sys_get_temp_dir();
        }

        return $this->baseDirPath;
    }

    /**
     * Write data to new export file.
     *
     * @param resource $file
     *
     * @throws \app\components\cassandra\Exception
     */
    public function writeDataToFile($file)
    {
        $provider = $this->getProductIdsProvider();
        $productInfoPool = $this->getProductInfoPool(self::PRODUCT_ID_FROM);

        while (($row = $productInfoPool->getNextRow()) !== null) {
            $resultData = $provider->getResultProductData($row['value']);
            $checkIfArray = is_array($resultData[0]);

            if ($resultData && !$checkIfArray) {
                $this->writeDataToCsvFile($file, $resultData);
            } elseif ($checkIfArray) {
                foreach ($resultData as $row) {
                    $this->writeDataToCsvFile($file, $row);
                }
            }
        }
    }

    /**
     * Copy final file to swift store.
     *
     * @throws \ErrorException
     * @throws \app\exceptions\BlobStorageException
     */
    protected function copyFinalFileToStore()
    {
        $storage = Yii::$app->blobStorage;
        $container = SwiftContainer::CONTAINER_REPOSITORY;

        if (!$storage->containerExists($container)) {
            $storage->createContainer($container);
        }
        $filePath = $this->getFullFilePath();
        $archivePath = $this->fullFilePathGz = $filePath . '.gz';
        ArchiveHelper::gzFileInStream($filePath, $archivePath);

        if (!$storage->put($container, $filePath, CommonConstants::FILE_PROD_ID_TXT)) {
            throw new \ErrorException('Failed to store export file to swift storage.');
        }
        if (!$storage->put($container, $archivePath, CommonConstants::FILE_PROD_ID_TXT_GZ)) {
            throw new \ErrorException('Failed to store export gz file to swift storage.');
        }
    }

    /**
     * Get full file path.
     *
     * @return null|string
     * @throws \ErrorException
     */
    public function getFullFilePath()
    {
        if ($this->fullFilePath === null) {
            throw new \ErrorException('Failed to get full file path.');
        }

        return $this->fullFilePath;
    }

    /**
     * Write column names to csv file.
     *
     * @param resource $file
     *
     * @return bool|int
     */
    public function writeColumnNamesToFile($file)
    {
        return $this->writeDataToCsvFile($file, $this->_columnNames);
    }

    /**
     * Gets new instance of ProductIdFilesManager class.
     *
     * @return ProdIdDProvider
     */
    protected function getProductIdsProvider()
    {
        return new ProdIdDProvider();
    }

    /**
     * Gets new instance of ProductInfoPool class.
     *
     * @param int $productIdFrom
     *
     * @return ProductInfoPool
     */
    protected function getProductInfoPool($productIdFrom)
    {
        return new ProductInfoPool($productIdFrom);
    }

    /**
     * Writes passed data to csv file.
     *
     * @param resource $file
     * @param array $row
     *
     * @return bool|int
     */
    protected function writeDataToCsvFile($file, array $row)
    {
        $newRowCsv = implode(self::CSV_FIELD_DELIMITER, $row) . self::ROW_SEPARATOR;

        return fwrite($file, $newRowCsv);
    }

    /**
     * Creates file pointer resource.
     *
     * @param string $filePath
     *
     * @return bool|resource
     */
    protected function createFileResource($filePath)
    {
        return fopen($filePath, 'wb');
    }

    /**
     * Creates file pointer resource.
     *
     * @param string $filePath
     *
     * @return bool|resource
     */
    public function closeFileResource($filePath)
    {
        return fclose($filePath);
    }

    /**
     * ProductIdsManager destructor.
     *
     * @throws \ErrorException
     */
    public function __destruct()
    {
        if (!$this->testMode) {
            FileHelper::safeUnlink($this->getFullFilePath());

            if ($this->fullFilePathGz !== null) {
                FileHelper::safeUnlink($this->fullFilePathGz);
            }
        }
    }
}
