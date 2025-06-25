<?php

namespace NespressoFTPOrderExport\Contracts;

use NespressoFTPOrderExport\Models\TableRow;
use Plenty\Modules\Plugin\Database\Contracts\Model;

interface ExportDataRepositoryContract
{
    public function save(array $data);

    public function get($plentyOrderId);

    public function listUnsent(int $maxRows, bool $isB2B=false);

    public function orderExists(int $plentyOrderId) : bool;

    public function deleteOldRecords(string $dateLimit) : void;
}
