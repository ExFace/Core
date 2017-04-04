<?php
namespace exface\Core\CommonLogic\Model;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\UnexpectedValueException;
/**
 * . Thus, a condition is basically
 * something like "expr = a" or "date > 01.01.1970", etc, while a ConditionGroup can be used to combine multiple conditions using
 * logical operators like AND, OR, etc.
 * @author Andrej Kabachnik
 * 
 */
class Condition implements iCanBeConvertedToUxon {
	private $exface = NULL;
	private $expression = NULL;
	private $value = NULL;
	private $comparator = EXF_COMPARATOR_IS;
	private $data_type = NULL;
	
	/**
	 * @deprecated use ConditionFactory instead!
	 * @param \exface\Core\CommonLogic\Workbench $exface
	 */
	public function __construct(\exface\Core\CommonLogic\Workbench $exface){
		$this->exface = $exface;
	}
	
	/**
	 * Returns the expression to filter
	 * @return Expression
	 */
	public function get_expression() {
		return $this->expression;
	}
	
	/**
	 * Sets the expression that will be compared to the value
	 * @param Expression $expression
	 */
	public function set_expression(Expression $expression) {
		$this->expression = $expression;
	}
	
	/**
	 * Returns the value to compare to
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}
	
	/**
	 * Sets the value to compare to
	 * @param mixed $value
	 * @throws RangeException
	 */
	public function set_value($value) {
		try {
			$value = $this->get_data_type()->parse($value);
		} catch (\Throwable $e) {
			throw new RangeException('Illegal filter value "' . $value . '" for attribute "' . $this->get_attribute_alias() . '" of data type "' . $this->get_expression()->get_attribute()->get_data_type()->get_name() . '": ' . $e->getMessage(), '6T5WBNB', $e);
			$value = null;
		}
		$this->value = $value;
	}
	
	/**
	 * Returns the comparison operator from this condition. Normally it is one of the EXF_COMPARATOR_xxx constants.
	 * @return string
	 */
	public function get_comparator() {
		if (is_null($this->comparator)){
			$this->comparator = EXF_COMPARATOR_IS;
		}
		return $this->comparator;
	}
	
	/**
	 * Sets the comparison operator for this condition. Use one of the EXF_COMPARATOR_xxx constants.
	 * 
	 * @param string $value
	 * @throws UnexpectedValueException if the value does not match one of the EXF_COMPARATOR_xxx constants
	 * @return Condition
	 */
	public function set_comparator($value) {
		$validated = false;
		foreach (get_defined_constants(true)['user'] as $constant => $comparator){
			if (substr($constant, 0, 15) === 'EXF_COMPARATOR_'){
				if (strcasecmp($value, $comparator) === 0){
					$validated = true;
					$value = $comparator;
					break;
				}
			}
		}
		$this->comparator = $value;
		
		if (!$validated){
			throw new UnexpectedValueException('Invalid comparator value in condition "' . $this->get_expression()->to_string() . ' ' . $value . ' ' . $this->get_value() . '"!');
		}
		return $this;
	}
	
	/**
	 * @return AbstractDataType
	 */
	public function get_data_type() {
		if (is_null($this->data_type)){
			$this->data_type = DataTypeFactory::create_from_alias($this->exface, EXF_DATA_TYPE_STRING);
		}
		return $this->data_type;
	}
	
	/**
	 * 
	 * @param AbstractDataType $value
	 */
	public function set_data_type(AbstractDataType $value) {
		$this->data_type = $value;
	}
	
	/**
	 * Returns the attribute_alias to filter if the filter is based upon an attribute or FALSE otherwise
	 * @return string|boolean
	 */
	public function get_attribute_alias(){
		if ($this->get_expression()->is_meta_attribute()){
			return $this->get_expression()->to_string();
		} else {
			return false;
		}
	}
	
	public function to_string(){
		return $this->get_expression()->to_string() . ' ' . $this->get_comparator() . ' ' . $this->get_value();
	}
	
	public function __toString(){
		return $this->to_string();
	}
	
	public function export_uxon_object(){
		$uxon = new UxonObject();
		$uxon->expression = $this->get_expression()->to_string();
		$uxon->comparator = $this->get_comparator();
		$uxon->value = $this->get_value();
		$uxon->object_alias = $this->get_expression()->get_meta_object()->get_alias_with_namespace();
		return $uxon;
	}
	
	/**
	 * Imports data from UXON objects like {"object_alias": "...", "expression": "...", "value": "...", "comparator": "..."}
	 * @param UxonObject $uxon_object
	 */
	public function import_uxon_object(UxonObject $uxon_object){
		if ($uxon_object->has_property('expression')){
			$expression = $uxon_object->get_property('expression');
		} elseif ($uxon_object->has_property('attribute_alias')){
			$expression = $uxon_object->get_property('attribute_alias');
		}
		$this->set_expression($this->exface->model()->parse_expression($expression, $this->exface->model()->get_object($uxon_object->get_property('object_alias'))));
		if ($uxon_object->has_property('comparator') && $uxon_object->get_property('comparator')){
			$this->set_comparator($uxon_object->get_property('comparator'));
		} 
		$this->set_value($uxon_object->get_property('value'));
	}
	
	public function get_model(){
		return $this->exface->model();
	}	
}