<?php

namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

interface BehaviorDependencyInterface
{
    public function apply(
        BehaviorInterface     $toBehavior, 
        BehaviorListInterface $behaviors,
        array                 $behaviorClasses
    ) : void;
}