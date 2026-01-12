<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractState;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\Transition;

class SimpleParserState extends AbstractState
{
    protected array $tokenRules = [];
    protected array $outputBuffer = [];
    protected int $cursor = -1;
    
    public function process($input, &$data): AbstractState|bool
    {
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
            $this->cursor += 1;

            // Check transitions.
            $transition = $this->checkTransitions($token);
            if($transition !== null) {
                // Write buffer to output.
                $this->writeBuffer($stringBuffer);
                return $this->exit($transition, $data);
            }

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

    public function exit(?Transition $transition, &$data): AbstractState|bool
    {
        if(!empty($this->outputBuffer)) {
            $data->setOutput($this->getName(), $this->outputBuffer);
            $this->outputBuffer = [];
        }
        
        $data->setCursor($this->cursor);
        
        $nextState = true;
        if($transition !== null) {
            $nextState = $transition->perform();
            // Exit transition.
            if($nextState === true) {
                $nextState = $data->popState();
                // If the stack was empty, return TRUE to exit.
                $nextState = $nextState ?? true;
            } else {
                $data->pushState($this);
            }
        }
        
        return $nextState;
    }
}