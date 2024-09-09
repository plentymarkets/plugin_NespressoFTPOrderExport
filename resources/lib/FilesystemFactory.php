<?php

use League\Flysystem\Filesystem;
//use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

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
        /*
        $adapter = new SftpAdapter([
            'host' => SdkRestApi::getParam('host'),
            'port' => 22,
            'username' => SdkRestApi::getParam('username'),
            'password' => SdkRestApi::getParam('password'),
            'timeout' => 100
        ]);
        */
        $adapter = new Filesystem(new SftpAdapter(
            new SftpConnectionProvider(
                SdkRestApi::getParam('host'), // host (required)
                SdkRestApi::getParam('username'), // username (required)
                SdkRestApi::getParam('password'), // password (optional, default: null) set to null if privateKey is used
                null, // private key (optional, default: null) can be used instead of password, set to null if password is set
                null, // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
                22, // port (optional, default: 22)
                true, // use agent (optional, default: false)
                30, // timeout (optional, default: 10)
                10, // max tries (optional, default: 4)
                null, // host fingerprint (optional, default: null),
                null, // connectivity checker (must be an implementation of 'League\Flysystem\PhpseclibV2\ConnectivityChecker' to check if a connection can be established (optional, omit if you don't need some special handling for setting reliable connections)
            ),
            '', // root path (required)
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0640,
                    'private' => 0604,
                ],
                'dir' => [
                    'public' => 0740,
                    'private' => 7604,
                ],
            ])
        ));

        return new Filesystem($adapter);
    }

}