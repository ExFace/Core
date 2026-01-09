<?php

namespace exface\Core\CommonLogic\Utils\SimpleParser;

class FiniteStateMachine
{
    protected array $states = [];
    protected ?State $current = null;
    
    public function addState(string $name, array $transitions) : bool
    {
        $result = !key_exists($name, $this->states);
        $this->states[$name] = $transitions;
        
        return $result;
    }
}