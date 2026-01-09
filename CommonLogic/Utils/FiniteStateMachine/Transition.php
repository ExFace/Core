<?php

namespace exface\Core\CommonLogic\Utils\SimpleParser;

class Transition
{
    protected State $target;
    protected string $pattern;
    
    function __construct(string $pattern, State $target)
    {
        $this->target = $target;
        $this->pattern = $pattern;
    }
    
    public function getTarget() : State
    {
        return $this->target;
    }
    
    public function getPattern() : string
    {
        return $this->pattern;
    }
}