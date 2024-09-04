<?php

use phpseclib\Net\SFTP;

/**
 * Class SftpClient
 */
class SftpClient
{
    /**
     * @var string
     */
    private $server;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var int
     */
    private $port;

    /**
     * @var resource
     */
    private $sftp;

    /**
     * FtpClient constructor.
     *
     * @param string $server
     * @param string $username
     * @param string $password
     * @param int $port
     */
    public function __construct(string $server, string $username, string $password, int $port)
    {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    /**
     * FtpClient destructor.
     */
    public function __destruct()
    {
        try {
            ssh2_disconnect($this->sftp);
        }
        catch (\Throwable $exception) {}
    }

    /**
     * Creates a new SFTP instance and logs in.
     *
     * @throws Exception
     * @return resource
     */
    private function connect()
    {
        try {
            if(is_null($this->sftp)) {
                $connection = ssh2_connect($this->server, $this->port);
                ssh2_auth_password($connection, $this->username, $this->password);
                $this->sftp = ssh2_sftp($connection);
            }
        } catch (\Throwable $exception) {
            throw new Exception(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * Get the file names from the specified path.
     *
     * @param string $path
     * @return mixed
     * @throws Exception
     */
    public function getFileNames(string $path)
    {
        $this->connect();

        try {
            $files = scandir('ssh2.sftp://'.intval($this->server).'/'.$path);
            $response = [];

            foreach($files as $file) {
                if($file == '.' || $file == '..') {
                    continue;
                }

                $updatedAt = filemtime('ssh2.sftp://'.intval($this->server).'/'.$path.'/'.$file);
                $response[$file] = ['updatedAt' => $updatedAt];
            }

            return $response;
        } catch (\Throwable $exception) {
            throw new Exception(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * Get the content of a file.
     *
     * @param string $path
     * @param string $filename
     * @return string
     * @throws Exception
     */
    public function download(string $path, string $filename):string
    {
        $this->connect();

        try {
            return file_get_contents('ssh2.sftp://'.intval($this->server).'/'.$path.'/'.$filename);
        } catch (\Throwable $exception) {
            throw new Exception(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * Uploads a file.
     *
     * @param string $path
     * @param string $content
     * @return bool
     * @throws Exception
     */
    public function put(string $path, string $content)
    {
        if (strlen($path) && strlen($content))
        {
            $this->connect();

            try {
                return (bool)file_put_contents('ssh2.sftp://'.intval($this->server).'/'.$path, $content);
            } catch (\Throwable $exception) {
                throw new Exception(__METHOD__.' '.$exception->getMessage());
            }
        }
        return false;
    }

    public function move(string $pathFrom, string $pathTo) {
        if (strlen($pathFrom) && strlen($pathTo))
        {
            $this->connect();

            try {
                $result = ssh2_sftp_rename($this->sftp, $pathFrom, $pathTo);

                return true;
            } catch (\Throwable $exception) {
                throw new Exception(__METHOD__.' '.$exception->getMessage());
            }
        }

        return false;
    }
}