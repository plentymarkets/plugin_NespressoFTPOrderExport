<?php

namespace NespressoFTPOrderExport\Models;

use Carbon\Carbon;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Repositories\ExportDataRepository;
use Plenty\Plugin\Log\Loggable;

class Record
{
    use Loggable;

    /**
     * @var string
     */
    public $record_remarks;

    /**
     * @var string
     */
    public $external_ref;

    /**
     * @var string
     */
    public $member_number;

    /**
     * @var int
     */
    public $address_changed;

    /**
     * @var string
     */
    public $order_source;

    /**
     * @var string
     */
    public $channel;

    /**
     * @var Customer
     */
    public $customer;

    /**
     * @var OrderData
     */
    public $order;

    /**
     * @param int $plentyOrderId
     * @return bool
     */
    public function saveRecord(int $plentyOrderId){

        $exportData = [
            'plentyOrderId'    => $plentyOrderId,
            'exportedData'     => json_encode($this),
            'savedAt'          => Carbon::now()->toDateTimeString(),
            'sentdAt'          => '',
        ];

        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            /** @var TableRow $savedObject */
            $savedObject = $exportDataRepository->save($exportData);
            return true;
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.saveExportError',
                [
                    'message'     => $e->getMessage(),
                    'exportData'  => $exportData
                ]);
        }
        return false;
    }
}
