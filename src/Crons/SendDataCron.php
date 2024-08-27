<?php

namespace NespressoFTPOrderExport\Crons;

use NespressoFTPOrderExport\Services\OrderExportService;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\Log\Loggable;
use Throwable;

class SendDataCron extends CronHandler
{
    use Loggable;

    public function handle(OrderExportService $exportService)
    {
        return $exportService->sendDataToClient();
    }
}
