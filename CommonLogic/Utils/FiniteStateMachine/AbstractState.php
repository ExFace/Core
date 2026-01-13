<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractState
{
    protected string $name;
    protected array $transitionsBefore = [];
    protected array $transitionsAfter = [];
    protected array $patterns;

    function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function getName() : string
    {
        return $this->name;
    }
    
    public function addTransitionBefore(AbstractTransition $transition) : AbstractState
    {
        return $this->addTransition($transition, true);
    }

    public function addTransitionAfter(AbstractTransition $transition) : AbstractState
    {
        return $this->addTransition($transition, false);
    }
    
    protected function addTransition(AbstractTransition $transition, bool $before) : AbstractState
    {
        if($before) {
            $this->transitionsBefore[] = $transition;
            
        } else {
            $this->transitionsAfter[] = $transition;
            
        }
        
        return $this;
    }
    
    protected function checkTransitions($input, bool $beforeProcessing) : ?AbstractTransition
    {
        $transitions = $beforeProcessing ? $this->transitionsBefore : $this->transitionsAfter;
        foreach ($transitions as $transition) {
            if($input === $transition->getTrigger()) {
                return $transition;
            }
        }
        
        return null;
    }

    public abstract function process($input, &$data) : AbstractState|bool;
    
    public abstract function exit(?AbstractTransition $transition, &$data) : AbstractState|bool;
}