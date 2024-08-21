<?php

namespace NespressoFTPOrderExport\Models;

class ImportBatch
{
    /**
     * @var string
     */
    public $batch_date_time;

    /**
     * @var string
     */
    public $batch_number;

    /**
     * @var string
     */
    public $sender_id;

    /**
     * @var Record
     */
    public $record;
}
