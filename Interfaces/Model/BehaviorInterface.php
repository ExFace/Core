<?php namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Model\Object;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeCopied;

interface BehaviorInterface extends ExfaceClassInterface, iCanBeConvertedToUxon, AliasInterface, iCanBeCopied {
	
	/**
	 * @return Object
	 */
	public function get_object();
	
	/**
	 * 
	 * @param Object $value
	 * @return BehaviorInterface
	 */
	public function set_object(Object &$value);
	
	/**
	 * @return BehaviorInterface
	 */
	public function register();
	
	/**
	 * @return boolean
	 */
	public function is_disabled();
	
	/**
	 * @return BehaviorInterface
	 */
	public function disable();
	
	/**
	 * @return BehaviorInterface
	 */
	public function enable();
	
	/**
	 * @return boolean
	 */
	public function is_registered();
	  
}
?>