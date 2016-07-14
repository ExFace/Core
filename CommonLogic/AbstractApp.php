<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Actions\ActionInterface;

abstract class AbstractApp implements AppInterface {
	private $exface = null;
	private $uid = null;
	private $vendor = null;
	private $alias = null;
	private $alias_with_namespace = '';
	private $directory = '';
	private $configuration_data_sheet = null;
	private $name_resolver =  null;
	
	/**
	 * 
	 * @param \exface\Core\CommonLogic\Workbench $exface
	 * @deprecated use AppFactory instead!
	 */
	public function __construct(\exface\Core\CommonLogic\Workbench &$exface){
		$this->exface = $exface;
		// Create an alias from the class (e.g. "exface.core" from "exface\Core\Core\CoreApp")
		$this->alias_with_namespace = str_replace(array($this->get_apps_class_namespace(), NameResolver::CLASS_NAMESPACE_SEPARATOR), array('', NameResolver::NAMESPACE_SEPARATOR), substr(get_class($this), 0, strrpos(get_class($this), NameResolver::CLASS_NAMESPACE_SEPARATOR)));
		$this->init();
	}
	
	/**
	 * This ist the startup-method for apps. Anything put here will be run right after the app is instantiated. By default it does not do anything!
	 * This method is handy to initialize some dependencies, variables, etc. 
	 * @return void
	 */
	protected function init(){
		
	}
	
	/**
	 * Returns an action object
	 * @param unknown $action_alias
	 * @return ActionInterface
	 */
	public function get_action($action_alias, \exface\Core\Widgets\AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null){
		if (!$action_alias) return false;
		$action_class = '\\exface\\Apps\\' . $this->get_class_namespace() . '\\Actions\\' . $action_alias;
		$action = new $action_class($this);
		if ($called_by_widget){
			$action->set_called_by_widget($called_by_widget);
		}
		if ($uxon_description instanceof \stdClass){
			$action->import_uxon_object($uxon_description);
		}
		return $action;
	}
	
	public function get_alias_with_namespace(){
		return $this->alias_with_namespace;
	}
	
	public function get_alias(){
		if (is_null($this->alias)){
			$this->alias = str_replace($this->get_vendor() . DIRECTORY_SEPARATOR, '', $this->get_alias_with_namespace());
		}
		return $this->alias;
	}
	
	/**
	 * Returns the directory path to the app folder relative to exface/apps
	 * @return string;
	 */
	public function get_directory(){
		if (!$this->directory){
			$this->directory = str_replace($this->get_workbench()->get_config_value('namespace_separator'), DIRECTORY_SEPARATOR, $this->get_alias_with_namespace());
		}
		return $this->directory;
	}
	
	public function get_namespace(){
		return substr($this->get_alias_with_namespace(), 0, mb_strripos($this->get_alias_with_namespace(), NameResolver::NAMESPACE_SEPARATOR));
	}
	
	public function get_class_namespace(){
		return str_replace($this->get_workbench()->get_config_value('namespace_separator'), '\\', $this->get_alias_with_namespace());
	}
	
	/**
	 * Return the applications vendor (first part of the namespace)
	 * @return string
	 */
	public function get_vendor(){
		if (is_null($this->vendor)){
			$this->vendor = explode(NameResolver::NAMESPACE_SEPARATOR, $this->get_alias_with_namespace())[0];
		}
		return $this->vendor;
	}
	
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * Returns a data sheet, that contains all configuration options for this app.
	 * NOTE: To fetch a single configuration value it is far better to use get_configuration_value()
	 * @see get_configuration_value()
	 * @return DataSheetInterface
	 */
	protected function get_configuration_data_sheet(){
		if (is_null($this->configuration_data_sheet)){
			$ds = $this->get_workbench()->data()->create_data_sheet($this->get_workbench()->model()->get_object('exface.Core.APP_CONFIG'));
			$ds->get_columns()->add_from_expression('CODE');
			$ds->get_columns()->add_from_expression('VALUE');
			$ds->add_filter_from_string('APP__ALIAS', $this->get_alias_with_namespace());
			$ds->data_read();
			$this->configuration_data_sheet = $ds;
		}
		return $this->configuration_data_sheet;
	}
	
	/**
	 * Returns a single configuration value specified by the given code
	 * @param string $code
	 * @return multitype
	 */
	public function get_configuration_value($code){
		return $this->get_configuration_data_sheet()->get_cell_value('VALUE', $this->get_configuration_data_sheet()->get_column('CODE')->find_row_by_value($code));
	}
	
	public function get_apps_class_namespace(){
		return 'exface\\Apps\\';
	}
	
	public function get_uid(){
		if (is_null($this->uid)){
			$ds = $this->get_workbench()->data()->create_data_sheet($this->get_workbench()->model()->get_object('exface.Core.APP'));
			$ds->add_filter_from_string('ALIAS', $this->get_alias_with_namespace());
			$ds->data_read();
			$this->uid = $ds->get_uid_column()->get_cell_value(0);
		}
		return $this->uid;
	}
	
	public function get_name_resolver() {
		return $this->name_resolver;
	}
	
	public function set_name_resolver(NameResolver $value) {
		$this->name_resolver = $value;
		return $this;
	}	
}
?>