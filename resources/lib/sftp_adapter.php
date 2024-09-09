<?php

require_once __DIR__ . '/FilesystemFactory.php';

$sftpFS = FilesystemFactory::create();
$params = SdkRestApi::getParam('params', []);

return call_user_func_array(
    [
        $sftpFS,
        SdkRestApi::getParam('operation')
    ],
    $params
);