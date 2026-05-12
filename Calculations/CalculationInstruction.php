<?php

namespace exface\Core\Calculations;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Contains an expression to be evaluated (the calculation) and an attribute alias, where the result should be written to.
 */
class CalculationInstruction
{
    use ImportUxonObjectTrait;
    
    private MetaObjectInterface $object;
    private ?string $outputAttributeAlias = null;
    private ?ExpressionInterface $expression = null;

    public function __construct(MetaObjectInterface $object)
    {
        $this->object = $object;
    }

    public static function fromUxon(MetaObjectInterface $object, UxonObject $uxonObject) : CalculationInstruction
    {
        $result = new CalculationInstruction($object);
        $result->importUxonObject($uxonObject);
        return $result;
    }
    
    public function getOutputAttributeAlias(): ?string
    {
        return $this->outputAttributeAlias;
    }

    /**
     * Decide where to write the output of the `expression`, once evaluated.
     * 
     * @uxon-property output_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $attributeAlias
     * @return $this
     */
    public function setOutputAttributeAlias(string $attributeAlias): CalculationInstruction
    {
        $this->outputAttributeAlias = $attributeAlias;
        return $this;
    }

    /**
     * @param string $attributeAlias
     * @return CalculationInstruction
     * @deprecated Use `setOutputAttributeAlias()`.
     */
    public function setAttributeAlias(string $attributeAlias): CalculationInstruction
    {
        return $this->setOutputAttributeAlias($attributeAlias);
    }


    /**
     * @see ConditionInterface::getExpression()
     */
    public function getExpression() : ExpressionInterface
    {
        return $this->expression;
    }

    /**
     * Define the actual calculation to be performed by this instruction.
     * 
     * @uxon-property expression
     * @uxon-type metamodel:expression
     *
     * @param string $expression
     * @return CalculationInstruction
     */
    public function setExpression(string $expression) : CalculationInstruction
    {
        $this->expression = ExpressionFactory::createForObject($this->object, $expression, true);
        return $this;
    }

    /**
     * @param string $calculation
     * @return CalculationInstruction
     * @deprecated Use `setExpression()`.
     */
    public function setCalculation(string $calculation) : CalculationInstruction
    {
        return $this->setExpression($calculation);
    }
}