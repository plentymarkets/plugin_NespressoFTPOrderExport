<?php

namespace NespressoFTPOrderExport\Repositories;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\DataBase\Contracts\Model;
use NespressoFTPOrderExport\Contracts\SettingRepositoryContract;
use NespressoFTPOrderExport\Models\Setting;

class SettingRepository implements SettingRepositoryContract
{
    /**
     * @var DataBase
     */
    private $database;

    /**
     * SettingsRepository constructor.
     *
     * @param  DataBase  $database
     */
    public function __construct(DataBase $database)
    {
        $this->database = $database;
    }

    /**
     * @param $key
     * @param $value
     * @return Model
     * @throws ValidationException
     */
    public function save($key, $value): Model
    {
        $settings        = pluginApp(Setting::class);
        $settings->key   = (string)$key;
        $settings->value = (string)$value;

        return $this->database->save($settings);
    }

    /**
     * @param $key
     * @return mixed|string|null
     */
    public function get($key)
    {
        $settings = $this->database->query(Setting::class)
            ->where('key', '=', $key)
            ->get();

        return is_array($settings) ? $settings[0]->value : null;
    }

    /**
     * @return Setting[]
     */
    public function list()
    {
        return $this->database->query(Setting::class)->get();
    }

    /**
     * @param $isB2B
     * @return string
     * @throws ValidationException
     */
    public function getBatchNumber($isB2B): string
    {
        if (!$isB2B){
            $batchField = 'batch_number';
        } else {
            $batchField = 'batch_number_b2b';
        }
        $batch = $this->get($batchField);
        if ($batch === null){
            $this->save($batchField, 1);
            return '01';
        }

        if ((int)$batch < 10){
            return '0' . $batch;
        }

        return (string)$batch;
    }


    /**
     * @return int
     * @throws ValidationException
     */
    public function getLatestCronExecutionTime(): int
    {
        $latestTime = $this->get('latest_cron_execution_time');

        if ($latestTime === null){
            $this->save('latest_cron_execution_time', microtime(true));
            return 0;
        }

        return (int)$latestTime;
    }

    /**
     * @return void
     * @throws ValidationException
     */
    public function setLatestCronExecutionTime()
    {
        $this->save('latest_cron_execution_time', microtime(true));
    }

    /**
     * @param $isB2B
     * @return void
     * @throws ValidationException
     */
    public function incrementBatchNumber($isB2B): void
    {
        $batch = $this->getBatchNumber($isB2B);
        $nextBatch = (int)$batch + 1;
        if (!$isB2B){
            $batchField = 'batch_number';
        } else {
            $batchField = 'batch_number_b2b';
        }
        $this->save($batchField, $nextBatch);
    }

    /**
     * @param array $productArray
     * @return void
     */
    public function setB2BProductList(array $productArray)
    {
        $this->save('b2b_productList', json_encode($productArray));
    }

    /**
     * @return mixed
     */
    public function getB2BProductList()
    {
        return json_decode($this->get('b2b_productList'), true);
    }
}
