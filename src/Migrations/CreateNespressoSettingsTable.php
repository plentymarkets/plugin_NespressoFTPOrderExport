<?php

namespace NespressoFTPOrderExport\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Plenty\Modules\Plugin\Exceptions\MySQLMigrateException;
use NespressoFTPOrderExport\Models\Setting;

class CreateNespressoSettingsTable
{
    /**
     * @param  Migrate  $migrate
     * @throws MySQLMigrateException
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(Setting::class);
    }

    protected function rollback(Migrate $migrate)
    {
        $migrate->deleteTable(Setting::class);
    }
}
