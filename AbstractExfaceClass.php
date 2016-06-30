<?php
namespace exface\Core;
use exface\exface;

abstract class AbstractExfaceClass {
	private $exface = null;
	
	public function __construct(exface &$exface) {
		$this->exface = $exface;
	}
	
	public function exface(){
		return $this->exface;
	}
	
	public function copy(){
		return clone $this;
	}
}