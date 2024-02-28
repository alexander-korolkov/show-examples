<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Filesystem;

use Exception;

class RemoteFileStorageStrategy implements FileStorageStrategy
{
    private $sftp = null;
    private $path = "";

    /**
     * @var array
     */
    private $config;

    public function __construct(
        array  $hosts,
        string $path,
        string $user,
        string $publicKeyPath,
        string $privateKeyPath
    ) {
        $this->path = $path;
        $this->config = [
            'hosts' => $hosts,
            'path' => $path,
            'user' => $user,
            'pub_key_path' => $publicKeyPath,
            'key_path' => $privateKeyPath,
        ];
    }

    private function sftpConnect()
    {
        $host = array_shift($this->config["hosts"]);
        $port = 22;

        if (!($ssh = ssh2_connect($host, $port, ['hostkey' => 'ssh-rsa']))) {
            throw new Exception("Could not connect to SSH server: {$host}:{$port}");
        }

        if (!ssh2_auth_pubkey_file($ssh, $this->config["user"], $this->config["pub_key_path"], $this->config["key_path"])) {
            throw new Exception("Could not authenticate: {$this->config["user"]}@{$host}:{$port}");
        }

        $this->sftp = ssh2_sftp($ssh);
        if (!$this->sftp) {
            throw new Exception("Could not initialize SFTP subsystem");
        }
    }

    public function write($name, $data)
    {
        try {
            $file = $this->openRemoteFile($name, 'w');
            if (false === (fwrite($file, $data))) {
                throw new Exception("Could not write data to remote file: {$this->filePath($name)}");
            }
            return true;
        } finally {
            fclose($file);
        }
    }

    public function read($name)
    {
        try {
            $file = $this->openRemoteFile($name, 'r');
            if (false === ($data = fread($file, filesize($this->remotePath($name))))) {
                throw new Exception("Could not read data from remote file: {$this->filePath($name)}");
            }
            return $data;
        } finally {
            fclose($file);
        }
    }

    public function delete($name)
    {
        if (!$this->sftp) {
            $this->sftpConnect();
        }

        return ssh2_sftp_unlink($this->sftp, $this->filePath($name));
    }

    public function rename($old, $new)
    {
        if (!$this->sftp) {
            $this->sftpConnect();
        }

        return ssh2_sftp_rename($this->sftp, $this->filePath($old), $this->filePath($new));
    }

    public function mkdir($name)
    {
        if (!$this->sftp) {
            $this->sftpConnect();
        }

        return ssh2_sftp_mkdir($this->sftp, $this->filePath($name));
    }

    public function rmdir($name)
    {
        if (!$this->sftp) {
            $this->sftpConnect();
        }

        return ssh2_sftp_rmdir($this->sftp, $this->filePath($name));
    }

    public function mvfiles($dir1, $dir2)
    {
        return !empty(
            array_map(
                function ($file) use ($dir1, $dir2) {
                    //if (is_file("{$dir1}/{$file}")) {
                        return $this->rename("{$dir1}/{$file}", "{$dir2}/{$file}");
                    //}
                },
                scandir($this->remotePath($dir1))
            )
        );
    }

    public function rmfiles($dir)
    {
        return !empty(
            array_map(
                function ($file) use ($dir) {
                    //if (is_file("{$dir}/{$file}")) {
                        return $this->delete("{$dir}/{$file}");
                    //}
                },
                scandir($this->remotePath($dir))
            )
        );
    }

    private function openRemoteFile($name, $mode)
    {
        if (false === ($file = @fopen($this->remotePath($name), $mode))) {
            throw new Exception("Could not open remote file: {$this->filePath($name)}");
        }
        return $file;
    }

    private function remotePath($name)
    {
        if (!$this->sftp) {
            $this->sftpConnect();
        }

        return sprintf("ssh2.sftp://%s%s", intval($this->sftp), $this->filePath($name));
    }

    private function filePath($name)
    {
        return $this->path . $name;
    }
}
