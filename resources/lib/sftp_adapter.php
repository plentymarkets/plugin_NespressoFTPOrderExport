<?php

require_once 'SdkFilesystemFactory.php';
require_once 'CsvHelper.php';

$sftpFS = SDKFilesystemFactory::create();
$params = SdkRestApi::getParam('params', []);

return call_user_func_array(
    [
        $sftpFS,
        SdkRestApi::getParam('operation')
    ],
    $params
);