<?php

namespace NespressoFTPOrderExport\Clients;

use Exception;
use NespressoFTPOrderExport\Configuration\PluginConfiguration;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\Log\Loggable;

class SFTPClient
{
    use Loggable;

    const TRANSFER_PROTOCOL = 'SFTP';

    /** @var LibraryCallContract */
    private $library;

    /** @var array */
    private $credentials;

    /**
     * SFTPClient constructor.
     *
     * @param  LibraryCallContract  $library
     * @param  PluginConfiguration  $pluginConfig
     *
     * @throws \Exception
     */
    public function __construct(LibraryCallContract $library, PluginConfiguration $pluginConfig)
    {
        $this->library = $library;

        try {
            $this->credentials = $pluginConfig->getSFTPCredentials();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    public function uploadXML(string $fileName, string $content)
    {
        $startTime = microtime(true);
        $result = $this->library->call(PluginConfiguration::PLUGIN_NAME . "::ftp_putFileContent", [
            'transferProtocol' => self::TRANSFER_PROTOCOL,
            'host'             => $this->credentials['ftp_hostname'],
            'user'             => $this->credentials['ftp_username'],
            'privateKeyContent'=> $this->credentials['ftp_password'],
            'publicKeyContent' => '',
            'port'             => $this->credentials['ftp_port'],
            'path'             => './' . $fileName,
            'content'          => $content
        ]);

        if (is_array($result) && array_key_exists('error', $result) && $result['error'] === true) {
            $endTime = microtime(true);
            $this->getLogger(__METHOD__)
                ->error(PluginConfiguration::PLUGIN_NAME . '::globals.ftpFileUploadError',
                    [
                        'errorMsg'  => $result['message'],
                        'host'      => $this->credentials['ftp_hostname'],
                        'user'      => $this->credentials['ftp_username'],
                        'port'      => $this->credentials['ftp_port'],
                        'fileName'  => $fileName,
                        'time'      => ($endTime - $startTime)
                    ]
                );

            throw new \Exception($result['error_msg']);
        }

        return $result;
    }
}
