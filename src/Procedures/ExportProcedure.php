<?php

namespace NespressoFTPOrderExport\Procedures;

use NespressoFTPOrderExport\Helpers\ExportHelper;
use NespressoFTPOrderExport\Services\OrderExportService;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;

use Throwable;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;

class ExportProcedure
{
    use Loggable;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param OrderExportService $exportService
     * @param ExportHelper $exportHelper
     * @return mixed
     * @throws Throwable
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        OrderExportService       $exportService,
        ExportHelper             $exportHelper
    ) {
        /** @var Order $order */
        $order = $eventTriggered->getOrder();
        $exportHelper->addHistoryData('Event triggered for order: ' . $order->id, $order->id);

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        return $authHelper->processUnguarded(function () use ($order, $exportService, $exportHelper) {
            try {
                $exportService->processOrder($order);
            } catch (Throwable $e) {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.exceptionMessage', $e->getMessage());
                $exportHelper->addHistoryData(
                    'Exception when processing order: ' . $order->id
                    . ' (' . $e->getMessage() . ')', $order->id);
            }

            $exportHelper->addHistoryData('Event ended for order: ' . $order->id, $order->id);

            return 0;
        });
    }
}
