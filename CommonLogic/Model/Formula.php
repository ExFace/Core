<?php
namespace exface\Core\CommonLogic\Model;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;

/**
 * Data functions are much like Excel functions. They calculate 
 * the value of a cell in a data_sheet based on other data from 
 * this sheet and user defined arguments. 
 * @author Andrej Kabachnik
 *
 */
abstract class Formula implements ExfaceClassInterface {
	private $required_attributes = array();
	private $arguments = array();
	private $data_sheet = null;
	private $relation_path = null;
	private $data_type = NULL;
	private $exface = null;
	
	public function __construct(Workbench $workbench){
		$this->exface = $workbench;
	}
	
	public function get_workbench(){
		return $this->exface;
	}
		
	/**
	 * Parses the the arguments for this function. Each argument 
	 * is an ExFace expression, which in turn can be another function, 
	 * a reference, a constant - whatever. We generally instatiate 
	 * expression objects for the arguments together with the function 
	 * and not while applying the function to data, because argument types 
	 * do not change depending on the contents of cells of data_sheets. 
	 * It is faster to create the respective expressions here and just 
	 * evaluate them, when really running the function.
	 * @param array arguments
	 */
	function init(array $arguments){
		// now find out, what each parameter is: a column reference, a string, a widget reference etc.
		foreach ($arguments as $arg){
			$expr = $this->get_workbench()->model()->parse_expression($arg);
			$this->arguments[] = $expr;
			$this->required_attributes = array_merge($this->required_attributes, $expr->get_required_attributes());
		}
	}
	
	/**
	 * Evaluates the function based on a given data sheet and the coordinates 
	 * of a cell (data functions are only applicable to specific cells!)
	 * This method is called for every row of a data sheet, while the function 
	 * is mostly defined for an entire column, so we try to do as little as possible 
	 * here: evaluate each argument's expression and call the run() method with 
	 * the resulting values. At this point all arguments are ExFace expressions 
	 * already. They where instantiated together with the function.
	 * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet
	 * @param string $column_name
	 * @param int $row_number
	 * @return mixed
	 */
	function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $column_name, $row_number){
		$args = array();
		foreach ($this->arguments as $expr){
			$args[] = $expr->evaluate($data_sheet, $column_name, $row_number);
		}
		// IDEA keep only a name reference to the sheet!
		$this->data_sheet = $data_sheet;
		
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
	}
	
	function get_required_attributes(){
		return $this->required_attributes;
	}
	
	function get_arguments(){
		return $this->arguments;
	}
	
	public function get_data_sheet() {
		return $this->data_sheet;
	}
	
	public function set_data_sheet($value) {
		$this->data_sheet = $value;
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
}
?>