<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iFillEntireContainer extends WidgetInterface {
	
	/**
	 * 
	 * @return 
	 */
	public function get_alternative_container_for_orphaned_siblings();
	
}