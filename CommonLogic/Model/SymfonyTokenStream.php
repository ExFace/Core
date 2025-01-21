<?php

namespace exface\Core\CommonLogic\Model;

use Symfony\Component\ExpressionLanguage\Lexer;
use exface\Core\Formulas\Calc;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Exceptions\FormulaError;

/**
 * Wrapper class to extract formula name, nested formulas and attributes
 * from a given expression using the Symfony/ExpressionLanguage/Lexer class
 * 
 * @author ralf.mulansky
 *
 */
class SymfonyTokenStream implements FormulaTokenStreamInterface
{
    private $tokenStream = null;
    
    private $tokens = null;
    
    private $formName = null;
    
    private $nestedForms = null;
    
    private $formulas = null;
    
    private $attributes = null;
    
    private $arguments = null;
    
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
        
        try {
            $lexer = new Lexer();
            $tokenStream = $lexer->tokenize($expression);
        } catch (\Throwable $e) {
            throw new FormulaError('Cannot parse formula: ' . $e->getMessage(), '7R34E52', $e);
        }
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
    
    /**
     * 
     * @return array
     */
    protected function getFormulas() : array
    {
        if ($this->formulas === null) {
            $forms = [];
            $delim = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
            $tokens = $this->getTokens();
            $buffer = null;
            $count = count($tokens);
            foreach ($tokens as $i => $token) {
                $prev = $i > 0 ? $tokens[$i-1] : null;
                $next = $i < $count - 1 ? $tokens[$i+1] : null;
                $name = $token['name'] ?? null;
                if ($name === null) {
                    continue;
                }
                //if token is preceeded by a ':' it might be an aggregation, so check that, else it's a formula
                if ($prev !== null && (':' === $prev['punctuation'] ?? null) && AggregatorFunctionsDataType::isValidStaticValue($name) === true) {
                    continue;
                }
                
                //if the token is followd by a '.' it's a formula with namespace, therefor buffer the token
                if ($next !== null && $delim === $next['punctuation'] ?? null) {
                    $buffer .= $name . $delim;
                    continue;
                }
                if ($next !== null && '(' === $next['punctuation'] ?? null) {
                    if ($prev !== null && ($delim === $prev['punctuation'] ?? null) && $buffer) {
                        $forms[] = $buffer . $name;
                    } else {
                        $forms[] = $name;
                    }
                    $buffer = null;
                }
            }            
            $this->formulas = $forms;
        }
        return $this->formulas;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getFormulaName()
     */
    public function getFormulaName() : ?string
    {
        if ($this->formName === null) {
            $name = $this->getFormulas()[0];
            //if expression does not contain formula name, e.g. '(1 + 1)', the formula is the Calc class formula
            if ($name === null) {
                $name = Calc::class;
            }
            $this->formName = $name;
        }        
        return $this->formName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getNestedFormulas()
     */
    public function getNestedFormulas() : array
    {
        if ($this->nestedForms === null) {
            $nestedForms = $this->getFormulas();
            array_shift($nestedForms);
            $nestedForms = array_unique($nestedForms);
            $this->nestedForms = $nestedForms;
        }
        return $this->nestedForms;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getAttributes()
     */
    public function getAttributes() : array
    {
        if ($this->attributes === null) {
            $this->extractArgumentsAndAttributes();
        }
        return $this->attributes;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::isArgumentAttribute()
     */
    public function getArgument(int $index) : ?string
    {
        return $this->getArguments()[$index] ?? null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::isArgumentAttribute()
     */
    public function isArgumentAttribute(int $index) : bool
    {
        $arg = $this->getArgument($index);
        if ($arg === null) {
            return false;
        }
        $attrs = $this->getAttributes();
        return in_array($arg, $attrs, true) === true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getArguments()
     */
    public function getArguments() : array
    {
        if ($this->arguments === null) {
            $this->extractArgumentsAndAttributes();            
        }
        return $this->arguments;
    }
    
    protected function extractArgumentsAndAttributes() : void
    {
        $attributes = [];
        $args = [];
        $tokens = $this->getTokens();
        $buffer = null;
        foreach ($tokens as $i => $token) {
            if (null !== $t = $token['string'] ?? null) {
                $args[] = $t;
                continue;
            }
            if (null !== $t = $token['number'] ?? null) {
                $args[] = $t;
                continue;
            }
            
            //every token with 'name' key gets to the arguments unless it is the formula name itself
            // TODO maybe should also extract nested formulas here so we can run this functions once and have all tokens categorized
            if (null !== ($name = $token['name'] ?? null) && $name !== $this->getFormulaName()) {
                
                if (mb_strtoupper($name) === 'TRUE' || mb_strtoupper($name) === 'FALSE') {
                    $args[] = $name;
                    continue;
                }
                
                $nextPunct = $tokens[$i+1]['punctuation'] ?? null;
                switch (true) {
                    //if the token is followed by a ':' and  the following token is an aggregation,
                    //its an attribute alias with an aggregation, therefor buffer the token
                    case $nextPunct === ':' && AggregatorFunctionsDataType::isValidStaticValue($tokens[$i+2]['name']):
                        $buffer .= $name . ':';
                        break;
                        //if the token is followed by '[' or ']' it's an attribute alias with relation modifier
                    case $nextPunct === '[' || $nextPunct === ']':
                        $buffer .= $name . $nextPunct;
                        break;
                    case $nextPunct !== '(' && $nextPunct !== '.':
                        $args[] = $buffer . $name;
                        $attributes[] = $buffer . $name;
                        $buffer = null;
                        break;
                    default:
                        $buffer = null;
                }
            }
        }
        $this->attributes = $attributes;
        $this->arguments = $args;
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::__toString()
     */
    public function __toString() : string
    {
        return $this->tokenStream->getExpression();
    }
}