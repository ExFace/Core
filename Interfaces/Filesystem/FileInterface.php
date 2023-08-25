<?php
namespace exface\Core\Interfaces\Filesystem;

interface FileInterface extends \Stringable
{
    public function read() : string;
    
    public function write($stringOrBinary) : FileInterface;
    
    public function readStream();
    
    public function writeStream($resource) : FileInterface;
    
    public function getFileInfo() : FileInfoInterface;
}