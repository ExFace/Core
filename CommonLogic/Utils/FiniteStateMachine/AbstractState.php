<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractState
{
    public const METADATA = 'metaData';
    
    protected string $name;
    protected array $transitions = [];
    protected array $patterns;
    protected array $metaData;

    function __construct(string $name, array $metaData = [])
    {
        $this->name = $name;
        $this->metaData = $metaData;
    }
    
    public function getName() : string
    {
        return $this->name;
    }
    
    public function getTransitions() : array
    {
        return $this->transitions;
    }
    
    public function addTransition(Transition $transition) : AbstractState
    {
        $this->transitions[] = $transition;
        return $this;
    }
    
    public function getMetaData() : array
    {
        return $this->metaData;
    }

    protected function checkTransitions($input) : ?Transition
    {
        foreach ($this->transitions as $transition) {
            if($input === $transition->getTrigger()) {
                return $transition;
            }
        }
        
        return null;
    }

    public abstract function process($input, &$data) : AbstractState|bool;
    
    public abstract function exit(?Transition $transition, &$data) : AbstractState|bool;
}