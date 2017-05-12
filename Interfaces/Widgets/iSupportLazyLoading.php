<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iSupportLazyLoading extends WidgetInterface {
	public function get_lazy_loading();
	public function set_lazy_loading($value);
	public function get_lazy_loading_action();
	public function set_lazy_loading_action($value);
	public function get_lazy_loading_group_id();
	
	/**
	 * Title
     * 
     * Description
     * 
     * @uxon-property lazy_loading_group_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 */
	public function set_lazy_loading_group_id($value);
}