<?php

namespace exface\Core\CommonLogic\Model;

/**
 * A single, immutable argument of a Symfony expression.
 * 
 * Every argument has a value and type. It may contain any number of formulas, referenced
 * by their index. It also knows its parent, which is either a formula index or NULL if it is
 * a top-level argument. 
 * 
 * Formula indices are only valid for the source-stream of this argument.
 */
class SymfonyArgument
{
    const TYP_ATTRIBUTE = 'attribute';
    const TYP_FORMULA = 'formula';
    const TYP_LITERAL = 'literal';
    const TYP_EXPRESSION = 'expression';
    const TYP_EMPTY = 'empty';
    
    protected SymfonyTokenStream $sourceStream;
    protected mixed $value;
    protected string $type;
    protected array $formulaIndices;
    protected ?int $parentIndex;

    function __construct(SymfonyTokenStream $sourceStream, mixed $value, string $type, array $formulaIndices, ?int $parentIndex)
    {
        $this->sourceStream = $sourceStream;
        $this->value = $value;
        $this->type = $type;
        $this->formulaIndices = $formulaIndices;
        $this->parentIndex = $parentIndex;
    }

    /**
     * Returns the token stream that lexed this argument.
     * 
     * @return SymfonyTokenStream
     */
    public function getSourceStream(): SymfonyTokenStream
    {
        return $this->sourceStream;
    }

    /**
     * Returns the value of this argument.
     * 
     * Consult `getType()` to infer the type of the value.
     * 
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Returns the type of this argument as a string. 
     * 
     * Types are:
     * - `literal`: The value is to be used as is, without interpretation (i.e. a regular string or number).
     * - `attribute`: The value is an attribute alias.
     * - `formula`: The value is a formula alias.
     * - `expression`: The value is an expression (like `(1 + Abs(Attribute))`).
     * - `empty`: A placeholder for an omitted argument (like the middle one in `Foo(a,,b)`). The value is NULL.
     * 
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the indices of formulas contained in this argument (i.e. nested formulas).
     * 
     * These indices are only valid in the context of the source stream.
     * 
     * @return array
     */
    public function getFormulaIndices(): array
    {
        return $this->formulaIndices;
    }

    /**
     * Returns the index of the parent context (usually a formula) that this argument belongs to.
     *
     * This index is only valid in the context of the source stream.
     *
     * @return int|null
     */
    public function getParentIndex(): ?int
    {
        return $this->parentIndex;
    }

    /**
     * Returns true if this argument is an attribute alias.
     * 
     * @return bool
     */
    public function isAttribute() : bool
    {
        return $this->getType() === self::TYP_ATTRIBUTE;
    }

    /**
     * Returns true if this argument is a formula alias.
     *
     * @return bool
     */
    public function isFormula() : bool
    {
        return $this->getType() === self::TYP_FORMULA;
    }

    /**
     * Returns true if this argument is a literal value.
     *
     * @return bool
     */
    public function isLiteral() : bool
    {
        return $this->getType() === self::TYP_LITERAL;
    }

    /**
     * Returns true if this argument is an expression.
     *
     * @return bool
     */
    public function isExpression() : bool
    {
        return $this->getType() === self::TYP_EXPRESSION;
    }

    /**
     * Returns true if this argument is a placeholder for an omitted argument.
     * 
     * This is not the same as a NULL literal, which is a real value.
     *
     * @return bool
     */
    public function isEmpty() : bool
    {
        return $this->getType() === self::TYP_EMPTY;
    }
}