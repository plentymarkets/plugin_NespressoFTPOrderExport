<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

/**
 * Class SdkFilesystemFactory
 */
class FilesystemFactory
{
    /**
     * @return Filesystem
     */
    public static function create()
    {
        $adapter = new SftpAdapter([
            'host' => SdkRestApi::getParam('host'),
            'port' => 22,
            'username' => SdkRestApi::getParam('username'),
            'password' => SdkRestApi::getParam('password'),
            'timeout' => 100
        ]);

        return new Filesystem($adapter);
    }

}