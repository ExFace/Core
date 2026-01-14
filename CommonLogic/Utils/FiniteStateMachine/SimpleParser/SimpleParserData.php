<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Model\SymfonyLexer;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractState;

class SimpleParserData
{
    private int $nextKey = 0;
    private array $tokens;
    private int $cursor = 0;
    private array $output = [];
    private array $stack = [];
    private int $currentKey;

    function __construct(string $expression)
    {
        $lexer = new SymfonyLexer($expression);
        $this->tokens = $lexer->getTokens();
        $this->currentKey = $this->getOutputKey();
    }
    
    public function getTokens() : array
    {
        return $this->tokens;
    }

    public function getCursor() : int
    {
        return $this->cursor;
    }

    public function setCursor(int $index) : SimpleParserData
    {
        $this->cursor = $index;
        return $this;
    }

    public function getToken(int $index) : ?array
    {
        return $this->tokens[$index];
    }
    
    private function getOutputKey() : string
    {
        return $this->nextKey++;
    }
    
    public function pushState(SimpleParserState $state, bool $createGroup) : SimpleParserData
    {
        $this->stack[] = [$this->currentKey, $state];
        
        if($createGroup) {
            $this->currentKey = $this->getOutputKey();
        }
        
        return $this;
    }
    
    public function popState(AbstractState $previousState) : ?SimpleParserState
    {
        $result = array_pop($this->stack);
        if($result === null) {
            return null;
        }
        
        $oldKey = $this->currentKey;
        $this->currentKey = $result[0];
        
        // New state belongs to a different group.
        if($oldKey !== $this->currentKey) {
            // Store a reference to the group we just exited.
            $prevStateName = $previousState->getName();
            $outputBuffer = $this->getOutputBuffer($prevStateName);
            $outputBuffer[] = $oldKey;
            $this->writeToOutputBuffer($prevStateName, $outputBuffer);
        }
        
        return $result[1];
    }
    
    public function getOutputBuffer(string $key) : array
    {
        $output = $this->output[$this->currentKey];
        if($output !== null) {
            $output = $output[$key];
        }
        
        return $output ?? [];
    }

    public function writeToOutputBuffer(string $key, array $data) : SimpleParserData
    {
        $this->output[$this->currentKey][$key] = $data;
        return $this;
    }

    public function getOutputAll() : array
    {
        return $this->output;
    }
    
    public function getStackInfo() : array
    {
        $result = [];
        
        foreach ($this->stack as $element) {
            if(empty($element)) {
                $result[] = null;
                continue;
            }
            
            $group = $element[0];
            $state = $element[1];
            if($state instanceof AbstractState) {
                $result[] = '[' . $group . '] ' . $state->getName();
            } else {
                $result[] = null;
            }
        }
        
        return $result;
    }
}