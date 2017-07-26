<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\Model\Object;

abstract class ConditionFactory extends AbstractUxonFactory
{

    /**
     * Returns an empty condition
     * 
     * @param Workbench $exface
     * @return \exface\Core\CommonLogic\Model\Condition
     */
    public static function createEmpty(Workbench $exface)
    {
        return new Condition($exface);
    }
    
    /**
     * Creates a condition for the given object from an expression string (e.g. attribute alias)
     * 
     * @param Object $object
     * @param string $expression_string
     * @param string $value
     * @param string $comparator
     * 
     * @return Condition
     */
    public static function createFromString(Object $object, $expression_string, $value, $comparator = null)
    {
        $workbench = $object->getWorkbench();
        $condition = static::createEmpty($workbench);
        $condition->setExpression($workbench->model()->parseExpression($expression_string, $object));
        $condition->setValue($value);
        if ($comparator){
            $condition->setComparator($comparator);
        }
        return $condition;
    }

    /**
     * Returns a condition object, that can be used in filters, conditional operators, etc.
     * Conditions consist of an expression, a value to
     * compare the expression to and a comparator like "=", ">", "<", etc. Comparators are defined by the EXF_COMPARATOR_xxx constants.
     *
     * @param Workbench $exface
     * @param string|\exface\Core\CommonLogic\Model\Expression $expression_or_string            
     * @param string $value            
     * @param string $comparator            
     * @return Condition
     */
    public static function createFromExpression(Workbench $exface, Expression $expression = NULL, $value = NULL, $comparator = null)
    {
        $condition = static::createEmpty($exface);
        if ($expression) {
            $condition->setExpression($expression);
        }
        $condition->setValue($value);
        if ($comparator){
            $condition->setComparator($comparator);
        }
        return $condition;
    }

    /**
     *
     * @param Workbench $exface            
     * @param array $array_notation            
     * @return Condition
     */
    public static function createFromArray(Workbench $exface, array $array_notation)
    {
        $condition = self::create($exface);
        $condition->setExpression($exface->model()->parseExpression($array_notation[1], $exface->model()->getObject($array_notation[0])));
        $condition->setComparator($array_notation[2]);
        $condition->setValue($array_notation[3]);
        return $condition;
    }

    /**
     *
     * @param Workbench $exface            
     * @param string|array $uxon_or_array            
     * @throws UnexpectedValueException
     * @return Condition
     */
    public static function createFromObjectOrArray(Workbench $exface, $uxon_or_array)
    {
        if ($uxon_or_array instanceof \stdClass) {
            return self::createFromStdClass($exface, $uxon_or_array);
        } elseif (is_array($uxon_or_array)) {
            return self::createFromArray($exface, $uxon_or_array);
        } else {
            throw new UnexpectedValueException('Cannot parse condition "' . print_r($uxon_or_array) . '"!');
        }
    }
}
?>