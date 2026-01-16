<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Model\SymfonyLexer;
use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractState;

/**
 * Serves as the core of the `SimpleParser`, by holding both the tokens to be processed, the cursor and the output
 * buffer. This class also manages the stack and facilitates communication between states.
 */
class SimpleParserData
{
    private int $nextKey = 0;
    private array $tokens;
    private int $cursor = 0;
    private array $output = [];
    private array $stack = [];
    private int $currentKey;
    private string $stringBuffer = '';

    function __construct(string $expression)
    {
        $lexer = new SymfonyLexer($expression);
        $this->tokens = $lexer->getTokens();
        $this->currentKey = $this->getGroupKey();
    }

    /**
     * Returns an array with all tokens generated from the input string.
     * 
     * @return array
     */
    public function getTokens() : array
    {
        return $this->tokens;
    }

    /**
     * Points to the currently active token. 
     * 
     * NOTE: The cursor is unbounded and may point to an invalid index, i.e. `null`.
     * 
     * @return int
     */
    public function getCursor() : int
    {
        return $this->cursor;
    }

    /**
     * Set the currently active token.
     *
     * NOTE: The cursor is unbounded and may point to an invalid index, i.e. `null`.
     * 
     * @param int $index
     * @return $this
     */
    public function setCursor(int $index) : SimpleParserData
    {
        $this->cursor = $index;
        return $this;
    }

    /**
     * Get the token at the specified index.
     * 
     * @param int $index
     * @return array|null
     */
    public function getToken(int $index) : ?array
    {
        return $this->tokens[$index];
    }

    /**
     * Generates a new group key.
     * 
     * @return string
     */
    private function getGroupKey() : string
    {
        return $this->nextKey++;
    }

    /**
     * Pushes a state onto the stack.
     * 
     * @param SimpleParserState $state
     * @param bool              $createGroup
     * If TRUE, a new output group will be created. This group is automatically concluded, when the pushed state
     * is popped from the stack.
     * @return $this
     */
    public function pushState(SimpleParserState $state, bool $createGroup) : SimpleParserData
    {
        $this->stack[] = [$this->currentKey, $state];
        
        if($createGroup) {
            $this->currentKey = $this->getGroupKey();
        }
        
        return $this;
    }

    /**
     * Pop the last state from the stack and returns it. If that state was a group boundary, its output group
     * will be closed and a reference to it is written to the output buffer.
     * 
     * @param AbstractState $previousState
     * @return SimpleParserState|null
     */
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

    /**
     * Get the output buffer for a specific state from the currently active output group.
     * 
     * @param string $stateName
     * @return array
     */
    public function getOutputBuffer(string $stateName) : array
    {
        $output = $this->output[$this->currentKey];
        if($output !== null) {
            $output = $output[$stateName];
        }
        
        return $output ?? [];
    }

    /**
     * Write to the output buffer of the currently active output group for a specific state.
     * 
     * @param string $stateName
     * @param array  $data
     * @return $this
     */
    public function writeToOutputBuffer(string $stateName, array $data) : SimpleParserData
    {
        $this->output[$this->currentKey][$stateName] = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getOutputAll() : array
    {
        return $this->output;
    }

    /**
     * Returns an array containing useful debug info about the stack.
     * 
     * @return array
     */
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

    /**
     * Get the current string buffer. If you intend to transform the result, make sure to
     * consume the buffer by using `setStringBuffer('')`.
     * 
     * @return string
     */
    public function getStringBuffer() : string
    {
        return $this->stringBuffer;
    }

    /**
     * Set the current string buffer.
     * 
     * @param string $value
     * @return $this
     */
    public function setStringBuffer(string $value) : SimpleParserData
    {
        $this->stringBuffer = $value;
        return $this;
    }
}