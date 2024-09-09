<?php
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;


$adapter = new SftpAdapter([
    'host'          => 'sftp.plentysystems.com',
    'port'          => 22,
    'username'      => 'p68291_ftp',
    'password'      => '53rKf8yw6xDeICcQ',
    'root'          => '/',
    'timeout'       => 30,
    'directoryPerm' => 0755,
]);

/** @var Filesystem $filesystem */
$filesystem = new Filesystem($adapter);

$response = $filesystem->put('abc.txt', 'abcd');

return $response;