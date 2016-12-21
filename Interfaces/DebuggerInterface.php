<?php namespace exface\Core\Interfaces;

interface DebuggerInterface {
	
	/**
	 * Returns a human readable representation of the exception
	 * @param \Throwable $exception
	 * @return string
	 */
	public function print_exception(\Throwable $exception, $use_html = true);
	
	/**
	 * @return boolean
	 */
	public function get_prettify_errors();
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Interfaces\DebuggerInterface
	 */
	public function set_prettify_errors($value);
	
}
