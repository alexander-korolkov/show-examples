<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Filesystem;

use Fxtm\CopyTrading\Application\FileStorageGateway;

class FileStorageFacade implements FileStorageGateway
{
    private $storage = null;

    public function __construct(FileStorageStrategy $fileStorage)
    {
        $this->storage = $fileStorage;
    }

    public function write($file, $data)
    {
        return $this->storage->write($file, $data);
    }

    public function read($file)
    {
        return $this->storage->read($file);
    }

    public function delete($file)
    {
        return $this->storage->delete($file);
    }

    public function rename($old, $new)
    {
        return $this->storage->rename($old, $new);
    }

    public function mkdir($dir)
    {
        return $this->storage->mkdir($dir);
    }

    public function rmdir($dir)
    {
        return $this->storage->rmdir($dir);
    }

    public function mvfiles($dir1, $dir2)
    {
        return $this->storage->mvfiles($dir1, $dir2);
    }

    public function rmfiles($dir)
    {
        return $this->storage->rmfiles($dir);
    }
}
