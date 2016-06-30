<?php namespace exface\Core;

use exface\Core\Exceptions\UxonParserError;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Exceptions\UiWidgetNotFoundException;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\UiPageInterface;

class WidgetLink implements WidgetLinkInterface {
	private $exface;
	private $page_id;
	private $widget_id;
	private $column_id;
	private $row_number;
	
	function __construct(\exface\exface &$exface){
		$this->exface = $exface;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::parse_link()
	 */
	public function parse_link($string_or_object){
		if ($string_or_object instanceof \stdClass){
			return $this->parse_link_object($string_or_object);
		} else {
			return $this->parse_link_string($string_or_object);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::parse_link_string()
	 */
	public function parse_link_string($string){
		$string = trim($string);
		// Check for reference to specific page_id
		if (strpos($string, '[') === 0){
			$page_id = substr($string, 1, strpos($string, ']')-1);
			if ($page_id) {
				$this->set_page_id($page_id);
				$string = substr($string, strpos($string, ']')+1);
			} else {
				throw new UxonParserError('Cannot parse widget reference "' . $string . '"! Expected format: "[page_id]widget_id".');
			}
		} 
		
		// Determine the widget id
		// Now the string definitely does not kontain a resource id any more
		if ($pos = strpos($string, '!')){
			// If there is a "!", there is at least a column id following it
			$widget_id = substr($string, 0, $pos);
			$string = substr($string, ($pos + 1));
			
			// Determine the column id
			if ($pos = strpos($string, '$')){
				// If there is a "$", there is a row number following it
				$column_id = substr($string, 0, $pos);
				$string = substr($string, ($pos + 1));
				$this->set_row_number($string);
			} else {
				// Otherwise, everything that is left, is the column id
				$column_id = $string;
			}
			$this->set_column_id($column_id);
			
		} else {
			// Otherwise, everything that is left, is the widget id
			$widget_id = $string;
		}
		$this->set_widget_id($widget_id);
		
		return $this;
	}
	
	public function parse_link_object(\stdClass $object){
		$this->set_page_id($object->page_id);
		$this->set_widget_id($object->widget_id);
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		return $this->parse_link_object($uxon);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::get_page_id()
	 */
	public function get_page_id() {
		return $this->page_id;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::set_page_id()
	 */
	public function set_page_id($value) {
		$this->page_id = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::get_widget_id()
	 */
	public function get_widget_id() {
		return $this->widget_id;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::set_widget_id()
	 */
	public function set_widget_id($value) {
		$this->widget_id = $value;
	}
	
	/**
	 * Returns the widget instance referenced by this link
	 * @throws uiWidgetNotFoundException if no widget with a matching id can be found in the specified resource
	 * @return AbstractWidget
	 */
	public function get_widget() {
		$widget = $this->get_page()->get_widget($this->get_widget_id());
		if (!$widget){
			throw new UiWidgetNotFoundException('Cannot find widget "' . $this->get_widget_id() . '" in resource "' . $this->get_page_id() . '"!');
		}
		return $widget;
	}   
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::get_page()
	 */
	public function get_page(){
		return $this->exface()->ui()->get_page($this->get_page_id());
	}
	
	/**
	 * @return UxonObject
	 */
	public function get_widget_uxon(){
		$resource = $this->exface->cms()->get_page($this->get_page_id());
		$uxon = UxonObject::from_json($resource);
		if ($this->get_widget_id() && $uxon->widget_id != $this->get_widget_id()){
			$uxon = $this->find_widget_id_in_uxon($uxon, $this->get_widget_id());
			if ($uxon === false){
				$uxon = $this->exface->create_uxon_object();
			}
		}
		return $uxon;
	}
	
	/**
	 * 
	 * @param UxonObject || array $uxon
	 * @param string $widget_id
	 * @return UxonObject|boolean
	 */
	private function find_widget_id_in_uxon($uxon, $widget_id){
		$result = false; 
		if ($uxon instanceof \stdClass){
			if ($uxon->id == $widget_id){
				$result = $uxon;
			} else {
				$array = get_object_vars($uxon);
			}
		} elseif (is_array($uxon)) {
			$array = $uxon;
		} 
		
		if (is_array($array)){
			foreach ($array as $prop){
				if ($result = $this->find_widget_id_in_uxon($prop, $widget_id)){
					return $result;
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = $this->exface->create_uxon_object();
		$uxon->widget_id = $this->get_widget_id();
		$uxon->page_id = $this->get_page_id();
		return $uxon;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::get_column_id()
	 */
	public function get_column_id() {
		return $this->column_id;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::set_column_id()
	 */
	public function set_column_id($value) {
		$this->column_id = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::get_row_number()
	 */
	public function get_row_number() {
		return $this->row_number;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::set_row_number()
	 */
	public function set_row_number($value) {
		$this->row_number = $value;
		return $this;
	}    
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::exface()
	 */
	public function exface(){
		return $this->exface;
	}
}
?>