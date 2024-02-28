<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Filesystem;

class LocalFileStorageStrategy implements FileStorageStrategy
{
    private $path = "";

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function write($name, $data)
    {
        return file_put_contents($this->filePath($name), $data);
    }

    public function read($name)
    {
        if (is_file($this->filePath($name))) {
            return file_get_contents($this->filePath($name));
        }
    }

    public function delete($name)
    {
        if (is_file($this->filePath($name))) {
            return unlink($this->filePath($name));
        }
    }

    public function rename($old, $new)
    {
        return rename($old, $new);
    }

    public function mkdir($name)
    {
        return mkdir($name);
    }

    public function rmdir($name)
    {
        return rmdir($name);
    }

    public function mvfiles($dir1, $dir2)
    {
        array_map(
            function ($file) use ($dir1, $dir2) {
                return rename("{$dir1}/{$file}", "{$dir2}/{$file}");
            },
            array_filter(
                scandir($dir1),
                function ($file) use ($dir1) {
                    return is_file("{$dir1}/{$file}");
                }
            )
        );
        return true;
    }

    public function rmfiles($dir)
    {
        array_map(
            function ($file) use ($dir) {
                return unlink("{$dir}/{$file}");
            },
            array_filter(
                scandir($dir),
                function ($file) use ($dir) {
                    return is_file("{$dir}/{$file}");
                }
            )
        );
        return true;
    }

    private function filePath($name)
    {
        return $this->path . $name;
    }
}
