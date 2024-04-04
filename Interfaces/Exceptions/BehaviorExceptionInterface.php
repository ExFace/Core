<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Model\BehaviorInterface;

Interface BehaviorExceptionInterface extends ExceptionInterface
{
    /**
     * 
     * @return BehaviorInterface
     */
    public function getBehavior() : BehaviorInterface;
}