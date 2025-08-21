<?php

namespace NespressoFTPOrderExport\Repositories;

use NespressoFTPOrderExport\Contracts\ExportDataRepositoryContract;
use NespressoFTPOrderExport\Models\TableRow;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class ExportDataRepository implements ExportDataRepositoryContract
{
    /**
     * @var DataBase
     */
    private $database;

    /**
     * @param DataBase $database
     */
    public function __construct(DataBase $database)
    {
        $this->database = $database;
    }

    /**
     * @param array $data
     * @return \Plenty\Modules\Plugin\DataBase\Contracts\Model
     */
    public function save(array $data)
    {
        $tableRow = pluginApp(TableRow::class);
        $tableRow->plentyOrderId    = (int)$data['plentyOrderId'];
        $tableRow->exportedData     = (string)$data['exportedData'];
        $tableRow->savedAt          = (string)$data['savedAt'];
        $tableRow->sentAt           = (string)$data['sentAt'];
        $tableRow->xml_destination  = (int)$data['xml_destination'];

        return $this->database->save($tableRow);
    }

    /**
     * @param $plentyOrderId
     * @return array|\Plenty\Modules\Plugin\DataBase\Contracts\Model[]|null
     */
    public function get($plentyOrderId)
    {
        $tableRow = $this->database->query(TableRow::class)
            ->where('plentyOrderId', '=', $plentyOrderId)
            ->get();

        return is_array($tableRow) ? $tableRow : null;
    }

    /**
     * @param int $maxRows
     * @param int $xml_destination
     * @return TableRow[]
     */
    public function listUnsent(int $maxRows, int $xml_destination=0)
    {
        if ($maxRows > 0) {
            return $this->database->query(TableRow::class)
                ->where('sentAt', '=', '')
                ->where('xml_destination', '=', $xml_destination)
                ->limit($maxRows)
                ->get();
        }
        return $this->database->query(TableRow::class)
            ->where('sentAt', '=', '')
            ->where('xml_destination', '=', $xml_destination)
            ->get();
    }

    /**
     * @param int $plentyOrderId
     * @return bool
     */
    public function orderExists(int $plentyOrderId) : bool
    {
        $results = $this->database->query(TableRow::class)
            ->where('plentyOrderId', '=', $plentyOrderId)
            ->limit(1)
            ->get();
        if (count($results) > 0){
            return true;
        }
        return false;
    }

    /**
     * @param string $dateLimit
     * @return void
     */
    public function deleteOldRecords(string $dateLimit) : void
    {
        $this->database->query(TableRow::class)
            ->where('sentAt', '!=', '')
            ->where('sentAt', '<', $dateLimit)
            ->delete();
    }

    /**
     * @param string $dateLimit
     * @return void
     */
    public function deleteAllRecords() : void
    {
        $this->database->query(TableRow::class)
            ->delete();
    }
}