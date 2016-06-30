<?php
namespace exface\Core\Actions;
/**
 * This action is just a better understandable alias for ShowWidgetPrefilled
 * @see ShowWidgetPrefilled
 * @author aka
 *
 */
class GoToWidget extends ShowWidgetPrefilled {
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(1);	
	}
	
}
?>