<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractState
{
    protected string $name;
    protected array $transitions;
    protected array $patterns;

    function __construct(string $name, array $transitions)
    {
        $this->name = $name;
        $this->transitions = $transitions;
    }
    
    public function getName() : string
    {
        return $this->name;
    }
    
    public function getTransitions() : array
    {
        return $this->transitions;
    }

    protected function checkTransitions($input) : AbstractState|bool
    {
        foreach ($this->transitions as $transition) {
            if($input === $transition) {
                return $transition->perform();
            }
        }

        return $this;
    }

    public abstract function process($input, &$data) : AbstractState|bool;
}