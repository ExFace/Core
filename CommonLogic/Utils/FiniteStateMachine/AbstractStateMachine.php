<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractStateMachine
{
    protected array $states = [];
    protected ?AbstractState $initial = null;
    protected ?AbstractState $current = null;
    
    public function addState(AbstractState $state) : bool
    {
        $name = $state->getName();
        $result = !key_exists($name, $this->states);
        
        $this->states[$name] = $state;
        
        return $result;
    }
    
    public function setInitialState(AbstractState $state) : AbstractStateMachine
    {
        $this->initial = $state;
        return $this;
    }
    
    public function process(&$data) : AbstractStateMachine
    {
        $this->current = $this->initial ?? $this->states[0];
        if($this->current === null) {
            return $this;
        }
        
        while (true) {
            $result = $this->current->process($this->getInput($data), $data);
            if(!$result instanceof AbstractState) {
                break;
            }
        }
        
        return $this;
    }
    
    protected abstract function getInput($data) : mixed;
}