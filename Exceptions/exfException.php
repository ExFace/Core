<?php
namespace exface\Core\Exceptions;
class exfException extends \Exception {
	
	public function rethrow($new_message = null){
		throw new self(($new_message ? $new_message : $this->getMessage()), $this->getCode(), $this);
	}
}
?>