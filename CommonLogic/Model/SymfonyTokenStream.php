<?php

namespace exface\Core\CommonLogic\Model;

use Symfony\Component\ExpressionLanguage\Lexer;
use exface\Core\Formulas\Calc;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\StringDataType;

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
            foreach ($tokens as $i => $token) {
                $name = $token['name'] ?? null;
                if ($name === null) {
                    continue;
                }
                //if token is preceeded by a ':' it might be an aggregation, so check that, else it's a formula
                if ($tokens[$i-1]['punctuation'] !== ':' && AggregatorFunctionsDataType::isValidStaticValue($name) === false) {
                    //if the token is followd by a '.' ist a formula with namespace, therefor buffer the token
                    if ($tokens[$i+1]['punctuation'] === $delim) {
                        $buffer .= $name . $delim;
                        continue;
                    }
                    if ($tokens[$i+1]['punctuation'] === '(') {
                        if ($tokens[$i-1]['punctuation'] === $delim && $buffer) {
                            $forms[] = $buffer . $name;
                        } else {
                            $forms[] = $name;
                        }
                        $buffer = null;
                    }
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
            $tokens = $this->getTokens();
            $attributes = [];
            $buffer = null;
            foreach ($tokens as $i => $token) {
                $name = $token['name'] ?? null;
                if (null === $name) {
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
                        $attributes[] = $buffer . $name;
                        $buffer = null;
                        break;
                    default:
                        $buffer = null;
                }
            }
            $attributes = array_unique($attributes);
            $this->attributes = $attributes;
        }
        return $this->attributes;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getArguments()
     */
    public function getArguments() : array
    {
        if ($this->arguments === null) {
            $args = [];
            foreach ($this->getTokens() as $token) {
                if ($token['string'] !== null) {
                    $args[] = $token['string'];
                    continue;
                }
                if ($token['number'] !== null) {
                    $args[] = $token['number'];
                    continue;
                }
                if (strcasecmp($token['name'] ?? '', 'false') === 0 || strcasecmp($token['name'] ?? '', 'true') === 0) {
                    $args[] = $token['name'];
                    continue;
                }
            }
            $this->arguments = $args;
        }
        return $this->arguments;
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