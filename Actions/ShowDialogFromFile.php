<?php namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

class ShowDialogFromFile extends ShowDialog  {
	private $file_path_attribute_alias = null;
	
	public function get_file_path_attribute_alias(){
		return $this->file_path_attribute_alias;
	}
	
	public function set_file_path_attribute_alias($value){
		$this->file_path_attribute_alias = $value;
		return $this;
	}
	
	protected function perform(){
		$entry_id = $this->get_input_data_sheet()->get_columns()->get_by_expression($this->get_file_path_attribute_alias())->get_cell_value(0);
		/*$json = file_get_contents($filename);
		 WidgetFactory::create_from_uxon($dialog->get_page(), UxonObject::from_json($json), $dialog);*/
		
		parent::perform();
	}
}
?>