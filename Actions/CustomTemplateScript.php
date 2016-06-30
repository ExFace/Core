<?php namespace exface\Apps\exface\Core\Actions;

use exface\Core\Interfaces\Actions\iRunTemplateScript;
use exface\Core\AbstractAction;

class CustomTemplateScript extends AbstractAction implements iRunTemplateScript {
	private $script_language = "javascript";
	private $script = "";
	
	protected function init(){
		$this->set_icon_name('script');
	}
	
	protected function perform(){
		$this->set_result_data_sheet($this->get_input_data_sheet());
		$this->set_result($this->get_script());
	}
	
	public function get_script_language() {
		return $this->script_language;
	}
	
	public function set_script_language($value) {
		$this->script_language = $value;
	}
	/**
	 * @see \exface\Core\Interfaces\Actions\iRunTemplateScript::get_script()
	 */
	public function get_script() {
		return $this->script;
	}
	
	public function set_script($value) {
		$this->script = $value;
	}
	
	/**
	 * @see \exface\Core\Interfaces\Actions\iRunTemplateScript::print_script()
	 */
	public function print_script($widget_id){
		return $this->prepare_script(array("[#widget_id#]" => $widget_id));
	}
	
	public function print_helper_functions(){
		return '';
	}
	
	public function get_includes(){
		return array();
	}
	
	/**
	 * Replaces placeholders in the script, thus preparing it for use. Expects a placeholders array of the
	 * form [placeholder => value]. If the script is not passed directly, get_script() will be used to get it.
	 * This method can be overridden to easiliy extend or modify the script specified in UXON.
	 * @param array $placeholders [placeholder => value]
	 * @param string $script
	 * @return string valid java script
	 */
	protected function prepare_script(array $placeholders, $script=null){
		return str_replace(array_keys($placeholders), array_values($placeholders), ($script ? $script : $this->get_script()));
	}
}
?>