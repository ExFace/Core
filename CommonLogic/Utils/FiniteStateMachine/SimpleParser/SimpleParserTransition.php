<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractTransition;

/**
 * Basic transition between parser states. If you pass `null` as target, the transition acts as RETURN.
 * 
 * You can pass the following options:
 * - `SimpleParserTransition::GROUP`
 * - `SimpleParserTransition::CONCAT`
 * - `SimpleParserTransition::WRITE_TOKEN`
 * 
 * @see SimpleParserTransition::GROUP, SimpleParserTransition::CONCAT, SimpleParserTransition::WRITE_TOKEN
 */
class SimpleParserTransition extends AbstractTransition
{
    /**
     * When triggered, the parser will create a new group to encapsulate the state
     * this transition leads into. Has no effect if target is `null`.
     */
    public const GROUP = 'group';
    
    /**
     * Passes the current output of this state to the target state. As a result the
     * target state will "continue the current sentence". If target is `null` the state being RETURNED to 
     * "continues the sentence".
     */
    public const CONCAT = 'concat';
    
    /**
     * Usually, the token that triggers a transition is consumed. Pass this option
     * if you want the state to write the token to its output.
     */
    public const WRITE_TOKEN = 'write_token';

    /**
     * TRUE if a new group should be created upon performing this transition.
     * 
     * @return bool
     */
    public function isGroupBoundary() : bool
    {
        return in_array(self::GROUP, $this->getOptions());
    }

    /**
     * TRUE if the current output should be passed to the target state.
     * 
     * @return bool
     */
    public function isConcat() : bool
    {
        return in_array(self::CONCAT, $this->getOptions());
    }

    /**
     * TRUE if the trigger token should be written to the output.
     * 
     * @return bool
     */
    public function isWritingTokenToOutput() : bool
    {
        return in_array(self::WRITE_TOKEN, $this->getOptions());
    }
}