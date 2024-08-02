<?php
namespace exface\Core\Exceptions\Filesystem;

use exface\Core\Interfaces\Exceptions\FileSystemExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

class FileCorruptedError extends RuntimeException implements FileSystemExceptionInterface
{
    use FileInfoExceptionTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7WVU9TJ';
    }
}