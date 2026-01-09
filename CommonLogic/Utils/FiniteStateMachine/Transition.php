<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

class Transition
{
    protected AbstractState $target;
    protected mixed $trigger;
    
    function __construct(string $trigger, AbstractState $target)
    {
        $this->target = $target;
        $this->trigger = $trigger;
    }
    
    public function getTarget() : AbstractState
    {
        return $this->target;
    }
    
    public function getTrigger() : string
    {
        return $this->trigger;
    }
    
    public function perform() : AbstractState|bool
    {
        return $this->target;
    }
}