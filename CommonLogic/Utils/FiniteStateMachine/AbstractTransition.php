<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractTransition
{
    protected ?AbstractState $target;
    protected mixed $trigger;
    protected array $options = [];
    
    function __construct(mixed $trigger, ?AbstractState $target, array $options = [])
    {
        $this->target = $target;
        $this->trigger = $trigger;
        $this->options = $options;
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
    
    public function getOptions() : array
    {
        return $this->options;
    }
}