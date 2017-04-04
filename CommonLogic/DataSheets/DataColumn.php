<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\EntityListFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataSheetDiffError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\UnexpectedValueException;

class DataColumn implements DataColumnInterface {
	
	const COLUMN_NAME_VALIDATOR = '[^A-Za-z0-9_\.]';
	
	// Properties, _not_ to be dublicated on copy()
	private $data_sheet = null;
	
	// Properties, to be dublicated on copy()
	private $name = null;
	private $attribute_alias = null;
	private $hidden = false;
	private $data_type = null;
	private $fresh = false;
	private $totals = array();
	private $ignore_fixed_values = false;
	/** @var Expression */
	private $expression = null;
	/** @var Formula */
	private $formula = null;
	/** @var Expression */
	private $formatter = null;
	
	function __construct($expression, $name='', DataSheetInterface $data_sheet){
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
				$this->expression = ExpressionFactory::create_from_string($exface, $this->get_attribute_alias(), $this->get_meta_object());
			}
		}
		// Make sure, there is always a meta object in the expression. For some reason, this is not always the case.
		// IDEA this check can be removed, once meta object have become mandatory for expressions (planned in distant future)
		if (!$this->expression->get_meta_object()){
			$this->expression->set_meta_object($this->get_meta_object());
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
			$expression = ExpressionFactory::create_from_string($exface, $expression_or_string, $this->get_meta_object());
		} else {
			$expression = $expression_or_string;
		}
		$this->expression = $expression;
		if ($expression->is_meta_attribute()){
			$attribute_alias = $expression->get_required_attributes()[0];
			$this->set_attribute_alias($attribute_alias);
			try {
				$attr = $this->get_meta_object()->get_attribute($attribute_alias);
				$this->set_data_type($attr->get_data_type());
			} catch (MetaAttributeNotFoundError $e){
				// ignore expressions with invalid attribute aliases
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
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
	public function set_data_sheet(DataSheetInterface $data_sheet){
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
	public function set_name($value, $keep_values = false) {
		// If we need to keep values and the column is being renamed (in contrast to being created the first time),
		// remember the current values a clear them from the data sheet
		if ($keep_values && !is_null($this->name)){
			$old_values = $this->get_values(false);
			$this->remove_rows();
		}
		
		// Set the new column name
		$this->name = static::sanitize_column_name($value);
		
		// If we need to keep values and the column had some previously, restore them.
		if ($keep_values && count($old_values) > 0){
			$this->set_values($old_values);
		}
		return $this;
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
		if (!($expression instanceof expression)){
			$expression = $this->get_workbench()->model()->parse_expression($expression);
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
			return $this->get_meta_object()->get_attribute($this->get_attribute_alias());
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
		$this->get_data_sheet()->set_column_values($this->get_name(), $column_values, $totals_values);
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_values_by_expression()
	 */
	public function set_values_by_expression(Expression $expression, $overwrite = true){
		if ($overwrite || $this->is_empty()){
			$this->set_values($expression->evaluate($this->get_data_sheet(), $this->get_name()));
		} else {
			foreach ($this->get_values(false) as $row => $val){
				if (!is_null($val) && $val !== ''){
					$this->set_value($row, $expression->evaluate($this->get_data_sheet(), $this->get_name(), $row));
				}
			}
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::is_fresh()
	 */
	public function is_fresh() {
		return $this->fresh;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_fresh()
	 */
	public function set_fresh($value) {
		$this->fresh = $value;
		return $this;
	}	
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::copy()
	 */
	public function copy(){
		$copy = clone $this;
		if ($this->get_expression_obj()){
			$copy->set_expression($this->get_expression_obj()->copy());
		}
		if ($this->get_formula()){
			$copy->set_formula($this->get_formula()->copy());
		}
		return $copy;
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
	public function find_row_by_value($cell_value, $case_sensitive = false){
		$result = false;
		if ($case_sensitive){
			$result = array_search($cell_value, $this->get_values(false));
		} else {
			foreach ($this->get_values(false) as $row_nr => $row_val){
				if (strcasecmp($cell_value, $row_val) === 0){
					$result = $row_nr;
					break;
				}
			}
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::find_rows_by_value()
	 */
	public function find_rows_by_value($cell_value, $case_sensitive = false){
		$result = array();
		if ($case_sensitive){
			$result = array_keys($this->get_values(false), $cell_value);
		} else {
			foreach ($this->get_values(false) as $row_nr => $row_val){
				if (strcasecmp($cell_value, $row_val) === 0){
					$result[] = $row_nr;
				}
			}
		}
		return $result;
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
			throw new DataSheetDiffError($this->get_data_sheet(), 'Cannot diff rows by uid for column "' . $this->get_name() . '": no UID column found in data sheet!', '6T5UUOI');
		}
		if ($this_uid_column->is_empty() || $other_uid_column->is_empty()){
			throw new DataSheetDiffError($this->get_data_sheet(), 'Cannot diff rows by uid for column "' . $this->get_name() . '": the UID column has no data!', '6T5UUOI');
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
				throw new DataSheetRuntimeError($this->get_data_sheet(), 'Invalid formula "' . $expression->to_string() . 'given to data sheet column "' . $this->get_name() . '"!', '6T5UW0E');
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
					throw new DataSheetRuntimeError($this->get_data_sheet(), 'Cannot fill column with default values ' . $this->get_meta_object()->get_name() . ': attribute ' . $attr->get_name() . ' not set in row ' . $row_id . '!', '6T5UX3Q');
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
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_ignore_fixed_values()
	 */
	public function get_ignore_fixed_values() {
		return $this->ignore_fixed_values;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::set_ignore_fixed_values()
	 */
	public function set_ignore_fixed_values($value) {
		$this->ignore_fixed_values = $value;
		return $this;
	} 
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::remove_rows()
	 */
	public function remove_rows(){
		$this->get_data_sheet()->remove_rows_for_column($this->get_name());
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::aggregate()
	 */
	public function aggregate($aggregate_function_name){
		$result = '';
		$values = $this->get_values(false);
		try {
			$result = static::aggregate_values($values, $aggregate_function_name);
		} catch (\Throwable $e){
			throw new DataSheetRuntimeError($this->get_data_sheet(), 'Cannot aggregate over column "' . $this->get_name() . '" of a data sheet of "' . $this->get_meta_object()->get_alias_with_namespace() . '": unknown aggregator function "' . $aggregate_function_name . '"!', '6T5UXLD', $e);
		}
		return $result;
	}
	
	/**
	 * Reduces the given array of values to a single value by applying the given aggregator function. If no function is specified,
	 * returns the first value.
	 *
	 * @param array $row_array
	 * @return array
	 */
	public static function aggregate_values(array $row_array, $group_function = null){
		$group_function = trim($group_function);
		$args = array();
		if ($args_pos = strpos($group_function, '(')){
			$func = substr($group_function, 0, $args_pos);
			$args = explode(',', substr($group_function, ($args_pos+1), -1));
		} else {
			$func = $group_function;
		}
	
		$output = '';
		switch (mb_strtoupper($func)) {
			case EXF_AGGREGATOR_LIST: $output = implode(($args[0] ? $args[0] : ', '), $row_array); break;
			case EXF_AGGREGATOR_LIST_DISTINCT: $output = implode(($args[0] ? $args[0] : ', '), array_unique($row_array)); break;
			case EXF_AGGREGATOR_MIN: $output = count($row_array) > 0 ? min($row_array) : 0; break;
			case EXF_AGGREGATOR_MAX: $output = count($row_array) > 0 ? max($row_array) : 0; break;
			case EXF_AGGREGATOR_COUNT: $output = count($row_array); break;
			case EXF_AGGREGATOR_COUNT_DISTINCT: $output = count(array_unique($row_array)); break;
			case EXF_AGGREGATOR_SUM: $output = array_sum($row_array); break;
			case EXF_AGGREGATOR_AVG: $output = count($row_array) > 0 ? array_sum($row_array)/count($row_array) : 0; break;
			default: throw new UnexpectedValueException('Invalid aggregator function "' . $group_function . '"!');
		}
		return $output;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::get_meta_object()
	 */
	public function get_meta_object(){
		return $this->get_data_sheet()->get_meta_object();
	}
 
}