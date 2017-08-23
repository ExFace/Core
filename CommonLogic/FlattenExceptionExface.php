<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Debug\Exception\FlattenException;
use exface\Core\Interfaces\Exceptions\iContainCustomTrace;

class FlattenExceptionExface extends FlattenException
{

    public function setTraceFromException(\Exception $exception)
    {
        if ($exception instanceof iContainCustomTrace) {
            $this->setTrace($exception->getStacktrace(), null, null);
        } else {
            $this->setTrace($exception->getTrace(), $exception->getFile(), $exception->getLine());
        }
    }
}
