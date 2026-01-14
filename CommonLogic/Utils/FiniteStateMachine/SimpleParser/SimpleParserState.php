<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractState;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractTransition;

class SimpleParserState extends AbstractState
{
    protected array $tokenRules = [];
    protected array $outputBuffer = [];
    protected int $cursor = -1;
    protected bool $concatenate;

    public function __construct(string $name, bool $concatenate = false)
    {
        $this->concatenate = $concatenate;
        parent::__construct($name);
    }


    public function process($input, &$data): AbstractState|bool
    {
        if(!$data instanceof SimpleParserData) {
            return true;
        }
        
        $this->cursor = $input;
        $this->outputBuffer = $data->getOutputBuffer($this->getName());
        $stringBuffer = '';
        
        // Perform parsing.
        while (true) {
            // Get next token and move the cursor.
            $token = $data->getToken($this->cursor);
            // End of file.
            if(empty($token)) {
                $this->writeBuffer($stringBuffer);
                break;
            }
            $token = $token[array_key_first($token)];
            
            // Check for transitions BEFORE processing.
            $transition = $this->checkTransitions($token, true);
            if($transition !== null) {
                // Write buffer to output.
                $this->writeBuffer($stringBuffer);
                return $this->exit($transition, $data);
            }

            $this->cursor += 1;
            $split = false;
            $consume = false;
            
            if(key_exists($token, $this->tokenRules)) {
                $split = $this->tokenRules[$token][0];
                $consume = $this->tokenRules[$token][1];
            }

            // Perform split, if needed.
            if($split) {
                $this->writeBuffer($stringBuffer);
                $stringBuffer = '';
            }
            
            // Add token to output buffer.
            if(!$consume) {
                $stringBuffer .= $token;
            }

            // Check for transitions AFTER processing.
            $transition = $this->checkTransitions($token, false);
            if($transition !== null) {
                // Write buffer to output.
                $this->writeBuffer($stringBuffer);
                return $this->exit($transition, $data);
            }
        }
        
        // Exit.
        return $this->exit(null, $data);
    }
    
    protected function writeBuffer(string $buffer) : void
    {
        if(empty($buffer)) {
            return;
        }
        
        $this->outputBuffer[] = $buffer;
    }
    
    public function addTokenRule(string $token, bool $split, bool $consume) : SimpleParserState
    {
        $this->tokenRules[$token] = [$split, $consume];
        return $this;
    }

    protected function addTransition(AbstractTransition $transition, bool $before): AbstractState
    {
        if($transition instanceof SimpleParserTransition && $transition->isConsumingToken()) {
            $this->addTokenRule($transition->getTrigger(), false, true);
        }
        
        return parent::addTransition($transition, $before);
    }

    public function exit(?AbstractTransition $transition, &$data): AbstractState|bool
    {
        if(!$data instanceof SimpleParserData) {
            return true;
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