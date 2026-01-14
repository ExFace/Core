<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractTransition;

class SimpleParserTransition extends AbstractTransition
{
    public const GROUP = 'group';
    public const CONCAT = 'concat';
    public const WRITE_TOKEN = 'write_token';
    
    public function isGroupBoundary() : bool
    {
        return in_array(self::GROUP, $this->getOptions());
    }

    public function isConcat() : bool
    {
        return in_array(self::CONCAT, $this->getOptions());
    }

    public function isWritingTokenToOutput() : bool
    {
        return in_array(self::WRITE_TOKEN, $this->getOptions());
    }
}