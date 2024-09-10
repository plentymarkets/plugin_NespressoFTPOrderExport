<?php

namespace NespressoFTPOrderExport\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class UseTextInExportTable extends Migration
{
    public function up()
    {
        Schema::table('plugin_nespresso_f_t_p_order_export__export_stack', function(Blueprint $table)
        {
            $table->text('exportedData')->default('');
        });
    }

    public function down()
    {
    }
}
