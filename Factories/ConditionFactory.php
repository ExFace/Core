<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Model\ConditionIncompleteError;
use exface\Core\Interfaces\Model\ConditionInterface;

abstract class ConditionFactory extends AbstractUxonFactory
{

    /**
     * Returns an empty condition
     * 
     * @param Workbench $exface
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createEmpty(Workbench $exface) : ConditionInterface
    {
        return new Condition($exface);
    }
    
    /**
     * Creates a condition for the given object from an expression string (e.g. attribute alias)
     * 
     * @param MetaObjectInterface $object
     * @param string $expression_string
     * @param string $value
     * @param string $comparator
     * 
     * @return Condition
     */
    public static function createFromExpressionString(MetaObjectInterface $object, string $expression_string, $value, string $comparator = null) : ConditionInterface
    {
        $workbench = $object->getWorkbench();
        $expression = $workbench->model()->parseExpression($expression_string, $object);
        return new Condition($workbench, $expression, $comparator, $value);
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
     * @return Condition
     */
    public static function createFromExpression(Workbench $exface, ExpressionInterface $expression = NULL, $value = NULL, string $comparator = null) : ConditionInterface
    {
        return new Condition($exface, $expression, $comparator, $value);
    }

    /**
     *
     * @param Workbench $exface            
     * @param array $array_notation            
     * @return Condition
     */
    public static function createFromArray(Workbench $exface, array $array_notation) : ConditionInterface
    {
        $expression = $exface->model()->parseExpression($array_notation[1], $exface->model()->getObject($array_notation[0]));
        $comparator = $array_notation[2];
        $value = $array_notation[3];
        return new Condition($exface, $expression, $comparator, $value);
    }

    /**
     *
     * @param Workbench $exface            
     * @param UxonObject|array $uxon_or_array            
     * @throws UnexpectedValueException
     * @return Condition
     */
    public static function createFromUxonOrArray(Workbench $exface, $uxon_or_array) : ConditionInterface
    {
        if ($uxon_or_array instanceof UxonObject) {
            return self::createFromUxon($exface, $uxon_or_array);
        } elseif (is_array($uxon_or_array)) {
            return self::createFromArray($exface, $uxon_or_array);
        } else {
            throw new UnexpectedValueException('Cannot parse condition "' . print_r($uxon_or_array) . '"!');
        }
    }
    
    /**
     * Parses a string like "MY_ATTRIBUTE > 0" into a condition.
     * 
     * The comparator must be separated from the left and the right expressions by spaces. Both 
     * expression may include spaces, but must not include comparator charaters (<, >, =, etc.)
     * 
     * @param Workbench $workbench
     * @param string $string
     * @param MetaObjectInterface $object
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createFromString(Workbench $workbench, string $string, MetaObjectInterface $object = null) : ConditionInterface
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
            $condition = static::createFromExpressionString($object, $left, $right, $comp);
        } else {
            $condition = new Condition($workbench, ExpressionFactory::createFromString($workbench, $left), $comp, $right);
        }
        return $condition;
    }
    
    /**
     * Parses a string like "> 0" into a condtion relative to the given left expression
     * 
     * @param ExpressionInterface $expression
     * @param string $string
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createFromStringRelativeToExpression(ExpressionInterface $expression, $string) : ConditionInterface
    {
        $string = trim($string);
        $tokens = explode(' ', $string);
        $comp = $tokens[0];
        $value = substr($string, (strlen($comp)+1));
        return static::createFromExpression($expression->getWorkbench(), $expression, $value, $comp);
    }
}
?>