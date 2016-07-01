<?php
namespace exface\Core\Model;
use exface\Core\Model\Expression;
use exface\Core\UxonObject;
use exface\Core\Model\DataTypes\String;
use exface\Core\Model\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataValidationException;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\DataTypeFactory;
/**
 * . Thus, a condition is basically
 * something like "expr = a" or "date > 01.01.1970", etc, while a ConditionGroup can be used to combine multiple conditions using
 * logical operators like AND, OR, etc.
 * @author aka
 * 
 */
class Condition implements iCanBeConvertedToUxon {
	private $exface = NULL;
	private $expression = NULL;
	private $value = NULL;
	private $comparator = NULL;
	private $data_type = NULL;
	
	/**
	 * 
	 * @param \exface\Core\exface $exface
	 * @param string|\exface\Core\Model\Expression $string_or_expression
	 * @param string $value
	 * @param string $comparator
	 */
	public function __construct(\exface\Core\exface &$exface){
		$this->exface = $exface;
	}
	
	/**
	 * Returns the expression to filter
	 * @return expression
	 */
	public function get_expression() {
		return $this->expression;
	}
	
	public function set_expression(Expression $expression) {
		$this->expression = $expression;
	}
	
	public function get_value() {
		return $this->value;
	}
	
	public function set_value($value) {
		try {
			$value = $this->get_data_type()->parse($value);
		} catch (\Exception $e) {
			throw new DataValidationException('Illegal filter value "' . $value . '" for attribute "' . $this->get_attribute_alias() . '" of data type "' . $this->get_expression()->get_attribute()->get_data_type()->get_name() . '": ' . $e->getMessage());
			$value = null;
			break;
		}
		$this->value = $value;
	}
	
	public function get_comparator() {
		return $this->comparator;
	}
	
	public function set_comparator($value) {
		$this->comparator = $value;
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
		$this->set_expression($this->exface->model()->parse_expression($uxon_object->expression, $this->exface->model()->get_object($uxon_object->object_alias)));
		$this->set_comparator($uxon_object->comparator);
		$this->set_value($uxon_object->value);
	}
	
	public function get_model(){
		return $this->exface->model();
	}	
}