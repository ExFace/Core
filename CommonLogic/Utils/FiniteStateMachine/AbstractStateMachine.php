<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine;

abstract class AbstractStateMachine
{
    protected array $states = [];
    protected ?AbstractState $initial = null;
    protected ?AbstractState $current = null;
    protected int $maxIterations;
    protected mixed $dataRaw = null;
    protected mixed $data;

    function __construct(array $states,  int $maxIterations = 100000)
    {
        $this->states = $states;
        $this->current = $states[0];
        $this->maxIterations = $maxIterations;
    }
    
    protected function addState(AbstractState $state) : bool
    {
        $name = $state->getName();
        $result = !key_exists($name, $this->states);

        $this->states[$name] = $state;
        return $result;
    }
    
    protected function setInitialState(AbstractState $state) : AbstractStateMachine
    {
        $this->initial = $state;
        return $this;
    }
    
    public function process() : mixed
    {
        if(empty($this->states) || $this->current === null) {
            return null;
        }
        
        $data = $this->getData();
        $iterations = $this->maxIterations;
        
        while ($iterations > 0) {
            $nextState = $this->current->process($this->getInput($data), $data);
            
            if($nextState === true) {
                break;
            } else {
                $this->current = $nextState;
            }
            
            $iterations = $this->maxIterations < 0 ? $iterations : $iterations - 1;
        }
        
        return $data;
    }
    
    public function getDebugInfo() : array
    {
        return [
            'Raw Input Data' => $this->dataRaw,
            'Active State' => $this->current->getName(),
            'Input' => $this->getInput($this->data)
        ];
    }
    
    protected abstract function getData() : mixed;
    
    protected abstract function getInput($data) : mixed;
}