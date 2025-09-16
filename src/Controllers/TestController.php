<?php

namespace NespressoFTPOrderExport\Controllers;

use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use NespressoFTPOrderExport\Repositories\ExportDataRepository;
use NespressoFTPOrderExport\Services\OrderExportService;
use NespressoFTPOrderExport\Repositories\SettingRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Log\Loggable;

class TestController extends Controller
{
    use Loggable;

    /**
     * @return bool
     */
    public function clearDataTable()
    {
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportList = $exportDataRepository->deleteAllRecords();
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return false;
        }
        return true;
    }

    /**
     * @return array|mixed
     */
    public function getB2BProductList()
    {
        /** @var SettingRepository $settingsRepository */
        $settingsRepository = pluginApp(SettingRepository::class);
        return $settingsRepository->getB2BProductList();
    }

    /**
     * @param array $productArray
     * @return void
     */
    public function setB2BProductList(array $productArray)
    {
        /** @var SettingRepository $settingsRepository */
        $settingsRepository = pluginApp(SettingRepository::class);
        $settingsRepository->setB2BProductList($productArray);
    }

    /**
     * @param string $newProductCode
     * @return void
     */
    public function addProductCode(string $newProductCode)
    {
        /** @var SettingRepository $settingsRepository */
        $settingsRepository = pluginApp(SettingRepository::class);

        $productCodes = $settingsRepository->getB2BProductList();
        if (!in_array($newProductCode, $productCodes)) {
            $productCodes[] = $newProductCode;
            $settingsRepository->setB2BProductList($productCodes);
        }
    }

    /**
     * @param string $productCode
     * @return void
     */
    public function deleteProductCode(string $productCode)
    {
        /** @var SettingRepository $settingsRepository */
        $settingsRepository = pluginApp(SettingRepository::class);

        $productCodes = $settingsRepository->getB2BProductList();

        $key = array_search($productCode, $productCodes);
        if ($key !== false) {
            unset($productCodes[$key]);
            $productCodes = array_values($productCodes);
            $settingsRepository->setB2BProductList($productCodes);
        }
    }

    /**
     * @return void
     */
    public function callExportXml()
    {
        /** @var OrderExportService $exportService */
        $exportService = pluginApp(OrderExportService::class);
        $exportService->sendDataToClient();
    }

    public function setBatchNumberForB2B($number)
    {
        /** @var SettingRepository $settingRepository */
        $settingRepository = pluginApp(SettingRepository::class);
        $batchField = $settingRepository->getBatchName(PluginConfiguration::STANDARD_DESTINATION);
        $settingRepository->save($batchField, $number);
    }
}
