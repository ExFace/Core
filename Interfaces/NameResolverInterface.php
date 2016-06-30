<?php namespace exface\Core\Interfaces;

use exface\exface;

interface NameResolverInterface extends ExfaceClassInterface {
	
	public static function create_from_string($string, $object_type, exface &$exface);
	
	public function get_object_type();
	
	public function set_object_type($value);
	
	public function get_alias();
	
	public function set_alias($value);
	
	public function get_namespace();
	
	public function set_namespace($value);
	
	public function get_alias_with_namespace();
	
	public function get_vendor();
	
	/**
	 * Returns the resolved class name in PSR-1 notation
	 * @return string
	 */
	public function get_class_name_with_namespace();
	
	public function get_class_namespace();
	
	public function get_class_directory();
	
	public function class_exists();
	
}