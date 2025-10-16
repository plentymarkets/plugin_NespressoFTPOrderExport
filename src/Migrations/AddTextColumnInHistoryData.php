<?php

namespace NespressoFTPOrderExport\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Plenty\DataBase\Migrations\Migration;

class AddTextColumnInHistoryData extends Migration
{
    /**
     * Run the migrations.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    public function run(Builder $schema)
    {
        if (!$schema->hasColumn('plugin_nespresso_f_t_p_order_export__history_data', 'data_column')) {
            $schema->table('plugin_nespresso_f_t_p_order_export__history_data', function (Blueprint $table) {
                $table->text('data_column')->default('');
            });
        }
    }

    protected function rollback(Builder $schema)
    {
    }
}
