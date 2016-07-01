<?php namespace exface\Core\Factories;

use exface\Core\exface;
use exface\Core\Model\Condition;
use exface\Core\Exceptions\FactoryError;
use exface\Core\Model\ConditionGroup;

abstract class ConditionGroupFactory extends AbstractUxonFactory {
	
	/**
	 * Returns an empty condition group
	 * @param exface $exface
	 * @param string $group_operator
	 * @return ConditionGroup
	 */
	public static function create_empty(exface &$exface, $group_operator = null){
		$group = new ConditionGroup($exface, $group_operator);
		return $group;
	}
	
	/**
	 * Creates a condition group from short notation arrays of the form 
	 * [ OPERATOR1, [ CONDITION1 ], [ CONDITION2 ], [ OPERATOR2, [ CONDITION3 ], [ CONDITION4] ], ... ]
	 * @param exface $exface
	 * @param array $array_notation
	 * @return ConditionGroup
	 */
	public static function create_from_array(exface &$exface, array $array_notation){
		$group = self::create($exface);
		// Short notation
		foreach ($array_notation as $nr => $part){
			if ($nr === 0){
				$group->set_operator($part);
			} elseif (is_array($part)){
				switch ($part[0]){
					case EXF_LOGICAL_AND:
					case EXF_LOGICAL_NOT:
					case EXF_LOGICAL_OR:
					case EXF_LOGICAL_XOR:
						$group->add_nested_group(self::create_from_object_or_array($exface, $part));
						break;
					default:
						$group->add_condition(ConditionFactory::create_from_object_or_array($exface, $part));
				}
			} else {
				throw new FactoryError('Cannot parse condition "' . print_r($part) . '" of condition group "' . print_r($array_notation) . '"!');
			}
		}
		return $group;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param string|array $uxon_or_array
	 * @throws FactoryError
	 * @return ConditionGroup
	 */
	public static function create_from_object_or_array(exface $exface, $uxon_or_array){
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