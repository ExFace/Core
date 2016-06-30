<?php namespace exface\Core\Contexts;

use exface\Core\Interfaces\Contexts\ContextInterface;

class UserContextScope extends AbstractContextScope {
	private $user_data = null;
	
	public function get_user_name(){
		return $this->exface()->cms()->get_user_name();
	}
	
	public function get_user_id(){
		return $this->get_user_data()->get_uid_column()->get_cell_value(0);
	}
	
	public function get_user_data_folder_absolute_path(){
		$path = $this->exface()->get_installation_path() . DIRECTORY_SEPARATOR . EXF_FOLDER_USER_DATA . DIRECTORY_SEPARATOR . $this->get_user_data_folder_name();
		if (!file_exists($path)){
			mkdir($path);
		}
		return $path;
	}
	
	public function get_user_data_folder_name(){
		return $this->get_user_name() ? $this->get_user_name() : '.anonymous';
	}
	
	/**
	 * TODO
	 * @see \exface\Core\Contexts\AbstractContextScope::load_context_data()
	 */
	public function load_context_data(ContextInterface &$context){
		
	}
	
	public function save_contexts(){
	
	}
	
	/**
	 * Returns a data sheet with all data from the user object
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 */
	protected function get_user_data(){
		if (is_null($this->user_data)){
			$user_object = $this->exface()->model()->get_object('exface.Core.USER');
			$ds = $this->exface()->data()->create_data_sheet($user_object);
			$ds->get_columns()->add_from_expression($user_object->get_uid_alias());
			$ds->get_columns()->add_from_expression('USERNAME');
			$ds->get_columns()->add_from_expression('FIRST_NAME');
			$ds->get_columns()->add_from_expression('LAST_NAME');
			$ds->add_filter_from_string('USERNAME', $this->get_user_name());
			$ds->data_read();
			$this->user_data = $ds;
		}
		return $this->user_data;
	}
}
?>