<?php

namespace NespressoFTPOrderExport\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Plenty\Modules\Plugin\Exceptions\MySQLMigrateException;
use NespressoFTPOrderExport\Models\HistoryData;

class CreateNespressoHistoryTable1
{
    /**
     * @param  Migrate  $migrate
     * @throws MySQLMigrateException
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(HistoryData::class);
    }

    protected function rollback(Migrate $migrate)
    {
        $migrate->deleteTable(HistoryData::class);
    }
}
