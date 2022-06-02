<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Model\ConditionIncompleteError;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\UxonObject;

abstract class ConditionFactory extends AbstractStaticFactory
{

    /**
     * Returns an empty condition
     * 
     * @param Workbench $exface
     * @param bool $ignoreEmptyValues
     * 
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createEmpty(Workbench $exface, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        return new Condition($exface, null, null, null, $ignoreEmptyValues);
    }
    
    /**
     * Creates a condition for the given object from an expression string (e.g. attribute alias)
     * 
     * @param MetaObjectInterface $object
     * @param string $expression_string
     * @param string $value
     * @param string $comparator
     * @param bool $ignoreEmptyValues
     * 
     * @return Condition
     */
    public static function createFromExpressionString(MetaObjectInterface $object, string $expression_string, $value, string $comparator = null, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        $workbench = $object->getWorkbench();
        $expression = ExpressionFactory::createForObject($object, $expression_string);
        return new Condition($workbench, $expression, $comparator, $value, $ignoreEmptyValues);
    }

    /**
     * Returns a condition object, that can be used in filters, conditional operators, etc.
     * Conditions consist of an expression, a value to
     * compare the expression to and a comparator like "=", ">", "<", etc. Comparators are defined by the EXF_COMPARATOR_xxx constants.
     *
     * @param Workbench $exface
     * @param string|\exface\Core\Interfaces\Model\ExpressionInterface $expression_or_string            
     * @param string $value            
     * @param string $comparator  
     * @param bool $ignoreEmptyValues          
     * @return Condition
     */
    public static function createFromExpression(Workbench $exface, ExpressionInterface $expression = NULL, $value = NULL, string $comparator = null, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        return new Condition($exface, $expression, $comparator, $value, $ignoreEmptyValues);
    }
    
    /**
     * Parses a string like "MY_ATTRIBUTE > 0" into a condition.
     * 
     * The comparator must be separated from the left and the right expressions by spaces. Both 
     * expression may include spaces, but must not include comparator charaters (<, >, =, etc.)
     * 
     * @param Workbench $workbench
     * @param string $string
     * @param MetaObjectInterface|NULL $object
     * @param bool $ignoreEmptyValues
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createFromString(Workbench $workbench, string $string, MetaObjectInterface $object = null, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        $tokens = explode(' ', $string);
        $left = '';
        $right = '';
        foreach ($tokens as $token) {
            if (in_array($token, ComparatorDataType::getValuesStatic())) {
                if ($left === '') {
                    throw new ConditionIncompleteError('Cannot parse "' . $string . '" as condition: there is no left side!');
                }
                
                $comp = $token;
                $right = substr($string, (strlen($left) + strlen($comp) + 2));
                break;
            } else {
                $left .= ($left ? ' ' : '') . $token;
            }
        }
        if (! is_null($object)) {
            $condition = static::createFromExpressionString($object, $left, $right, $comp, $ignoreEmptyValues);
        } else {
            $condition = new Condition($workbench, ExpressionFactory::createFromString($workbench, $left), $comp, $right, $ignoreEmptyValues);
        }
        return $condition;
    }
    
    /**
     * Parses a string like "> 0" into a condtion relative to the given left expression
     * 
     * @param ExpressionInterface $expression
     * @param string $string
     * @param bool $ignoreEmptyValues
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createFromStringRelativeToExpression(ExpressionInterface $expression, string $string, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        $string = trim($string);
        $tokens = explode(' ', $string);
        $comp = $tokens[0];
        $value = substr($string, (strlen($comp)+1));
        return static::createFromExpression($expression->getWorkbench(), $expression, $value, $comp, $ignoreEmptyValues);
    }
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @param mixed $value
     * @param string $comparator
     * @param bool $ignoreEmptyValues
     * @return ConditionInterface
     */
    public static function createFromAttribute(MetaAttributeInterface $attribute, $value, string $comparator = null, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        $expr = ExpressionFactory::createFromAttribute($attribute);
        return static::createFromExpression($attribute->getWorkbench(), $expr, $value, $comparator, $ignoreEmptyValues);
    }
    
    /**
     * 
     * @param Workbench $exface
     * @param UxonObject $uxon
     * @param bool $ignoreEmptyValues
     * @return ConditionInterface
     */
    public static function createFromUxon(Workbench $exface, UxonObject $uxon, bool $ignoreEmptyValues = false) : ConditionInterface
    {
        $result = static::createEmpty($exface, $ignoreEmptyValues);
        $result->importUxonObject($uxon);
        return $result;
    }
}