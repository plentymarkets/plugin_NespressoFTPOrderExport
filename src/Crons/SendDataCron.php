<?php

namespace NespressoFTPOrderExport\Crons;

use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Helpers\ExportHelper;
use NespressoFTPOrderExport\Repositories\SettingRepository;
use NespressoFTPOrderExport\Services\OrderExportService;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\Log\Loggable;
use Throwable;

class SendDataCron extends CronHandler
{
    use Loggable;

    /**
     * @param OrderExportService $exportService
     * @return bool
     */
    public function handle(
        OrderExportService  $exportService,
        PluginConfiguration $configRepository,
        SettingRepository   $settingsRepository,
        ExportHelper        $exportHelper
    )
    {
        $cronInterval = $configRepository->getCronInterval();
        $latestExecutionTime = $settingsRepository->getLatestCronExecutionTime();

        if (($latestExecutionTime + $cronInterval * 60) <= microtime(true)) {
            $exportHelper->addHistoryData('Start XML export');
            $result = $exportService->sendDataToClient();
            $settingsRepository->setLatestCronExecutionTime();
            $exportHelper->addHistoryData('End XML export');
            return $result;
        }
    }
}
