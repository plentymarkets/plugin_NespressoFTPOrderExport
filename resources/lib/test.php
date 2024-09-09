<?php
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;


$adapter = new SftpAdapter([
    'host'          => SdkRestApi::getParam('host'),
    'port'          => 22,
    'username'      => SdkRestApi::getParam('username'),
    'password'      => SdkRestApi::getParam('password'),
    'root'          => '/',
    'timeout'       => 30,
    'directoryPerm' => 0755,
]);

/** @var Filesystem $filesystem */
$filesystem = new Filesystem($adapter);

$response = $filesystem->put('abc.txt', 'abcd');

return $response;