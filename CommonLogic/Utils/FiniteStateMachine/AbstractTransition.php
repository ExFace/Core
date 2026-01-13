<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractTransition
{
    protected ?AbstractState $target;
    protected mixed $trigger;
    
    function __construct(mixed $trigger, ?AbstractState $target)
    {
        $this->target = $target;
        $this->trigger = $trigger;
    }
    
    public function getTarget() : ?AbstractState
    {
        return $this->target;
    }
    
    public function getTrigger() : mixed
    {
        return $this->trigger;
    }
    
    public function perform() : AbstractState|bool
    {
        return $this->target ?? true;
    }
}