<?php
namespace exface\Core\Exceptions;

use exface\Core\Exceptions\Filesystem\FileInfoExceptionTrait;

/**
 * Exception thrown if an file is not readable although it exists.
 *
 * @author Andrej Kabachnik
 *        
 */
class FileNotReadableError extends RuntimeException
{
    use FileInfoExceptionTrait;
}