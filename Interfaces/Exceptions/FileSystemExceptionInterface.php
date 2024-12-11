<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;

interface FileSystemExceptionInterface extends ExceptionInterface
{    
    public function getFileInfo() : ?FileInfoInterface;
}