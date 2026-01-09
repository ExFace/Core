<?php

namespace exface\Core\CommonLogic\Utils\SimpleParser;

class State
{
    protected string $name;
    protected array $transitions;
    protected array $patterns;

    function __construct(string $name, array $transitions)
    {
        $this->name = $name;
        $this->transitions = [];

        foreach ($transitions as $transition) {
            $pattern = $transition->getPattern();
            // Ignore empty or repeat patterns.
            if($pattern === '' || key_exists($pattern, $this->transitions)) {
                continue;
            }

            $this->patterns[substr($pattern, 0, 1)][] = $pattern;
            $this->transitions[$pattern] = $transition;
        }
    }
    
    public function getName() : string
    {
        return $this->name;
    }
    
    public function getTransition(string $pattern) : ?Transition
    {
        return $this->transitions[$pattern];
    }
    
    public function getApplicablePatterns(string $char) : array
    {
        return $this->patterns[$char] ?? [];
    }
}