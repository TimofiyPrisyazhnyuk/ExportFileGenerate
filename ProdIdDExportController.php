<?php

namespace app\commands;

use app\components\console\Controller;
use app\export\FileManager;
use Yii;

/**
 * Class ExportController
 *
 * @author Prisyazhnyuk Timofiy
 * @package app\commands
 */
class ExportController extends Controller
{
    const LOG_TAG = 'ExportFile';

    /**
     * Create export file..
     *
     * @param bool $testMode
     *
     * @return int
     */
    public function actionCreateExportFile($testMode = false)
    {
        try {
            $manager = new FileManager();
            $manager->setTestMode($testMode);
            $manager->run();
        } catch (\Exception $e) {
            $rawMessage = [
                'Failed to generate csv export file.',
                'Error message: ' . $e->getMessage(),
                'Trace:' . $e->getTraceAsString(),
            ];
            Yii::$app->logger->warning(implode("\n", $rawMessage), ['tags' => static::LOG_TAG]);

            return static::EXIT_CODE_ERROR;
        }

        return static::EXIT_CODE_NORMAL;
    }
}
