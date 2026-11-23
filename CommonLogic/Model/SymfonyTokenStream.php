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
     * Formula names in order of appearance. Formulas are identified by their position (the array key),
     * not by name, because the same formula name may appear several times.
     *
     * @var string[]|null
     */
    protected ?array $mappedFormulas = null;

    /**
     * Arguments in order of completion, i.e. nested formulas come before the argument that contains them.
     * Use `getArguments()` to get the arguments of a single formula in source order.
     *
     * @var SymfonyArgument[]|null
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
            $name = $this->mappedFormulas[$this->getRootIndex()] ?? null;

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
            unset($formulas[$this->getRootIndex()]);
            
            $this->nestedForms = $formulas ?? [];
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
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::getArgument()
     */
    public function getArgument(int $index) : ?SymfonyArgument
    {
        $args = $this->getArguments($this->getRootIndex());
        return $args[$index] ?? null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface::isArgumentAttribute()
     */
    public function isArgumentAttribute(int $index) : bool
    {
        return $this->getArguments()[$index]?->isAttribute() ?? false;
    }

    /**
     * Returns the arguments of the formula identified by `$index`, in the order they were written.
     * 
     * @return SymfonyArgument[]
     */
    public function getArguments(int $index = -1) : array
    {
        // Default is the root index, otherwise use the index that was specified.
        $parentIndex = $index > -1 ? $index : $this->getRootIndex();

        $all = $this->getArgumentsAll();
        $forIndex = array_filter($all, fn(SymfonyArgument $arg) => $arg->getParentIndex() === $parentIndex);
        // Arguments of one formula are already in source order, so re-indexing keeps their positions.
        return array_values($forIndex);
    }
    
    /**
     * Returns the index of the root formula, i.e. the single formula that wraps the whole expression.
     * 
     * Returns NULL if the expression is not wrapped in a formula, e.g. `(1 + Amount)`. The result is cached.
     * 
     * @return int|null
     */
    protected function getRootIndex() : ?int
    {
        if($this->rootIndex !== -1) {
            return $this->rootIndex;
        }

        $this->rootIndex = null;

        $all = $this->getArguments();
        $topLevel = array_filter($all, fn(SymfonyArgument $arg) => $arg->getParentIndex() === null);
        if (count($topLevel) === 1 && reset($topLevel)->getType() === 'formula') {
            $this->rootIndex = reset($topLevel)->getFormulaIndices()[0] ?? null;
        }
        
        return $this->rootIndex;
    }

    /**
     * @inheritDoc
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
            '['
        ];
        $hasTrailingPunctuation = false;
        // Tokens of the argument currently being read, per formula index (-1 = top level).
        $slots = [];

        foreach ($tokens as $token) {
            // The first token after a delimiter marks where the source text of an argument starts. Grouping
            // brackets count too, so that the source text stays balanced. Delimiters never start an argument.
            $punctuation = $token['punctuation'] ?? null;
            $isDelimiter = $punctuation === ',' || ($punctuation === ')' && ($openBrackets[$bracketDepth] ?? null) !== null);
            if (! $isDelimiter) {
                $this->markArgumentStart($token['cursor'], $openBrackets, $slots);
            }

            // Extract string or number arguments.
            $arg = match (true) {
                isset($token['string']) => $token['string'],
                isset($token['number']) => $token['number'],
                default => null,
            };

            // If an argument is found, add it to the arguments list and continue to the next token.
            if ($arg !== null) {
                $this->checkTrailingPunctuation($hasTrailingPunctuation, $buffer, $arg);
                $this->addPart($arg, SymfonyArgument::TYP_LITERAL, $openBrackets, $slots);
                $buffer = null;
                continue;
            }

            // Operators join the surrounding tokens into one expression argument, e.g. "Amount:SUM * 0.19".
            if (isset($token['operator'])) {
                $this->addPart($buffer, SymfonyArgument::TYP_ATTRIBUTE, $openBrackets, $slots);
                $buffer = null;
                $this->addPart($token['operator'], 'operator', $openBrackets, $slots);
                continue;
            }

            // Next, we see if the token is a punctuation and handle it accordingly.
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
                            $this->addPart($buffer, SymfonyArgument::TYP_FORMULA, $openBrackets, $slots, array_key_last($this->mappedFormulas));
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
                        $this->addPart($buffer, SymfonyArgument::TYP_ATTRIBUTE, $openBrackets, $slots);
                        $buffer = null;
                        // Closing brackets may be unbalanced, we guard instead of throwing,
                        // because the parser will catch this later (we are just lexing here).
                        if ($bracketDepth > 0) {
                            // Conclude the last argument of the formula, if this bracket belongs to one.
                            if ($openBrackets[$bracketDepth] !== null) {
                                $this->closeArgument($openBrackets[$bracketDepth], $slots, $token['cursor']);
                            }
                            // Conclude formula.
                            unset($openBrackets[$bracketDepth]);
                            // Decrement bracket depth.
                            $bracketDepth--;
                        }
                        break;
                    case ']':
                        // A closing modifier bracket ends the modifier, so no follow-up is expected.
                        $buffer .= $punctuation;
                        break;
                    case in_array($punctuation, $needsBuffer):
                        // Append punctuation and expect follow-up.
                        $hasTrailingPunctuation = true;
                        $buffer .= $punctuation;
                        break;
                    case ',':
                        // A comma concludes the current argument of the enclosing formula.
                        $this->addPart($buffer, SymfonyArgument::TYP_ATTRIBUTE, $openBrackets, $slots);
                        $buffer = null;
                        $this->closeArgument($this->getEnclosingFormulaIndex($openBrackets), $slots, $token['cursor'], true);
                        break;
                    default:
                        // Treat any other punctuation as terminal and flush buffer as attribute.
                        $this->addPart($buffer, SymfonyArgument::TYP_ATTRIBUTE, $openBrackets, $slots);
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
                $this->addPart($name, SymfonyArgument::TYP_LITERAL, $openBrackets, $slots);
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
            $this->addPart($buffer, SymfonyArgument::TYP_ATTRIBUTE, $openBrackets, $slots);

            // Finally, start a fresh buffer with this token.
            $buffer = $name;
        }
        
        // Flush the last buffered argument, e.g. for bare expressions like "Status".
        $this->addPart($buffer, SymfonyArgument::TYP_ATTRIBUTE, $openBrackets, $slots);
        // Conclude the top-level argument.
        $this->closeArgument(null, $slots, strlen($this->tokenStream->getExpression()));
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
        $slots[$key]['parts'][] = ['value' => $value, 'type' => $type, 'nested' => $nestedFormulaIndex];
    }

    /**
     * Remembers where the source text of the argument currently being read starts.
     * 
     * Only the first call after the previous argument was concluded has an effect.
     *
     * @param int              $cursor 0-based offset of the token in the expression
     * @param array<int, ?int> $openBrackets
     * @param array            $slots
     * @return void
     */
    protected function markArgumentStart(int $cursor, array $openBrackets, array &$slots) : void
    {
        $key = $this->getEnclosingFormulaIndex($openBrackets) ?? -1;
        $slots[$key]['start'] ??= $cursor;
    }

    /**
     * Concludes the argument that is currently collected for a formula and appends it to `$mappedArguments`.
     * 
     * A single token is stored as it is. Several tokens form an expression, which is stored as one argument
     * with its original source text, so the position of an argument always equals its position between the commas.
     *
     * @param int|null $parentIndex
     * @param array    $slots
     * @param int      $end 0-based offset in the expression where the argument ends (the delimiter)
     * @param bool     $keepEmpty If TRUE, an argument without any tokens is stored as an empty placeholder. This is used for
     *                            commas, so that the positions of the following arguments do not shift. A trailing
     *                            comma, e.g. `Foo(a,)`, does not create an argument.
     * @return void
     */
    protected function closeArgument(?int $parentIndex, array &$slots, int $end, bool $keepEmpty = false) : void
    {
        // Top-level tokens (no enclosing formula) are collected under the key -1.
        $key = $parentIndex ?? -1;
        // Take the collected tokens and reset the slot, so the next argument starts empty.
        $parts = $slots[$key]['parts'] ?? [];
        $start = $slots[$key]['start'] ?? 0;
        unset($slots[$key]);
        
        // Nothing collected, e.g. for an empty argument list "Foo()" or an empty slot "Foo(a,,b)".
        if (empty($parts)) {
            if ($keepEmpty) {
                $this->mappedArguments[] = new SymfonyArgument($this, null, SymfonyArgument::TYP_EMPTY, [], $parentIndex);
            }
            return;
        }
        
        // The formulas written directly inside this argument, in source order. Their own nested formulas
        // are reachable via their parentIndex. The property is empty for arguments that are attributes and literals.
        $formulaIndices = array_values(array_map(
            fn(array $part) => $part['nested'],
            array_filter($parts, fn(array $part) => $part['type'] === SymfonyArgument::TYP_FORMULA)
        ));
        
        if (count($parts) === 1) {
            // A single token is stored as it is: an attribute, a literal or a nested formula.
            $value = $parts[0]['value'];
            $type = $parts[0]['type'];
        } else {
            // Several tokens form an expression. We cut its source text out of the original expression, so
            // brackets, quoting and nested formulas stay exactly as written.
            $value = trim(substr($this->tokenStream->getExpression(), $start, $end - $start));
            $type = SymfonyArgument::TYP_EXPRESSION;
        }

        $this->mappedArguments[] = new SymfonyArgument(
            $this,
            $value,
            $type,
            $formulaIndices,
            // Attach the argument to the formula it belongs to (NULL at top level).
            $parentIndex
        );
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