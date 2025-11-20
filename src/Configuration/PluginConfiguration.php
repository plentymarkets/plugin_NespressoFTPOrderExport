<?php

namespace NespressoFTPOrderExport\Configuration;

use Exception;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class PluginConfiguration
{
    use Loggable;

    const PLUGIN_NAME            = "NespressoFTPOrderExport";
    const STANDARD_DESTINATION   = 0;
    const B2B_DESTINATION        = 1;
    const FBM_DESTINATION        = 2;
    const MMS_DESTINATION        = 3;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    public function __construct(
        ConfigRepository $configRepository
    ) {
        $this->configRepository  = $configRepository;
    }

    /**
     * @param $configKey
     *
     * @return mixed
     */
    protected function getConfigValue($configKey)
    {
        return $this->configRepository->get(self::PLUGIN_NAME . '.' . $configKey);
    }

    /**
     * @return int
     */
    public function getTotalOrdersPerBatch()
    {
        $totalOrders = $this->configRepository->get(self::PLUGIN_NAME . '.batchCount');
        if ($totalOrders == null) {
            return 50;
        }
        return (int)$totalOrders;
    }

    /**
     * @return int
     */
    public function getCronInterval()
    {
        $cronInterval = $this->configRepository->get(self::PLUGIN_NAME . '.cronInterval');
        if ($cronInterval == null) {
            return 20;
        }
        return (int)$cronInterval;
    }

    /**
     * @return array|true[]
     */
    public function getSFTPCredentials(): array
    {
        $ftpHost                = $this->getConfigValue('host');
        $ftpUser                = $this->getConfigValue('username');
        $ftpPassword            = $this->getConfigValue('password');
        $ftpPort                = $this->getConfigValue('port');
        $ftpFolderPath          = $this->getConfigValue('folderPath');
        $ftpFolderPathForB2B    = $this->getConfigValue('b2b_folderPath');
        $ftpFolderPathForFBM    = $this->getConfigValue('fbm_folderPath');

        if ($ftpHost === null || $ftpUser === null || $ftpPassword === null || $ftpPort === null) {
            $this->getLogger(__METHOD__)->error(self::PLUGIN_NAME . '::error.mandatoryCredentialsAreNotSet',
                [
                    'ftp_hostname'      => $ftpHost,
                    'ftp_username'      => $ftpUser,
                    'ftp_password'      => $ftpPassword,
                    'ftp_port'          => $ftpPort,
                    'ftp_folderPath'    => $ftpFolderPath,
                    'ftp_folderPath_B2B'=> $ftpFolderPathForB2B,
                    'ftp_folderPath_FBM'=> $ftpFolderPathForFBM,
                ]);
            
            return [
                'error' => true
            ];
        }

        return [
            'ftp_hostname'      => $ftpHost,
            'ftp_username'      => $ftpUser,
            'ftp_password'      => $ftpPassword,
            'ftp_port'          => $ftpPort,
            'ftp_folderPath'    => $ftpFolderPath,
            'ftp_folderPath_B2B'=> $ftpFolderPathForB2B,
            'ftp_folderPath_FBM'=> $ftpFolderPathForFBM
        ];
    }

    public function getPluginVariant(): string
    {
        $pluginVariant = $this->getConfigValue('pluginVariant');
        switch ($pluginVariant){
            case "0":
            default:
                return "DE";
            case "1":
                return "AT";
        }
    }

    public function getProcessedOrderStatus(): string
    {
        $orderStatus = $this->getConfigValue('processedStatus');
        if (!is_null($orderStatus) && ((int)$orderStatus > 0)){
            return $orderStatus;
        }
        return '';
    }
}
