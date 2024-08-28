<?php

namespace NespressoFTPOrderExport\Procedures;

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
     * @return mixed
     * @throws Throwable
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        OrderExportService       $exportService
    ) {
        /** @var Order $order */
        $order = $eventTriggered->getOrder();

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        return $authHelper->processUnguarded(function () use ($order, $exportService) {
            try {
                $exportService->processOrder($order);
            } catch (Throwable $e) {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error('Exception', $e->getMessage());
            }

            $this->getLogger('return order processed')
                ->addReference('orderId', $order->id)
                ->debug(PluginConfiguration::PLUGIN_NAME . 'general.returnOrderExecuted', [
                    'message'         => 'Return executed'
                ]);

            return 0;
        });
    }
}
