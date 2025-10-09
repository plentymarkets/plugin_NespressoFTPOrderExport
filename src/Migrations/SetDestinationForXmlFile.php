<?php

namespace NespressoFTPOrderExport\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Plenty\DataBase\Migrations\Migration;

class SetDestinationForXmlFile extends Migration
{
    /**
     * Run the migrations.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    public function run(Builder $schema)
    {
        if ($schema->hasColumn('plugin_nespresso_f_t_p_order_export__export_stack', 'isB2B')) {
            $schema->table('plugin_nespresso_f_t_p_order_export__export_stack', function (Blueprint $table) {
                $table->dropColumn('isB2B');
            });
        }
        if (!$schema->hasColumn('plugin_nespresso_f_t_p_order_export__export_stack', 'xml_destination')) {
            $schema->table('plugin_nespresso_f_t_p_order_export__export_stack', function (Blueprint $table) {
                $table->integer('xml_destination')->default(0);
            });
        }
    }

    protected function rollback(Builder $schema)
    {
    }
}
