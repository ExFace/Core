<?php namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

class ShowDialogFromFile extends ShowDialog {
	private $file_path_attribute_alias = null;

	public function get_file_path_attribute_alias() {
		return $this->file_path_attribute_alias;
	}

	public function set_file_path_attribute_alias($value) {
		$this->file_path_attribute_alias = $value;

		return $this;
	}

	protected function perform() {
		$basePath = Filemanager::path_normalize($this->get_workbench()->filemanager()->get_path_to_base_folder());

		$obj          = $this->get_workbench()->model()->get_object('exface.Core.LOG_DETAILS');
		$relativePath = $obj->get_data_address();

		$filename = $this->get_input_data_sheet()->get_columns()->get_by_expression($this->get_file_path_attribute_alias())->get_cell_value(0);

		$completeFilename = $basePath . '/' . $relativePath . '/' . $filename;
		if (file_exists($completeFilename)) {
			$json   = file_get_contents($completeFilename);
			$this->set_widget(WidgetFactory::create_from_uxon($this->get_dialog_widget()->get_page(), UxonObject::from_json($json), $this->get_dialog_widget()));
		}

		parent::perform();
	}
}

?>