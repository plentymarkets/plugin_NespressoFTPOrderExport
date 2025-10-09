<?php

namespace NespressoFTPOrderExport\Clients;

use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\Log\Loggable;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;

class ClientForSFTP
{
    use Loggable;

    const CLASS_NAME = 'ClientForSFTP';

    /**
     * @var LibraryCallContract
     */
    private $libraryCall;
    /**
     * @var PluginConfiguration
     */
    private $configRepository;

    /** @var array */
    private $credentials;

    /**
     * Client constructor.
     * @param LibraryCallContract $libraryCall
     * @param PluginConfiguration $configRepository
     */
    public function __construct(
        LibraryCallContract $libraryCall,
        PluginConfiguration    $configRepository
    )
    {
        $this->libraryCall = $libraryCall;
        $this->configRepository = $configRepository;

        try {
            $this->credentials = $configRepository->getSFTPCredentials();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }


    /**
     * @param string $filename
     * @param string $xmlContent
     * @param int $xml_destination
     * @return array
     */
    public function uploadXML(string $filename, string $xmlContent, int $xml_destination)
    {
        $folderPath = '';
        switch ($xml_destination){
            case PluginConfiguration::STANDARD_DESTINATION:
                $folderPath = $this->credentials['ftp_folderPath'];
                break;
            case PluginConfiguration::B2B_DESTINATION:
                $folderPath = $this->credentials['ftp_folderPath_B2B'];
                break;
            case PluginConfiguration::FBM_DESTINATION:
                $folderPath = $this->credentials['ftp_folderPath_FBM'];
                break;
        };
        return $this->libraryCall->call(
            PluginConfiguration::PLUGIN_NAME . '::upload_file',
            [
                'host' => $this->credentials['ftp_hostname'],
                'username' => $this->credentials['ftp_username'],
                'password' => $this->credentials['ftp_password'],
                'folderPath' => $folderPath,
                'privatekey' => null,
                'fileName' => $filename,
                'xmlContent' => $xmlContent
            ]
        );
    }
}