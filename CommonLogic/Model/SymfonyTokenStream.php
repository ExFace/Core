<?php

namespace exface\Core\CommonLogic\Model;

use Symfony\Component\ExpressionLanguage\Lexer;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;

class SymfonyTokenStream implements FormulaTokenStreamInterface
{
    private $tokenStream = null;
    
    private $tokens = null;
    
    private $formName = null;
    
    private $nestedForms = null;
    
    private $arguments = null;
    
    public function __construct(string $expression)
    {
        $lexer = new Lexer();
        $tokenStream = $lexer->tokenize($expression);
        $this->tokenStream = $tokenStream;
    }
    
    protected function getTokens() : array
    {
        if ($this->tokens === null) {
            $tokens = [];
            do {
                $tok = $this->tokenStream->current;
                $tokens[] = [$tok->type => $tok->value];
                $this->tokenStream->next();
            } while (! $this->tokenStream->isEOF());
            $this->tokens = $tokens;
        }
        return $this->tokens;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFormulaName() : ?string
    {
        if ($this->formName === null) {
            $tokens = $this->getTokens();
            for ($i = 0; $i < count($tokens); $i++) {
                $token = $tokens[$i];
                if ($token['name']) {
                    if ($tokens[$i+1]['punctuation'] === '(') {
                        $this->formName = $token['name'];
                        break;
                    }
                }
            }
        }
        return $this->formName;
    }
    
    /**
     * 
     * @return array
     */
    public function getNestedFormulas() : array
    {
        if ($this->nestedForms === null) {
            $formName = $this->getFormulaName();
            $tokens = $this->getTokens();
            $nestedForms = [];
            for ($i = 0; $i < count($tokens); $i++) {
                $token = $tokens[$i];
                if ($token['name'] && $token['name'] !== $formName) {
                    if ($tokens[$i+1]['punctuation'] === '(') {
                        $nestedForms[] = $token['name'];
                    }
                }
            }
            $this->nestedForms = $nestedForms;
        }
        return $this->nestedForms;
    }
    
    /**
     * 
     * @return array
     */
    public function getArguments() : array
    {
        if ($this->arguments === null) {
            $tokens = $this->getTokens();
            $arguments = [];
            for ($i = 0; $i < count($tokens); $i++) {
                $token = $tokens[$i];
                if ($token['name']) {
                    if ($tokens[$i+1]['punctuation'] !== '(') {
                        $arguments = $token['name'];
                    }
                }
            }
            $this->arguments = $arguments;
        }
        return $this->arguments;
    }
    
    /**
     * 
     * @return string
     */
    public function getExpression() : string
    {
        return $this->tokenStream->getExpression();
    }
}