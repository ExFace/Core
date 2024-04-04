<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Model\BehaviorInterface;

interface BehaviorEventInterface extends EventInterface
{
    /**
     * 
     * @return BehaviorInterface
     */
    public function getBehavior() : BehaviorInterface;
}