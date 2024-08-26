<?php
namespace NespressoFTPOrderExport\Providers;

use ErrorException;
use Exception;
use NespressoFTPOrderExport\Crons\SendDataCron;
use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Plenty\Plugin\Application;
use Plenty\Plugin\ServiceProvider;


/**
 * Class PluginServiceProvider
 * @package NespressoFTPOrderExport\Providers
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * @param CronContainer $container
     * @param EventProceduresService $eventProceduresService
     * @param WizardContainerContract $wizardContainerContract
     * @param Application $app
     * @return void
     * @throws ErrorException
     */
    public function boot(
        CronContainer $container,
        EventProceduresService $eventProceduresService,
        WizardContainerContract $wizardContainerContract,
        Application $app
    ) {
        $container->add(CronContainer::EVERY_FIFTEEN_MINUTES, SendDataCron::class);
        $this->bootProcedures($eventProceduresService);
        $this->getApplication()->register(NespressoFTPOrderExportRouteServiceProvider::class);
    }

    /**
     * @param  EventProceduresService  $eventProceduresService
     */
    private function bootProcedures(EventProceduresService $eventProceduresService)
    {
        $eventProceduresService->registerProcedure(
            'NespressoFTPOrderExport',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => ' NespressoFTPOrderExport [DE]',
                'en' => ' NespressoFTPOrderExport [EN]'
            ],
            '\NespressoFTPOrderExport\Procedures\ExportProcedure@run'
        );
    }
}
