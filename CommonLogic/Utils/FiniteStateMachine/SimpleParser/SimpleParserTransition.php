<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractState;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractTransition;

class SimpleParserTransition extends AbstractTransition
{
    private bool $isGroupBoundary;
    private bool $consumeToken;

    public function __construct(
        mixed $trigger,
        ?AbstractState $target,
        bool $isGroupBoundary = false,
        bool $consumeToken = true
    )
    {
        $this->isGroupBoundary = $isGroupBoundary;
        $this->consumeToken = $consumeToken;
        parent::__construct($trigger, $target);
    }
    
    public function isGroupBoundary() : bool
    {
        return $this->isGroupBoundary;
    }
    
    public function isConsumingToken() : bool
    {
        return $this->consumeToken;
    }
}