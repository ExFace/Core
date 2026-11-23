<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Exceptions\FormulaNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Formulas\Calc;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

/**
 * Wrapper class to extract formula name, nested formulas and attributes
 * from a given expression using the Symfony/ExpressionLanguage/Lexer class
 * 
 * @author ralf.mulansky
 *
 */
class SymfonyTokenStream extends SymfonyLexer implements FormulaTokenStreamInterface
{
    private $formName = null;
    
    private $nestedForms = null;
    
    private $attributes = null;
    
    private ?int $rootIndex = -1;
    
    /**
     * Formulas in order of appearance. Formulas are identified by position, not by name,
     * because the same formula name may appear several times. Each entry holds the formula name and
     * the index of the enclosing formula (NULL at top level).
     *
     * @var array<int, array{name: string, parent: ?int}>
     */
    protected ?array $mappedFormulas = null;

    /**
     * Arguments in order of appearance. Each entry holds the argument value and the index of the
     * enclosing formula in `$mappedFunctions` (NULL when not inside any formula).
     *
     * @var array<int, array{value: mixed, formula: ?int}>
     */
    protected ?array $mappedArguments = null;

    public function __construct(string $expression)
    {
        try {
            parent::__construct($expression);
        } catch (\Throwable $e) {
            throw new FormulaNotFoundError('Cannot parse formula: ' . $e->getMessage(), '7R34E52', $e);
        }
    }

    /**
     * 
     * @return array
     */
    protected function getFormulas() : array
    {
        if ($this->mappedFormulas === null) {
            $this->mapTokens();
        }
        
        return $this->mappedFormulas;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getFormulaName()
     */
    public function getFormulaName() : ?string
    {
        if ($this->formName === null) {
            $name = $this->getFormulas()[0] ?? null;

            // If the expression does not contain a formula name, e.g. '(1 + 1)', the formula is the Calc class formula.
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
            $formulas = $this->getFormulas();
            
            // Remove outer formula (i.e. the root).
            $nestedFormulas = array_reverse($formulas, true);
            array_pop($nestedFormulas);
            
            $this->nestedForms = $formulas;
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
            $this->mapTokens();
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
        $arg = $this->getArguments()[$index] ?? null;
        
        return $arg === null ? null : (string) $arg['value'];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::isArgumentAttribute()
     */
    public function isArgumentAttribute(int $index) : bool
    {
        return ($this->getArguments()[$index]['type'] ?? null) === 'attribute';
    }

    /**
     * Returns the arguments of the formula identified by `$index`, in the order they were written.
     * 
     * @return array[]
     */
    public function getArguments(int $index = -1) : array
    {
        // Default is the root index, otherwise use the index that was specified.
        $parentIndex = $index > -1 ? $index : $this->getRootIndex();

        // Arguments of one formula are already in source order, so re-indexing keeps their positions.
        $all = $this->getArgumentsAll();
        return array_values(array_filter($all, fn(array $arg) => $arg['parentIndex'] === $parentIndex));
    }
    
    protected function getRootIndex() : ?int
    {
        if($this->rootIndex !== -1) {
            return $this->rootIndex;
        }

        $this->rootIndex = null;

        $all = $this->getArguments();
        $topLevel = array_filter($all, fn(array $arg) => $arg['parentIndex'] === null);
        if (count($topLevel) === 1 && reset($topLevel)['type'] === 'formula') {
            $this->rootIndex = reset($topLevel)['formulaIndex'];
        }
        
        return $this->rootIndex;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getArguments()
     */
    public function getArgumentsAll() : array
    {
        if ($this->mappedArguments === null) {
            $this->mapTokens();
        }
        
        return $this->mappedArguments;
    }

    /**
     * Maps tokens to formulas and their arguments.
     * 
     * Brackets are tracked by depth. A bracket that opens a formula (a name directly before `(`) records the
     * index of that formula. A grouping bracket records NULL and leaves the enclosing formula unchanged.
     * Each argument is attached to the nearest formula that is still open.
     * 
     * @return void
     */
    protected function mapTokens() : void
    {
        $this->mappedFormulas = [];
        $this->mappedArguments = [];
        $this->attributes = [];
        
        $tokens = $this->getTokens();
        $buffer = null;
        $bracketDepth = 0;
        // Maps [ bracketDepth => formulaIndex ].
        $openBrackets = [];

        // These punctuations will be buffered. They denote namespaces, aggregators or modifiers of an alias.
        $needsBuffer = [
            AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER,
            DataAggregation::AGGREGATION_SEPARATOR,
            '[', ']'
        ];
        $hasTrailingPunctuation = false;
        // Tokens of the argument currently being read, per formula index (-1 = top level).
        $slots = [];

        foreach ($tokens as $token) {
            // Extract string or number arguments.
            $arg = match (true) {
                isset($token['string']) => $token['string'],
                isset($token['number']) => $token['number'],
                default => null,
            };

            // If an argument is found, add it to the arguments list and continue to the next token.
            if ($arg !== null) {
                $this->checkTrailingPunctuation($hasTrailingPunctuation, $buffer, $arg);
                $this->addPart($arg, 'literal', $openBrackets, $slots);
                $buffer = null;
                continue;
            }

            // Operators join the surrounding tokens into one expression argument, e.g. "Amount:SUM * 0.19".
            if (isset($token['operator'])) {
                $this->addPart($buffer, 'attribute', $openBrackets, $slots);
                $buffer = null;
                $this->addPart($token['operator'], 'operator', $openBrackets, $slots);
                continue;
            }

            // Next, we see if the token is a punctuation and handle it accordingly.
            $punctuation = $token['punctuation'] ?? null;
            if ($punctuation !== null) {
                // We do not expect double buffered punctuations.
                $this->checkTrailingPunctuation($hasTrailingPunctuation, $buffer, $punctuation);

                switch ($punctuation) {
                    case '(':
                        // Increment bracket depth.
                        $bracketDepth++;
                        if ($buffer !== null) {
                            // A buffered name before '(' is a formula. We map it to its enclosing context.
                            $this->mappedFormulas[] = $buffer;
                            // The nested formula occupies one slot in the argument list of its parent.
                            $this->addPart($buffer, 'formula', $openBrackets, $slots, array_key_last($this->mappedFormulas));
                            // Mark the bracket as open and associate it with the index of the enclosing formula.
                            $openBrackets[$bracketDepth] = array_key_last($this->mappedFormulas);
                        } else {
                            // If the buffer is NULL, we mark it as an open grouping bracket.
                            // Anything inside remains part of the current formula context.
                            $openBrackets[$bracketDepth] = null;
                        }
                        $buffer = null;
                        break;
                    case ')':
                        // Flush buffer as attribute.
                        $this->addPart($buffer, 'attribute', $openBrackets, $slots);
                        $buffer = null;
                        // Closing brackets may be unbalanced, we guard instead of throwing,
                        // because the parser will catch this later (we are just lexing here).
                        if ($bracketDepth > 0) {
                            // Conclude the last argument of the formula, if this bracket belongs to one.
                            if ($openBrackets[$bracketDepth] !== null) {
                                $this->closeArgument($openBrackets[$bracketDepth], $slots);
                            }
                            // Conclude formula.
                            unset($openBrackets[$bracketDepth]);
                            // Decrement bracket depth.
                            $bracketDepth--;
                        }
                        break;
                    case in_array($punctuation, $needsBuffer):
                        // Append punctuation and expect follow-up.
                        $hasTrailingPunctuation = true;
                        $buffer .= $punctuation;
                        break;
                    case ',':
                        // A comma concludes the current argument of the enclosing formula.
                        $this->addPart($buffer, 'attribute', $openBrackets, $slots);
                        $buffer = null;
                        $this->closeArgument($this->getEnclosingFormulaIndex($openBrackets), $slots);
                        break;
                    default:
                        // Treat any other punctuation as terminal and flush buffer as attribute.
                        $this->addPart($buffer, 'attribute', $openBrackets, $slots);
                        $buffer = null;
                }
                continue;
            }

            // Now we handle tokens that have a 'name' key. This is where we determine if the token is an argument, attribute or a formula.
            $name = $token['name'] ?? null;
            // Ignore empty names.
            if ($name === null) {
                continue;
            }

            // TRUE, FALSE or NULL are treated as arguments.
            //TODO maybe implement a check for attribute alias that they do not conflict with reserved keywords like TRUE, FALSE, NULL
            if (mb_strtoupper($name) === 'TRUE' || mb_strtoupper($name) === 'FALSE' || mb_strtoupper($name) === 'NULL') {
                $this->checkTrailingPunctuation($hasTrailingPunctuation, $buffer, $name);
                $this->addPart($name, 'literal', $openBrackets, $slots);
                $buffer = null;
                continue;
            }

            // Now we know that we are handling some kind of alias or part of an alias.
            // If the buffer is empty, just buffer it. The buffer will be flushed later.
            if ($buffer === null) {
                $buffer = $name;
                continue;
            }

            // If the buffer is not empty, but we have a trailing punctuation, the token is part
            // of an alias (i.e. it's a namespace, aggregator or modifier).
            if ($hasTrailingPunctuation) {
                $buffer .= $name;
                $hasTrailingPunctuation = false;
                continue;
            }

            // If we have no trailing punctuation, this token concludes the previous argument, and we must flush the buffer.
            $this->addPart($buffer, 'attribute', $openBrackets, $slots);

            // Finally, start a fresh buffer with this token.
            $buffer = $name;
        }
        
        // Flush the last buffered argument, e.g. for bare expressions like "Status".
        $this->addPart($buffer, 'attribute', $openBrackets, $slots);
        // Conclude the top-level argument.
        $this->closeArgument(null, $slots);
    }

    /**
     * Throws an error if `$hasTrailingPunctuation` is TRUE.
     * 
     * @param bool        $hasTrailingPunctuation
     * @param string|null $buffer
     * @param string      $token
     * @return void
     */
    protected function checkTrailingPunctuation(bool $hasTrailingPunctuation, ?string $buffer, string $token) : void
    {
        if($hasTrailingPunctuation) {
            $msg = $buffer !== null ?
                $buffer . $token :
                '{MISSING ARG} ' . $token;

            throw new InvalidArgumentException('Unexpected punctuation encountered for argument "' . $msg . '".');
        }
    }

    /**
     * Returns the index of the nearest formula context that is still open, or NULL at top level.
     *
     * @param array<int, ?int> $openBrackets
     * @return int|null
     */
    protected function getEnclosingFormulaIndex(array $openBrackets) : ?int
    {
        foreach (array_reverse($openBrackets) as $formulaIndex) {
            if ($formulaIndex !== null) {
                return $formulaIndex;
            }
        }
        
        return null;
    }

    /**
     * Adds a token to the argument currently being read for the enclosing formula.
     * 
     * Tokens are only collected here. They become an entry of `$mappedArguments` once the argument is
     * concluded by a comma or the closing bracket (see `closeArgument()`).
     *
     * @param mixed            $value
     * @param string           $type 'attribute', 'literal', 'operator' or 'formula'
     * @param array<int, ?int> $openBrackets
     * @param array            $slots
     * @param int|null         $nestedFormulaIndex
     * @return void
     */
    protected function addPart(mixed $value, string $type, array $openBrackets, array &$slots, ?int $nestedFormulaIndex = null) : void
    {
        if ($value === null) {
            return;
        }
        
        if ($type === 'attribute') {
            $this->attributes[] = $value;
        }
        
        $key = $this->getEnclosingFormulaIndex($openBrackets) ?? -1;
        $slots[$key][] = ['value' => $value, 'type' => $type, 'nested' => $nestedFormulaIndex];
    }

    /**
     * Concludes the argument that is currently collected for a formula and appends it to `$mappedArguments`.
     * 
     * A single token is stored as it is. Several tokens form an expression, which is stored as one argument,
     * so the position of an argument always equals its position between the commas.
     *
     * @param int|null $formulaIndex
     * @param array    $slots
     * @return void
     */
    protected function closeArgument(?int $formulaIndex, array &$slots) : void
    {
        // Top-level tokens (no enclosing formula) are collected under the key -1.
        $key = $formulaIndex ?? -1;
        // Take the collected tokens and reset the slot, so the next argument starts empty.
        $parts = $slots[$key] ?? [];
        unset($slots[$key]);
        
        // Nothing collected, e.g. for an empty argument list "Foo()".
        if (empty($parts)) {
            return;
        }
        
        if (count($parts) === 1) {
            // A single token is stored as it is: an attribute, a literal or a nested formula.
            $arg = [
                'value' => $parts[0]['value'],
                'type' => $parts[0]['type'],
                'formulaIndex' => $parts[0]['nested']
            ];
        } else {
            // Several tokens form an expression, which we store as one argument with its source text.
            $texts = [];
            foreach ($parts as $part) {
                $isString = $part['type'] === 'literal' && is_string($part['value']);
                // TRUE, FALSE and NULL are keywords and must not be quoted like strings.
                $isKeyword = $isString && preg_match('/^(TRUE|FALSE|NULL)$/i', $part['value']);
                
                if ($isString && ! $isKeyword) {
                    $texts[] = "'" . addcslashes($part['value'], "'\\") . "'";
                } else {
                    $texts[] = (string) $part['value'];
                }
            }
            $arg = [
                'value' => implode(' ', $texts),
                'type' => 'expression',
                'formulaIndex' => null
            ];
        }
        
        // Attach the argument to the formula it belongs to (NULL at top level).
        $arg['parentIndex'] = $formulaIndex;
        $this->mappedArguments[] = $arg;
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