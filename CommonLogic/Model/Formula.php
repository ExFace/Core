<?php
namespace exface\Core\CommonLogic\Model;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;

/**
 * Data functions are much like Excel functions. They calculate 
 * the value of a cell in a data_sheet based on other data from 
 * this sheet and user defined arguments. 
 * @author Andrej Kabachnik
 *
 */
abstract class Formula implements FormulaInterface {
	private $required_attributes = array();
	private $arguments = array();
	private $data_sheet = null;
	private $relation_path = null;
	private $data_type = NULL;
	private $exface = null;
	private $current_column_name = null;
	private $current_row_number = null;
	
	/**
	 * @deprecated use FormulaFactory instead!
	 * @param Workbench $workbench
	 */
	public function __construct(Workbench $workbench){
		$this->exface = $workbench;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->exface;
	}
		
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Formulas\FormulaInterface::init()
	 */
	public function init(array $arguments){
		// now find out, what each parameter is: a column reference, a string, a widget reference etc.
		foreach ($arguments as $arg){
			$expr = $this->get_workbench()->model()->parse_expression($arg);
			$this->arguments[] = $expr;
			$this->required_attributes = array_merge($this->required_attributes, $expr->get_required_attributes());
		}
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Formulas\FormulaInterface::evaluate()
	 */
	public function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $column_name, $row_number){
		$args = array();
		foreach ($this->arguments as $expr){
			$args[] = $expr->evaluate($data_sheet, $column_name, $row_number);
		}
		
		$this->set_data_sheet($data_sheet);
		$this->set_current_column_name($column_name);
		$this->set_current_row_number($row_number);
		
		return call_user_func_array(array($this, 'run'), $args);
	}
	
	public function get_relation_path(){
		return $this->relation_path;
	}
	
	public function set_relation_path($relation_path) {
		// set new relation path
		$this->relation_path = $relation_path;
		if ($relation_path){
			foreach ($this->arguments as $key => $a){
				$a->set_relation_path($relation_path);
				$this->arguments[$key] = $a;
			}
		}
		return $this;
	}
	
	public function get_required_attributes(){
		return $this->required_attributes;
	}
	
	public function get_arguments(){
		return $this->arguments;
	}
	
	/**
	 * Returns the data sheet, the formula is being run on
	 * 
	 * @return DataSheetInterface
	 */
	public function get_data_sheet() {
		return $this->data_sheet;
	}
	
	/**
	 * 
	 * @param DataSheetInterface $value
	 * @return \exface\Core\CommonLogic\Model\Formula
	 */
	protected function set_data_sheet(DataSheetInterface $value) {
		$this->data_sheet = $value;
		return $this;
	}
	
	public function get_data_type() {
		if (is_null($this->data_type)){
			$exface = $this->get_data_sheet()->get_workbench();
			$this->data_type = DataTypeFactory::create_from_alias($exface, EXF_DATA_TYPE_STRING);
		}
		return $this->data_type;
	}
	
	public function set_data_type($value) {
		$this->data_type = $value;
	}  
	
	public function map_attribute($map_from, $map_to){
		foreach ($this->required_attributes as $id => $attr){
			if ($attr == $map_from){
				$this->required_attributes[$id] = $map_to;
			}
		}
		foreach ($this->arguments as $key => $a){
			$a->map_attribute($map_from, $map_to);
			$this->arguments[$key] = $a;
		}
	}
	
	/**
	 * Returns the column name of the data sheet column currently being processed
	 * 
	 * @return string
	 */
	public function get_current_column_name() {
		return $this->current_column_name;
	}
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\CommonLogic\Model\Formula
	 */
	protected function set_current_column_name($value) {
		$this->current_column_name = $value;
		return $this;
	}
	
	/**
	 * Returns the row number in the data sheet currently being processed.
	 *
	 * @return integer
	 */
	public function get_current_row_number() {
		return $this->current_row_number;
	}
	
	/**
	 * 
	 * @param integer $value
	 * @return \exface\Core\CommonLogic\Model\Formula
	 */
	protected function set_current_row_number($value) {
		$this->current_row_number = $value;
		return $this;
	}
	
}
?>