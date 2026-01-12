<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

class Transition
{
    protected ?AbstractState $target;
    protected mixed $trigger;
    protected array $transitionData;
    
    function __construct(mixed $trigger, ?AbstractState $target, array $transitionData = [])
    {
        $this->target = $target;
        $this->trigger = $trigger;
        $this->transitionData = $transitionData;
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
    
    public function getTransitionData() : array
    {
        return $this->transitionData;
    }
}