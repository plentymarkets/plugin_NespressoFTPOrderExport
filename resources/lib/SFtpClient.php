<?php

use phpseclib\Net\SFTP;

/**
 * Class FtpClient
 */
class SFtpClient
{

    private $server;
    private $username;
    private $password;

    private $sftp;

    public function __construct( string $server, string $username, string $password, string $port )
    {
        $this->server   = $server;
        $this->username = $username;
        $this->password = $password;

        $this->sftp = new SFTP( $server, $port );

        $this->login();
    }

    private function login()
    {
        return $this->sftp->login( $this->username, $this->password );
    }

    /**
     * Get the file names from the specified path
     *
     * @param string $path
     *
     * @return array
     */
    public function getFileNames( string $path ) : array
    {
        return $this->sftp->nlist( $path );
    }

    /**
     * Get the content of a file
     *
     * @param string $fileName
     *
     * @return string|array
     */
    public function downloadFile( string $fileName ) : string
    {
        return $this->sftp->get( $fileName );
    }

    /**
     * @param string $fileName
     * @param string $newFileName
     *
     * @return string
     */
    public function renameFile( string $fileName, string $newFileName ) : string
    {
        //return $this->sftp->rename( $fileName, $newFileName );
    }

    /**
     * @param string $fileName
     * @param string  $content
     *
     * @return bool
     */
    public function uploadFile( string $fileName, string $content ) : bool
    {
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

    /**
     * @param string $fileName
     * @param string $content
     *
     * @return bool
     */
    public function uploadRawFile( string $fileName, $content ) : bool
    {
        if( $fp = fopen( 'php://temp', 'w+' ) )
        {
            try
            {
                fwrite( $fp, $content );
                rewind( $fp );

                return $this->sftp->put( $fileName, $fp, SFTP::SOURCE_LOCAL_FILE );
            }
            finally
            {
                fclose( $fp );
            }
        }
    }


    public function delete( string $path )
    {
        return $this->sftp->delete( $path );
    }

}