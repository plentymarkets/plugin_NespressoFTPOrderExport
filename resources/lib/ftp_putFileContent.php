<?php

require_once __DIR__ . '/SFtpClient.php';

$host     = SdkRestApi::getParam( 'host' );
$user     = SdkRestApi::getParam( 'username' );
$password = SdkRestApi::getParam( 'password' );
$port     = SdkRestApi::getParam( 'port' );
$fileName = SdkRestApi::getParam( 'fileName' );
$content  = SdkRestApi::getParam( 'content' );

$ftp = new SFtpClient( $host, $user, $password, $port );

return $ftp->uploadFile( $fileName, $content );