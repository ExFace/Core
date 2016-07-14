<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\DataSheetException;
use exface\Core\Factories\EntityListFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\CommonLogic\UxonObject;

class DataColumn implements DataColumnInterface {
	private $name = null;
	private $expression = null;
	private $attribute_alias = null;
	private $formula = null;
	private $data_sheet = null;
	private $hidden = false;
	private $formatter = null;
	private $data_type = null;
	private $up_to_date = false;
	private $totals = array();
	
	const COLUMN_NAME_VALIDATOR = '[^A-Za-z0-9_\.]';
	
	function __construct($expression, $name='', DataSheetInterface &$data_sheet){
		$exface = $data_sheet->get_workbench();
		$this->data_sheet = $data_sheet;
		$this->set_expression($expression);
		$this->set_name($name ? $name : $this->get_expression_obj()->to_string());
		$this->totals = EntityListFactory::create_with_entity_factory($exface, $this, 'DataColumnTotalsFactory');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_expression_obj()
	 */
	public function get_expression_obj() {
		if (is_null($this->expression) || $this->expression->is_empty()){
			if ($this->attribute_alias){
				$exface = $this->get_workbench();
				$this->expression = ExpressionFactory::create_from_string($exface, $this->get_attribute_alias(), $this->get_data_sheet()->get_meta_object());
			}
		}
		return $this->expression;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_expression()
	 */
	public function set_expression($expression_or_string) {
		if (!($expression_or_string instanceof Expression)){
			$exface = $this->get_workbench();
			$expression = ExpressionFactory::create_from_string($exface, $expression_or_string);
		} else {
			$expression = $expression_or_string;
		}
		$this->expression = $expression;
		if ($expression->is_meta_attribute()){
			$attribute_alias = $expression->get_required_attributes()[0];
			$this->set_attribute_alias($attribute_alias);
			if ($attr = $this->get_data_sheet()->get_meta_object()->get_attribute($attribute_alias)){
				$this->set_data_type($attr->get_data_type());
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::exface()
	 */
	public function get_workbench(){
		return $this->get_data_sheet()->get_workbench();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_data_sheet()
	 */
	public function get_data_sheet(){
		return $this->data_sheet;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_data_sheet()
	 */
	public function set_data_sheet(DataSheetInterface &$data_sheet){
		$this->data_sheet = $data_sheet;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_name()
	 */
	public function get_name() {
		return $this->name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_name()
	 */
	public function set_name($value) {
		$this->name = static::sanitize_column_name($value);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_hidden()
	 */
	public function get_hidden() {
		return $this->hidden;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_hidden()
	 */
	public function set_hidden($value) {
		$this->hidden = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_formatter()
	 */
	public function get_formatter() {
		return $this->formatter;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_formatter()
	 */
	public function set_formatter($expression) {
		global $exface;
		if (!($expression instanceof expression)){
			$expression = $exface->model()->parse_expression($expression);
		}
		$this->formatter = $expression;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_data_type()
	 */
	public function get_data_type() {
		if (is_null($this->data_type)){
			$exface = $this->get_data_sheet()->get_workbench();
			$this->data_type = DataTypeFactory::create_from_alias($exface, EXF_DATA_TYPE_STRING);
		}
		return $this->data_type;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_data_type()
	 */
	public function set_data_type($data_type_or_string) {
		if ($data_type_or_string){
			if ($data_type_or_string instanceof AbstractDataType){
				$this->data_type = $data_type_or_string;
			} else {
				$exface = $this->get_workbench();
				$this->data_type = DataTypeFactory::create_from_alias($exface, $data_type_or_string);
			}
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_attribute()
	 */
	public function get_attribute(){
		if ($this->get_attribute_alias()){
			return $this->get_data_sheet()->get_meta_object()->get_attribute($this->get_attribute_alias());
		} else {
			return false;
		}
	}   
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_values()
	 */
	public function get_values($include_totals = false){
		return $this->get_data_sheet()->get_column_values($this->get_name(), $include_totals);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_cell_value()
	 */
	public function get_cell_value($row_number){
		return $this->get_data_sheet()->get_cell_value($this->get_name(), $row_number);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_values()
	 */
	public function set_values($column_values, $totals_values = null){
		return $this->get_data_sheet()->set_column_values($this->get_name(), $column_values, $totals_values);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_values_by_expression()
	 */
	public function set_values_by_expression(Expression $expression){
		return $this->set_values($expression->evaluate($this->get_data_sheet(), $this->get_name()));
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::is_up_to_date()
	 */
	public function is_up_to_date() {
		return $this->up_to_date;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_up_to_date()
	 */
	public function set_up_to_date($value) {
		$this->up_to_date = $value;
		return $this;
	}	
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::copy()
	 */
	public function copy(){
		return clone $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = $this->get_data_sheet()->get_workbench()->create_uxon_object();
		$uxon->expression = $this->get_expression_obj()->to_string();
		$uxon->name = $this->get_name();
		$uxon->hidden = $this->get_hidden();
		$uxon->data_type = $this->get_data_type()->get_name();
		if ($this->formula){
			$uxon->formula = $this->get_formula()->to_string();
		}
		if ($this->attribute_alias){
			$uxon->attribute_alias = $this->attribute_alias;
		}
		if (!$this->get_totals()->is_empty()){
			$uxon->totals = $this->get_totals()->export_uxon_object();
		}
		return $uxon;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::import_uxon_object()
	 */
	public function import_uxon_object (UxonObject $uxon){
		$this->set_hidden($uxon->hidden);
		$this->set_data_type($uxon->data_type);
		$this->set_formula($uxon->formula);
		$this->set_attribute_alias($uxon->attribute_alias);
		if (is_array($uxon->totals)){
			foreach ($uxon->totals as $u){
				$total = DataColumnTotalsFactory::create_from_uxon($this, $u);
				$this->get_totals()->add($total);
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::find_row_by_value()
	 */
	public function find_row_by_value($cell_value){
		return array_search($cell_value, $this->get_values(false));
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::diff_values()
	 */
	public function diff_values(DataColumnInterface $another_column){
		return array_diff($this->get_values(false), $another_column->get_values(false));
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::diff_rows()
	 */
	public function diff_rows(DataColumnInterface $another_column){
		$result = array();
		foreach ($this->get_values(false) as $row_nr => $val){
			if ($another_column->get_cell_value($row_nr) !== $val){
				$result[$row_nr] = $val;
			}
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::diff_values_by_uid()
	 */
	public function diff_values_by_uid(DataColumnInterface $another_column){
		$result = array();
		$this_uid_column = $this->get_data_sheet()->get_uid_column();
		$other_uid_column = $another_column->get_data_sheet()->get_uid_column();
		if (!$this_uid_column || !$other_uid_column){
			throw new DataSheetException('Cannot diff rows by uid for column "' . $this->get_name() . '": no UID column found in data sheet!');
		}
		if ($this_uid_column->is_empty() || $other_uid_column->is_empty()){
			throw new DataSheetException('Cannot diff rows by uid for column "' . $this->get_name() . '": the UID column has no data!');
		}
		foreach ($this->get_values(false) as $row_nr => $val){
			$uid = $this_uid_column->get_cell_value($row_nr);
			if ($another_column->get_cell_value($other_uid_column->find_row_by_value($uid)) !== $val){
				$result[$uid] = $val;
			}
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_formula()
	 */
	public function get_formula() {
		return $this->formula;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_formula()
	 */
	public function set_formula($expression_or_string) {
		if ($expression_or_string){
			if ($expression_or_string instanceof Expression){
				$expression = $expression_or_string;
			} else {
				$exface = $this->get_workbench();
				$expression = ExpressionFactory::create_from_string($exface, $expression_or_string);
			}
			if (!$expression->is_formula() && !$expression->is_reference()){
				throw new DataSheetException('Invalid formula "' . $expression->to_string() . 'given to data sheet column "' . $this->get_name() . '"!');
			}
			$this->formula = $expression;
		}
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_attribute_alias()
	 */
	public function get_attribute_alias() {
		if (is_null($this->attribute_alias)){
			if ($this->expression && $this->get_expression_obj()->is_meta_attribute()){
				$this->attribute_alias = $this->get_expression_obj()->to_string();
			}
		}
		return $this->attribute_alias;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_attribute_alias()
	 */
	public function set_attribute_alias($value) {
		$this->attribute_alias = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_totals()
	 */
	public function get_totals() {
		return $this->totals;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::is_empty()
	 */
	public function is_empty(){
		if (count($this->get_values(true)) > 0){
			return false;
		} else {
			return true;
		}
	}
	
	public static function sanitize_column_name($string){
		$name = preg_replace('/'.self::COLUMN_NAME_VALIDATOR.'/', '_', $string);
		return $name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_values_from_defaults()
	 */
	public function set_values_from_defaults(){
		$attr = $this->get_attribute();
		// If there is already a column for the required attribute, check, if it has values for all rows
		foreach ($this->get_values(false) as $row_id => $val){
			if (is_null($val) || $val === ''){
				if ($attr->get_fixed_value()){
					$this->set_value($row_id, $attr->get_fixed_value()->evaluate($this->get_data_sheet(), $this->get_name(), $row_id));
				} elseif ($attr->get_default_value()){
					$this->set_value($row_id, $attr->get_default_value()->evaluate($this->get_data_sheet(), $this->get_name(), $row_id));
				} else {
					throw new \exface\Core\Exceptions\DataSheetException('Cannot create ' . $this->get_meta_object()->get_name() . ': attribute ' . $attr->get_name() . ' not set in row ' . $row_id . '!');
				}
			}
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_value()
	 */
	public function set_value($row_number, $value){
		$this->get_data_sheet()->set_cell_value($this->get_name(), $row_number, $value);
	}
 
}