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

    private function getBatchName(string $pluginVariant, bool $isB2B, bool $isFBM)
    {
        //ATENTIE: De revizuit in contextul FBM / B2B
        if ($pluginVariant == 'DE'){
            if ($isFBM){
                $batchField = 'batch_number_fbm';
            } else {
                if ($isB2B) {
                    $batchField = 'batch_number_b2b';
                } else {
                    $batchField = 'batch_number';
                }
            }
        } else {
            $batchField = 'batch_number';
        }
        return $batchField;
    }

    /**
     * @param string $pluginVariant
     * @param bool $isB2B
     * @param bool $isFBM
     * @return string
     * @throws ValidationException
     */
    public function getBatchNumber(string $pluginVariant, bool $isB2B, bool $isFBM): string
    {
        $batchField = $this->getBatchName($pluginVariant, $isB2B, $isFBM);

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
     * @param string $pluginVariant
     * @param bool $isB2B
     * @param bool $isFBM
     * @return void
     * @throws ValidationException
     */
    public function incrementBatchNumber(string $pluginVariant, bool $isB2B, bool $isFBM): void
    {
        $batch = $this->getBatchNumber($pluginVariant, $isB2B, $isFBM);
        $nextBatch = (int)$batch + 1;
        $batchField = $this->getBatchName($pluginVariant, $isB2B, $isFBM);
        $this->save($batchField, $nextBatch);
    }

    public function setB2BProductList(array $productArray)
    {
        $this->save('b2b_productList', json_encode($productArray));
    }

    /**
     * @return array|mixed
     */
    public function getB2BProductList()
    {
        $productList = $this->get('b2b_productList');
        if ($productList !== '')
            return json_decode($productList, true);
        return [];
    }
}
