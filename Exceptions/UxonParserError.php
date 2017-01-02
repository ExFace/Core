<?php
namespace exface\Core\Exceptions;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\UxonExceptionInterface;

/**
 * Exception thrown if the entity (widget, action, etc.) represented by a UXON description cannot be instantiated due to invalid or missing properties.
 * 
 * If the entity exists alread, it's class-specific exceptions (e.g. widget or action exceptions) should be preferred
 * to this general exception.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonParserError extends RuntimeException implements UxonExceptionInterface {
	private $uxon = null;
	
	public function __construct (UxonObject $uxon, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_uxon($uxon);
	}
	
	public function get_uxon(){
		return $this->uxon;
	}
	
	public function set_uxon(UxonObject $uxon){
		$this->uxon = $uxon;
		return $this;
	}
}
?>