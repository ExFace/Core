<?php namespace exface\Core\Widgets;

class ErrorMessage extends DebugMessage {
	
	public function get_caption(){
		return $this->translate('ERROR.CAPTION');
	}
}
