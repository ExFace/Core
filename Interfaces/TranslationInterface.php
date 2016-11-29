<?php namespace exface\Core\Interfaces;

use exface\Core\Interfaces\ExfaceClassInterface;

interface TranslationInterface extends ExfaceClassInterface {
	
	/**
	 * 
	 * @param string $message_id
	 * @param array $placeholder_values
	 * @return string
	 */
	public function translate($message_id, array $placeholder_values);
	
	/**
	 * @return string
	 */
	public function get_locale();
	
	/**
	 * 
	 * @param string $string
	 * @return TranslationInterface
	 */
	public function set_locale($string);
		
}


?>