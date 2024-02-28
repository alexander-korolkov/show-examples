<?php

namespace Fxtm\CopyTrading\Application;

interface FileStorageGateway
{
    public function write($file, $data);
    public function read($file);
    public function delete($file);
    public function rename($old, $new);
    public function mkdir($dir);
    public function rmdir($dir);
    public function rmfiles($dir);
    public function mvfiles($dir1, $dir2);
}
