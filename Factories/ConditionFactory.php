<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Exceptions\FactoryError;

abstract class ConditionFactory extends AbstractUxonFactory {
	
	public static function create_empty(Workbench &$exface){
		return new Condition($exface);
	}
	
	/**
	 * Returns a condition object, that can be used in filters, conditional operators, etc. Conditions consist of an expression, a value to 
	 * compare the expression to and a comparator like "=", ">", "<", etc. Comparators are defined by the EXF_COMPARATOR_xxx constants.
	 * @param exface
	 * @param string|\exface\Core\CommonLogic\Model\Expression $expression_or_string
	 * @param string $value
	 * @param string $comparator 
	 * @return Condition
	 */
	public static function create_from_expression(Workbench &$exface, Expression $expression=NULL, $value=NULL, $comparator=EXF_COMPARATOR_IS){
		$condition = static::create_empty($exface);
		if ($expression){
			$condition->set_expression($expression);
		}
		$condition->set_value($value);
		$condition->set_comparator($comparator);
		return $condition;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param array $array_notation
	 * @return Condition
	 */
	public static function create_from_array(Workbench &$exface, array $array_notation){
		$condition = self::create($exface);
		$condition->set_expression($exface->model()->parse_expression($array_notation[1], $exface->model()->get_object($array_notation[0])));
		$condition->set_comparator($array_notation[2]);
		$condition->set_value($array_notation[3]);
		return $condition;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param string|array $uxon_or_array
	 * @throws FactoryError
	 * @return Condition
	 */
	public static function create_from_object_or_array(Workbench $exface, $uxon_or_array){
		if ($uxon_or_array instanceof \stdClass){
			return self::create_from_stdClass($exface, $uxon_or_array);
		} elseif (is_array($uxon_or_array)){
			return self::create_from_array($exface, $uxon_or_array);
		} else {
			throw new FactoryError('Cannot parse condition "' . print_r($uxon_or_array) . '"!');
		}
	}
}
?>