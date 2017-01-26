<?php
namespace exface\Core\CommonLogic;
use exface\Core\CommonLogic\Workbench;

abstract class AbstractExfaceClass {
	private $exface = null;
	
	public function __construct(Workbench $exface) {
		$this->exface = $exface;
	}
	
	public function get_workbench(){
		return $this->exface;
	}
	
	public function copy(){
		return clone $this;
	}
}