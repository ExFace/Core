<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowUrl;
use exface\Core\CommonLogic\Model\DataTypes\Boolean;
use exface\Core\CommonLogic\AbstractAction;

/**
 * This action opens a URL for a given object instance. The URL can contain placeholders, that will
 * ber replaced by attribute values of the instance. This is usefull in tables, where a URL needs
 * to be opened for a specific row. Any value from that row can be passed to the URL vial placeholder [#column_id#]
 * @author aka
 *
 */
class GoToUrl extends AbstractAction implements iShowUrl {
	private $url = null;
	private $open_in_new_window = false;
	/**
	 * @uxon urlencode_placeholders Makes all placeholders get encoded and thus URL-safe if set to TRUE. Use FALSE if placeholders are ment to use as-is (e.g. the URL itself is a placeholder)
	 * @var Boolean
	 */
	private $urlencode_placeholders = true;
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(1);	
		$this->set_input_rows_max(1);
		$this->set_icon_name('link');
		return $this;
	}
	
	public function get_url() {
		return $this->url;
	}
	
	public function set_url($value) {
		$this->url = $value;
		return $this;
	}
	
	protected function perform(){
		$vars = array();
		$vals = array();
		foreach ($this->get_input_data_sheet()->get_row(0) as $var => $val){
			$vars[] = '[#' . $var . '#]';
			$vals[] = $val;
		}
		$result = str_replace($vars, $vals, $this->get_url());
		$this->set_result($result);
		$this->set_result_data_sheet($this->get_input_data_sheet());
		return $this;
	}
	
	public function get_open_in_new_window() {
		return $this->open_in_new_window;
	}
	
	public function set_open_in_new_window($value) {
		$this->open_in_new_window = $value;
		return $this;
	}	
	
	public function get_urlencode_placeholders() {
		return $this->urlencode_placeholders;
	}
	
	public function set_urlencode_placeholders($value) {
		$this->urlencode_placeholders = $value;
		return $this;
	}  
}
?>