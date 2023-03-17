<?php
namespace exface\Core\Exceptions\PWA;

use exface\Core\Exceptions\NotFoundError;

/**
 * Exception thrown if the requested offline data set is not part of the PWA.
 *
 * @author Andrej Kabachnik
 *        
 */
class PWADatasetNotFoundError extends NotFoundError
{
}