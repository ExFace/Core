<?php
namespace exface\Core\Exceptions;

use exface\Core\Exceptions\Filesystem\FileInfoExceptionTrait;

/**
 * Exception thrown if an file is not writable although it exists.
 *
 * @author Andrej Kabachnik
 *        
 */
class FileNotWritableError extends RuntimeException
{
    use FileInfoExceptionTrait;
}