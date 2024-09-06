<?php

use phpseclib3\Net\SFTP;

/**
 * Class FtpClient
 */
class SFtpClient
{

    private $server;
    private $username;
    private $password;

    private $port;

    private $sftp;

    public function __construct( string $server, string $username, string $password, string $port )
    {
        $this->server   = $server;
        $this->username = $username;
        $this->password = $password;
        $this->port     = $port;
    }

    private function login()
    {
        return $this->sftp->login( $this->username, $this->password );
    }

    /**
     * @param string $fileName
     * @param string  $content
     *
     * @return bool
     */
    public function uploadFile( string $fileName, string $content )
    {
        define('NET_SFTP_LOGGING', 2);
        define('NET_SSH2_LOGGING', 2);
        $this->sftp = new SFTP( $this->server, $this->port );
        if (!$this->login()){
            return [
                'error' => 'true',
                'error_msg' => 'could not authenticate',
                'server'    => $this->server,
                'user'  => $this->username,
                'pass' => $this->password,
                'all_errors'=> $this->sftp->getSFTPErrors(),
                'logs'=>$this->sftp->getSFTPLog()
            ];
        }
        if( $fp = fopen( 'php://temp', 'w+' ) )
        {
            try
            {
                fwrite( $fp, $content);
                rewind( $fp );
                return [
                    'error' => 'false',
                    'response'  => $this->sftp->put( $fileName, $fp, SFTP::SOURCE_LOCAL_FILE )
                ];
            }
            catch (\Throwable $exception) {
                return [
                    'error' => 'true',
                    'error_msg' => $exception->getMessage()
                ];
            }
            finally
            {
                fclose( $fp );
            }
        }
        return [
            'error' => 'true',
            'error_msg' => 'could not write to SFTP server'
        ];
    }

}