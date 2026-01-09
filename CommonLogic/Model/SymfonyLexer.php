<?php

namespace exface\Core\CommonLogic\Model;

use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\TokenStream;

/**
 * Wrapper class for the `Lexer` class to reduce boilerplate and provide a uniform way of tokenizing expressions.
 * 
 * @see Lexer
 */
class SymfonyLexer
{
    protected TokenStream|null $tokenStream = null;

    private ?array $tokens = null;
    
    /**
     *
     * @param string $expression
     */
    public function __construct(string $expression)
    {
        //from symfony/ExpressionLanguage documentation:
        //Control characters (e.g. \n) in expressions are replaced with whitespace.
        //To avoid this, escape the sequence with a single backslash (e.g. \\n).
        $expression = str_replace("\n", "\\n", $expression);
        $expression = str_replace("\t", "\\t", $expression);

        $lexer = new Lexer();
        $tokenStream = $lexer->tokenize($expression);
        
        $this->tokenStream = $tokenStream;
    }

    /**
     *
     * @return array
     */
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
}