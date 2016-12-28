<?php
namespace exface\Core\Contexts\Types;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Contexts\ContextLoadError;
class FilterContext extends AbstractContext {
	private $conditions_by_object = array();
	
	/**
	 * Returns an array with all conditions from the current context
	 * @param object $object
	 * @return Condition[]
	 * 
	 * TODO Modify to look for possible related objects and rebase() their conditions!
	 * Ccurrently we only look for conitions based on direct attributes of the object given.
	 */
	public function get_conditions(Object $object = NULL) {
		$array = array();
		if ($object){
			// Get object ids of the given object and all its parents
			$ids = array_merge(array($object->get_id()), $object->get_parent_objects_ids());
			// Look for filter conditions for these objects
			foreach ($ids as $object_id){
				if (is_array($this->conditions_by_object[$object_id])){
					// If the condition was created based on another object, we need to rebase it.
					// FIXME The current version only supports inherited attributes, for which the rebase(relation_path_to_new_base_object) does not work,
					// as there is no relation path. So this is a temporal work-around with a manuel rebase. There are different long-term solutions possible. 
					// Apart from that the condition produced here references the object asked for ($object) while it's attribute references the object inherited
					// from. The question, if inherited attributes should retain the parent object is still open - see object::extend_from_object_id()
					// 1) Ensure, there is a relation to the parent object and thus a relation path to rebase. However, the rebase() should not actually change
					//    The alias of the attribute or it's relation path if the parent object does not have it's own data address
					// 2) Create an alternative rebase() method, that would work with objects. This would probably be harder to understand.
					if ($object_id != $object->get_id()){
						foreach ($this->conditions_by_object[$object_id] as $condition){
							$exface = $this->get_workbench();
							$new_expresseion = ExpressionFactory::create_from_string($exface, $condition->get_expression()->to_string(), $object);
							$condition = ConditionFactory::create_from_expression($exface, $new_expresseion, $condition->get_value(), $condition->get_comparator());
							$array[] = $condition;
						}
					} else {
						$array = array_merge($array, $this->conditions_by_object[$object_id]);
					}
				}
			}
		} else {
			foreach ($this->conditions_by_object as $object_id => $conditions){
				foreach ($conditions as $condition){
					$array[] = $condition;
				}
			}
		}
		return $array;
	}
	
	/**
	 * Adds a condition to the current context
	 * @param Condition $condition
	 * @return \exface\Core\Contexts\Types\FilterContext
	 */
	public function add_condition(Condition $condition){
		$this->conditions_by_object[$condition->get_expression()->get_meta_object()->get_id()][$condition->get_expression()->to_string()] = $condition;
		return $this;
	}
	
	/**
	 * Removes a given condition from the current context
	 * @param Condition $condition
	 * @return \exface\Core\Contexts\Types\FilterContext
	 */
	public function remove_condition(Condition $condition){
		unset($this->conditions_by_object[$condition->get_expression()->get_meta_object()->get_id()][$condition->get_expression()->to_string()]);
		return $this;
	}
	
	/**
	 * Removes all conditions based on a certain attribute
	 * @param attribute $attribute
	 * @return \exface\Core\Contexts\Types\FilterContext
	 */
	public function remove_conditions_for_attribute(Attribute $attribute){
		if (is_array($this->conditions_by_object[$attribute->get_object_id()])){
			foreach ($this->conditions_by_object[$attribute->get_object_id()] as $id => $condition){
				if ($condition->get_attribute_alias() == $attribute->get_alias_with_relation_path()){
					unset($this->conditions_by_object[$attribute->get_object_id()][$id]);
				}
			}
		}
		return $this;
	}
	
	/**
	 * Clears all conditions from this context
	 * @return \exface\Core\Contexts\Types\FilterContext
	 */
	public function remove_all_conditions(){
		$this->conditions_by_object = array();
		return $this;
	}
	
	/**
	 * Returns an array with UXON objects for each condition in the context
	 * @return UxonObject
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		if (!$this->is_empty()){
			$uxon->conditions = array();
			foreach ($this->get_conditions() as $condition){
				$uxon->conditions[] = $condition->export_uxon_object();
			}
		}
		return $uxon;
	}
	
	/**
	 * Loads an array of conditions in UXON representation into the context
	 * @param UxonObject $uxon
	 * @throws ContextLoadError
	 * @return \exface\Core\Contexts\Types\FilterContext
	 */
	public function import_uxon_object(UxonObject $uxon){
		$exface = $this->get_workbench();
		if (is_array($uxon->conditions)){
			foreach ($uxon->conditions as $uxon_condition){
				try {
					$this->add_condition(ConditionFactory::create_from_stdClass($exface, $uxon_condition));
				} catch (ErrorExceptionInterface $e) {
					// ignore context that cannot be instantiated!
				}
			}
		} elseif (!is_null($uxon->conditions)) {
			throw new ContextLoadError($this, 'Cannot load filter contexts: Expecting an array of UXON objects, ' . gettype($uxon->conditions) . ' given instead!');
		}
		return $this;
	}
	
	public function is_empty(){
		if (count($this->conditions_by_object) > 0){
			return false;
		} else {
			return true;
		}
	}
	 
}
?>