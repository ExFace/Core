<?php namespace exface\Core\Actions;

use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * This creates and displays a widget from a JSON file containing some UXON description of the widget.
 *
 * It is used for instance to show errors with additional information from log detail files. Such files contain
 * UXON like this:
 *
 * {
 *   "widget_type": "DebugMessage",
 *   "object_alias": "exface.Core.ERROR",
 *   "visibility": "normal",
 *   "widgets": [
 *     {
 *       "id": "error_tab",
 *		 "widget_type": "Tab",
 *		 "object_alias": "exface.Core.ERROR",
 *		 "caption": "Error",
 *		 "visibility": "normal",
 *		 "widgets": [
 *		   {
 *		     "widget_type": "TextHeading",
 *		     "object_alias": "exface.Core.ERROR",
 *		     "value": "Error 6T91AR9: Invalid data filter widget configuration",
 *		     "visibility": "normal",
 *		     "heading_level": 2
 *		   },
 *		   {
 *		     "widget_type": "Text",
 *		     "object_alias": "exface.Core.ERROR",
 *		     "value": "Cannot create a filter for attribute alias \"NO\" in widget \"style\": attribute not found for object \"alexa.RMS.ARTICLE\"!",
 *		     "visibility": "normal"
 *		   },
 *		   {
 *		     "widget_type": "Text",
 *		     "object_alias": "exface.Core.ERROR",
 *		     "caption": "Description",
 *		     "hint": "[Text] ",
 *		     "visibility": "normal",
 *		     "attribute_alias": "DESCRIPTION"
 *		   }
 *		 ]
 *	   },
 *   ... eventually more tabs ...
 *   ]
 * }
 *
 * @author Thomas Walter
 *
 */
class ShowDialogFromFile extends ShowDialog {
	private $file_path_attribute_alias = null;
	private $file_extension = null;
    
	/**
	 * 
	 * @return string
	 */
	public function get_file_path_attribute_alias() {
		return $this->file_path_attribute_alias;
	}
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Actions\ShowDialogFromFile
	 */
	public function set_file_path_attribute_alias($value) {
		$this->file_path_attribute_alias = $value;
		return $this;
	}

	protected function perform() {
		$basePath = Filemanager::path_normalize($this->get_workbench()->filemanager()->get_path_to_base_folder());

		$filename = $this->get_input_data_sheet()->get_columns()->get_by_expression($this->get_file_path_attribute_alias())->get_cell_value(0);
		if (strlen(trim($filename)) > 0) {
		    if ($this->get_folder_path()){
		        if (Filemanager::path_is_absolute($this->get_folder_path())){
		            $basePath = $this->get_folder_path();
		        } else {
		            $basePath = Filemanager::path_join(array($basePath, $this->get_folder_path()));
		        }
		    }
			$completeFilename = $basePath . '/' . $filename . ($this->get_file_extension() ? '.' . ltrim($this->get_file_extension(), ".") : '');
			if (file_exists($completeFilename)) {
				$json = file_get_contents($completeFilename);
				$this->set_widget(WidgetFactory::create_from_uxon($this->get_dialog_widget()->get_page(), UxonObject::from_json($json), $this->get_dialog_widget()));
			} else {
				throw new FileNotFoundError('File "' . $completeFilename. '" not found!');
			}
		} else {
			throw new ActionInputMissingError($this, 'No file name found in input column "' . $this->get_file_path_attribute_alias() . '" for action "' . $this->get_alias_with_namespace() . '"!');
		}
		
		if (!$this->get_widget()->get_caption()){
			$this->get_widget()->set_caption($completeFilename);
		}

		parent::perform();
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_file_extension() {
	    return $this->file_extension;
	}
	
	/**
	 * Sets the file extension to be used if file_path_attribute_alias does not contain the extension.
	 * 
	 * If the value of the file path attribute only contains the file name and no extension, add the
	 * extension here: e.g. "json" or ".json".
	 * 
	 * @uxon-property file_extension
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Actions\ShowDialogFromFile
	 */
	public function set_file_extension($value) {
	    $this->file_extension = $value;
	    return $this;
	}  
	
	/**
	 * 
	 * @return string
	 */
	public function get_folder_path() {
	    return $this->folder_path;
	}
	
	/**
	 * Adds a folder path to the value of the file path. Relative paths will be assumed relative to the installation folder.
	 * 
	 * @uxon-property folder_path
	 * @uxon-type string
	 * 
	 * @param unknown $value
	 * @return \exface\Core\Actions\ShowDialogFromFile
	 */
	public function set_folder_path($value) {
	    $this->folder_path = $value;
	    return $this;
	}  
  
}

?>