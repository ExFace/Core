<?php
namespace exface\Core\Interfaces;

interface iCanBeConvertedToString {
	/**
	 * Returns the string of the business object. If the string is imported back via import_uxon_object(), it should
	 * result in the same business object.
	 * 
	 * @return string
	 */
	public function export_string();

	/**
	 * Sets properties of this business object according to the string description.
	 * 
	 * @param string $string
	 * @return void
	 */
	public function import_string($string);
}
?>