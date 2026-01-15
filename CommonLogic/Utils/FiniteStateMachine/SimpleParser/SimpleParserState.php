<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractState;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractTransition;

/**
 * Parses an input string and writes the results into an output array, using its name as key.
 * Use transitions to control the parser behavior between states. Add token rules for more fine-grained control
 * over the parsed string.
 */
class SimpleParserState extends AbstractState
{
    protected array $tokenRules = [];
    protected array $outputBuffer = [];
    protected string $stringBuffer = '';
    protected int $cursor = -1;
    
    public function process($input, &$data): AbstractState|bool
    {
        if(!$data instanceof SimpleParserData) {
            return true;
        }
        
        $this->cursor = $input;
        
        // Get output buffer and string buffer.
        $this->outputBuffer = $data->getOutputBuffer($this->getName());
        $this->stringBuffer = $data->getStringBuffer();
        // We "consume" the string buffer to avoid side effects.
        $data->setStringBuffer('');
        
        // Perform parsing.
        while (true) {
            // Get next token and move the cursor.
            $token = $data->getToken($this->cursor);
            // End of file.
            if(empty($token)) {
                $this->exit(null, $data, true);
                break;
            }
            
            // Get token. TODO Read and parse token type.
            $token = $token[array_key_first($token)];
            $this->cursor += 1;

            // Check for transitions BEFORE processing.
            $transition = $this->checkTransitions($token, true);
            if($transition !== null) {
                return $this->exit($transition, $data, true);
            }

            $split = false;
            $consume = false;
            
            if(key_exists($token, $this->tokenRules)) {
                $split = $this->tokenRules[$token][0];
                $consume = $this->tokenRules[$token][1];
            }

            // Perform split, if needed.
            if($split) {
                $this->appendToOutputBuffer($this->stringBuffer);
                $this->stringBuffer = '';
            }
            
            // Add token to output buffer.
            if(!$consume) {
                $this->stringBuffer .= $token;
            }

            // Check for transitions AFTER processing.
            $transition = $this->checkTransitions($token, false);
            if($transition !== null) {
                return $this->exit($transition, $data, false);
            }
        }
        
        // Exit.
        return $this->exit(null, $data, false);
    }

    /**
     * append a given string as a new element to the current output buffer.
     * 
     * @param string $buffer
     * @return void
     */
    protected function appendToOutputBuffer(string $buffer) : void
    {
        if(empty($buffer)) {
            return;
        }
        
        $this->outputBuffer[] = $buffer;
    }

    /**
     * Add a token rule to this state.
     * 
     * Much like transitions, token rules react to a specified token. When this state encounters a token with an
     * associated rule, it will modify its behavior accordingly.
     * 
     * WARNING: Token rules are unique per token and adding a transition always produces a token rule with 
     * `($trigger, false, true)`. Be careful not to accidentally overwrite existing token rules.
     * 
     * @param string $token
     * The token this rule applies to.
     * @param bool   $split
     * If TRUE, the current buffer will be written to the output and a new string is started. Acts similar to
     * `explode($token, $string)`.
     * @param bool   $consume
     * If TRUE, the token will be consumed, without being added to the buffer. This means it will NOT show up in
     * the final output.
     * @return $this
     */
    public function addTokenRule(string $token, bool $split, bool $consume) : SimpleParserState
    {
        $this->tokenRules[$token] = [$split, $consume];
        return $this;
    }
    
    protected function addTransition(AbstractTransition $transition, bool $before): AbstractState
    {
        $this->addTokenRule($transition->getTrigger(), false, true);
        return parent::addTransition($transition, $before);
    }

    public function exit(?AbstractTransition $transition, &$data, bool $before): AbstractState|bool
    {
        if(!$data instanceof SimpleParserData) {
            return true;
        }

        $writeToken = $transition instanceof SimpleParserTransition && $transition->isWritingTokenToOutput();
        $concat = $transition instanceof SimpleParserTransition && $transition->isConcat();

        if($writeToken && ($concat || !$before)) {
            $this->stringBuffer .= $transition->getTrigger();
        }
        
        // Pass string buffer to next state.
        if($concat) {
            $data->setStringBuffer($this->stringBuffer);
        }
        // Append string buffer to output buffer.
        else {
            $this->appendToOutputBuffer($this->stringBuffer);
            
            if($writeToken) {
                $data->setStringBuffer($transition->getTrigger());
            }
        }
        
        if(!empty($this->outputBuffer)) {
            $data->writeToOutputBuffer($this->getName(), $this->outputBuffer);
            $this->outputBuffer = [];
        }
        
        $data->setCursor($this->cursor);
        
        if($transition === null) {
            return true;
        }

        $nextState = $transition->perform();
        
        // Transition return.
        if($nextState === true) {
            $nextState = $data->popState($this);
            // If the stack was empty, return TRUE to exit.
            $nextState = $nextState ?? true;
        } 
        // Transition to state.
        else {
            $createGroup = $transition instanceof SimpleParserTransition && $transition->isGroupBoundary();
            $data->pushState($this, $createGroup);
        }
        
        return $nextState;
    }
}