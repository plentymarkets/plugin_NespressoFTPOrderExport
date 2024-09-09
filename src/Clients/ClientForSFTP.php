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
     * @param string $operation
     * @param array $params
     * @return mixed
     */
    private function processCall($operation, $params = [])
    {
        return $this->libraryCall->call(
            PluginConfiguration::PLUGIN_NAME . '::sftp_adapter',
            [
                'operation'     => $operation,
                'params'        => $params,
                'host'          => $this->credentials['ftp_hostname'],
                'username'      => $this->credentials['ftp_username'],
                'password'      => $this->credentials['ftp_password'],
            ]
        );
    }

    /**
     * @param string $path
     * @param string $fileNamePart
     * @return mixed
     *
     * @see \League\Flysystem\FilesystemInterface::listContents
     */
    public function listFiles($path = '/', $fileNamePart = '')
    {
        $files = $this->processCall(
            'listContents',
            [
                'directory' => $path,
                'recursive' => false
            ]
        );

        $this->getLogger(self::CLASS_NAME)->debug(PluginConfiguration::PLUGIN_NAME . "::Debug.info", ['files' => $files]);

        if (!empty($fileNamePart)) {
            $files = array_filter(
                $files,
                function ($fileData) use ($fileNamePart) {
                    return strpos($fileData['path'], $fileNamePart) !== false;
                }
            );

        }

        return $files;
    }

    /**
     * @param $path
     * @param $content
     * @return bool
     *
     * @see \League\Flysystem\FilesystemInterface::write
     */
    public function uploadFile($path, $content, $config = [])
    {
        return $this->processCall(
            'write',
            [
                'path' => $path,
                'contents' => $content,
                'config' => $config
            ]);
    }

    /**
     * @param $path
     * @return string
     *
     * @see \League\Flysystem\FilesystemInterface::read
     */
    public function downloadfile($path)
    {
        return $this->processCall(
            'read',
            [
                'path' => $path
            ]
        );
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return mixed
     *
     * @see \League\Flysystem\FilesystemInterface::rename
     */
    public function rename($path, $newpath)
    {
        return $this->processCall(
            'rename',
            [
                'path' => $path,
                'newpath' => $newpath
            ]
        );
    }

    /**
     * @param string $filename
     * @param string $xmlContent
     * @return bool
     *
     * Defined for compatibility with another library
     */
    public function uploadXML(string $filename, string $xmlContent)
    {
        //$response = $this->uploadFile($filename, $xmlContent);
        //return $response;
        return $this->libraryCall->call(
            PluginConfiguration::PLUGIN_NAME . '::test',
            [
                'operation'     => $operation,
                'params'        => $params,
                'host'          => $this->credentials['ftp_hostname'],
                'username'      => $this->credentials['ftp_username'],
                'password'      => $this->credentials['ftp_password'],
            ]
        );
    }
}