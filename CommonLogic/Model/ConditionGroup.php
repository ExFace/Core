<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Model\ExpressionRebaseImpossibleError;

/**
 * A condition group contains one or more conditions and/or other (nested) condition groups combined by one logical operator, 
 * e.g. OR( AND( cond1 = val1, cond2 < val2 ), cond3 = val3 ).
 * @author Andrej Kabachnik
 *
 */
class ConditionGroup implements iCanBeConvertedToUxon {
	private $exface = NULL;
	private $operator = NULL;
	private $conditions = array();
	private $nested_groups = array();
	
	function __construct(\exface\Core\CommonLogic\Workbench &$exface, $operator = EXF_LOGICAL_AND){
		$this->exface = $exface;
		$this->set_operator($operator);
	}
	
	/**
	 * Adds a condition to the group
	 * @param Condition $condition
	 * @return \exface\Core\CommonLogic\Model\ConditionGroup
	 */
	public function add_condition(Condition $condition){
		// TODO check, if the same condition already exists. There is no need to allow duplicate conditions in the same group!
		$this->conditions[] = $condition;
		return $this;
	}
	
	/**
	 * Creates a new condition and adds it to this group
	 * @param expression $expression
	 * @param string $value
	 * @param string $comparator
	 * @return \exface\Core\CommonLogic\Model\ConditionGroup
	 */
	public function add_condition_from_expression(Expression $expression, $value=NULL, $comparator=EXF_COMPARATOR_IS){
		if (!is_null($value) && $value !== ''){
			$condition = ConditionFactory::create_from_expression($this->exface, $expression, $value, $comparator);
			$this->add_condition($condition);
		}
		return $this;
	}
	
	/**
	 * Creates a new condition and adds it to this condition group.
	 * TODO Refactor to use ConditionFactory::create_from_string() and process special prefixes and so on there 
	 * @param string $column_name
	 * @param mixed $value
	 * @param string $comparator
	 * @return ConditionGroup
	 */
	function add_conditions_from_string(Object &$base_object, $expression_string, $value, $comparator = null){
		// Determine the comparator if it is not given directly.
		// It can be derived from the value or set to a default value
		if (is_null($comparator)){
			if (strpos($value, '==') === 0) { $comparator = '=='; $value = substr($value, 2); }
			elseif (strpos($value, '=') === 0) { $comparator = '='; $value = substr($value, 1); }
			elseif (strpos($value, '>') === 0) { $comparator = '>'; $value = substr($value, 1); }
			elseif (strpos($value, '>=') === 0) { $comparator = '>='; $value = substr($value, 2); }
			elseif (strpos($value, '<') === 0) { $comparator = '<'; $value = substr($value, 1); }
			elseif (strpos($value, '<=') === 0) { $comparator = '<='; $value = substr($value, 2); }
			else { $comparator = EXF_COMPARATOR_IS; }
	
			$value = trim($value);
				
			// a value enclosed in [] is actually a IN-statement
			if (substr($value, 0, 1) == '[' && substr($value, -1) == ']'){
				$value = trim($value, '[]');
				$comparator = EXF_COMPARATOR_IN;
			}
			// if a numeric attribute has a value with commas, it is actually an IN-statement
			elseif (strpos($expression_string, ',') === false
					&& $base_object->get_attribute($expression_string)
					&&($base_object->get_attribute($expression_string)->get_data_type()->is(EXF_DATA_TYPE_NUMBER)
							|| $base_object->get_attribute($expression_string)->get_data_type()->is(EXF_DATA_TYPE_RELATION)
							)
					&& strpos($value, ',') !== false){
						$comparator = EXF_COMPARATOR_IN;
			}
		}
			
		// Another special feature is the possibility to specify a comma separated list of attributes in one  element
		// of the filters array, wich means that at least one of the attributes should match the value
		$expression_strings = explode(',', $expression_string);
		if (count($expression_strings) > 1){
			$group = ConditionGroupFactory::create_empty($this->exface, EXF_LOGICAL_OR);
			foreach ($expression_strings as $f){
				$group->add_condition_from_expression($this->exface->model()->parse_expression($f, $base_object), $value, $comparator);
			}
			$this->add_nested_group($group);
		} else {
			$this->add_condition_from_expression($this->exface->model()->parse_expression($expression_string, $base_object), $value, $comparator);
		}
	
		return $this;
	}
	
	/**
	 * Adds a subgroup to this group.
	 * @param ConditionGroup $group
	 * @return \exface\Core\CommonLogic\Model\ConditionGroup
	 */
	public function add_nested_group(ConditionGroup $group){
		$this->nested_groups[] = $group;
		return $this;
	}
	
	/**
	 * Returns an array of conditions directly contained in this group (not in the subgroups!). Returns an empty array if the group does not have conditions.
	 * @return Condition[]
	 */
	public function get_conditions(){
		return $this->conditions;
	}
	
	/**
	 * Returns an array of condition groups directly contained in this group (not in the subgroups!). Returns an empty array if the group does not have subgroups.
	 * @return ConditionGroup[]
	 */
	public function get_nested_groups(){
		return $this->nested_groups;
	}
	
	/**
	 * Returns the logical operator of the group. Operators are defined by the EXF_LOGICAL_xxx constants.
	 * @return string
	 */
	public function get_operator() {
		return $this->operator;
	}
	
	/**
	 * Sets the logical operator of the group. Operators are defined by the EXF_LOGICAL_xxx constants.
	 * @param string $value
	 */
	public function set_operator($value) {
		// TODO Check, if the group operator is one of the allowed logical operators
		if ($value){
			$this->operator = $value;
		}
	}	
	
	/**
	 * Returns a condition group with the same conditions, but based on a related object specified by the given relation path. 
	 * @see expression::rebase()
	 *
	 * @param string $relation_path_to_new_base_object
	 * @return ConditionGroup
	 */
	public function rebase($relation_path_to_new_base_object, $remove_conditions_not_matching_the_path = false){
		// Do nothing, if the relation path is empty (nothing to rebase...)
		if (!$relation_path_to_new_base_object) return $this;
		
		$result = ConditionGroupFactory::create_empty($this->exface, $this->get_operator());
		foreach ($this->get_conditions() as $condition){
			// Remove conditions not matching the path if required by user
			if ($remove_conditions_not_matching_the_path && $condition->get_expression()->is_meta_attribute()){
				if (strpos($condition->get_expression()->to_string(), $relation_path_to_new_base_object) !== 0){
					continue;
				}
			}
			
			// Rebase the expression behind the condition and create a new condition from it
			try {
				$new_expression = $condition->get_expression()->rebase($relation_path_to_new_base_object);
			} catch (ExpressionRebaseImpossibleError $e){
				// Silently omit conditions, that cannot be rebased
				continue;
			}
			$new_condition = ConditionFactory::create_from_expression($this->exface, $new_expression, $condition->get_value(), $condition->get_comparator());
			$result->add_condition($new_condition);
		}
	
		foreach ($this->get_nested_groups() as $group){
			$result->add_nested_group($group->rebase($relation_path_to_new_base_object));
		}
		
		return $result;
	}
	
	public function get_workbench(){
		return $this->exface;
	}
	
	public function to_string(){
		$result = '';
		foreach ($this->get_conditions() as $cond){
			$result .= ($result ? ' ' . $this->get_operator() . ' ' : '') . $cond->to_string();
		}
		foreach ($this->get_nested_groups() as $group){
			$result .= ($result ? ' ' . $this->get_operator() . ' ' : '') . '( ' . $group->to_string() . ' )';
		}
		return $result;
	}
	
	public function export_uxon_object(){
		$uxon = new UxonObject();
		$uxon->operator = $this->get_operator();
		$uxon->conditions = array();
		$uxon->nested_groups = array();
		foreach ($this->get_conditions() as $cond){
			$uxon->conditions[] = $cond->export_uxon_object();
		}
		foreach ($this->get_nested_groups() as $group){
			$uxon->nested_groups = $group->export_uxon_object();
		}
		return $uxon;
	}
	
	public function import_uxon_object(UxonObject $uxon){
		$this->set_operator($uxon->operator);
		foreach ($uxon->conditions as $cond){
			$this->add_condition(ConditionFactory::create_from_object_or_array($this->exface, $cond));
		}
		foreach ($uxon->nested_groups as $group){
			$this->add_nested_group(ConditionGroupFactory::create_from_object_or_array($this->exface, $group));
		}
	}
	
	public function get_model(){
		return $this->get_workbench()->model();
	}
	
	public function is_empty(){
		if (count($this->get_conditions()) == 0 && count($this->get_nested_groups()) == 0){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Removes a given condition from this condition group (not from the nested groups!)
	 * @param Condition $condition
	 * @return Condition
	 */
	public function remove_condition(Condition $condition){
		foreach ($this->get_conditions() as $i => $cond){
			if ($cond == $condition){
				unset($this->conditions[$i]);
			}
		}
		return $this;
	}
	
	/**
	 * Removes all conditions and nested groups from this condition group thus resetting it completely
	 * @return ConditionGroup
	 */
	public function remove_all(){
		$this->conditions = array();
		$this->nested_groups = array();
		return $this;
	}
}
?>