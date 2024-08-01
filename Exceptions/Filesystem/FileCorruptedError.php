<?php
namespace exface\Core\Exceptions\Filesystem;

use exface\Core\Interfaces\Exceptions\FileSystemExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

class FileCorruptedError extends RuntimeException implements FileSystemExceptionInterface
{
    use FileInfoExcpetionTrait;
}