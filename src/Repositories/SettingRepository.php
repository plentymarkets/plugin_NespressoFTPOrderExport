<?php

namespace NespressoFTPOrderExport\Repositories;

use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Contracts\SettingRepositoryContract;
use NespressoFTPOrderExport\Models\Setting;
use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\DataBase\Contracts\Model;

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
     * @param int $xml_destination
     * @return string
     */
    private function getBatchName(int $xml_destination)
    {
        switch ($xml_destination){
            case PluginConfiguration::STANDARD_DESTINATION:
                $batchField = 'batch_number';
                break;
            case PluginConfiguration::B2B_DESTINATION:
                $batchField = 'batch_number_b2b';
                break;
            case PluginConfiguration::FBM_DESTINATION:
                $batchField = 'batch_number_fbm';
                break;
        }
        return $batchField;
    }

    /**
     * @param int $xml_destination
     * @return string
     * @throws ValidationException
     */
    public function getBatchNumber(int $xml_destination): string
    {
        $batchField = $this->getBatchName($xml_destination);

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
     * @param int $xml_destination
     * @return void
     * @throws ValidationException
     */
    public function incrementBatchNumber(int $xml_destination): void
    {
        $batch = $this->getBatchNumber($xml_destination);
        $nextBatch = (int)$batch + 1;
        $batchField = $this->getBatchName($xml_destination);
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
