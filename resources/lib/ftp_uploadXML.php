<?php

require_once __DIR__ . '/FtpClient.php';

$protocol = SdkRestApi::getParam('transferProtocol');
$host = SdkRestApi::getParam('host');
$user = SdkRestApi::getParam('user');
$password = SdkRestApi::getParam('password');
$port = SdkRestApi::getParam('port');
$fileName = SdkRestApi::getParam('fileName');
$content = SdkRestApi::getParam('content');

$ftp = new FtpClient($protocol, $host, $user, $password, $port);

try {
    return $ftp->upload($fileName, $content);
} catch (\Exception $exception) {
    return [
        "error"   => true,
        "message" => $exception->getMessage()
    ];
}