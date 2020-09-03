<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Interface for events related to exceptions and errors.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ErrorEventInterface extends EventInterface
{
    /**
     * Returns the workbench exception describing the error.
     * 
     * @return ExceptionInterface
     */
    public function getException() : ExceptionInterface;
}