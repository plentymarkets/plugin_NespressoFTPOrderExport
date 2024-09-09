<?php

require_once __DIR__ . '/SdkFilesystemFactory.php';

$sftpFS = SDKFilesystemFactory::create();
$params = SdkRestApi::getParam('params', []);

return call_user_func_array(
    [
        $sftpFS,
        SdkRestApi::getParam('operation')
    ],
    $params
);