<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Model\SymfonyLexer;

class SimpleParserData
{
    public const OUTPUT_BUFFER = 'buffer';
    
    private int $nextKey = 0;
    private array $tokens;
    private int $tokenCount;
    private int $cursor = 0;
    private array $output = [];
    private array $stack = [];
    private int $currentKey;

    function __construct(string $expression)
    {
        $lexer = new SymfonyLexer($expression);
        $this->tokens = $lexer->getTokens();
        $this->tokenCount = count($this->tokens);
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
        $this->cursor = min($index, $this->tokenCount - 1);
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
    
    public function pushState(SimpleParserState $state) : SimpleParserData
    {
        $this->stack[] = [$this->currentKey, $state];
        $this->currentKey = $this->getOutputKey();
        return $this;
    }
    
    public function popState() : ?SimpleParserState
    {
        $result = array_pop($this->stack);
        if($result === null) {
            return null;
        }
        
        $this->currentKey = $result[0];
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

    public function setOutput(string $key, array $data) : SimpleParserData
    {
        $this->output[$this->currentKey][$key] = $data;
        return $this;
    }

    public function getOutputAll() : array
    {
        return $this->output;
    }
}