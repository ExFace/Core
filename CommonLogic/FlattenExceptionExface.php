<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Debug\Exception\FlattenException;
use exface\Core\Interfaces\Exceptions\iContainCustomTrace;

/**
 * Extends the FlattenException-class to support errors with custom stack traces.
 * 
 * @author SFL
 *
 */
class FlattenExceptionExface extends FlattenException
{

    /**
     * 
     * {@inheritDoc}
     * @see \Symfony\Component\Debug\Exception\FlattenException::setTraceFromException()
     */
    public function setTraceFromException(\Exception $exception)
    {
        if ($exception instanceof iContainCustomTrace) {
            // Wenn der Fehler einen custom Stacktrace enthaelt, dann diesen verwenden.
            // Das ist notwendig, da es in PHP nicht moeglich ist den Stacktrace
            // manuell zu setzen oder zu veraendern.
            $this->setTrace($exception->getStacktrace(), null, null);
        } else {
            $this->setTrace($exception->getTrace(), $exception->getFile(), $exception->getLine());
        }
    }
}
