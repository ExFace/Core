<?php namespace exface\Core\Interfaces;

interface DebuggerInterface {
	
	/**
	 * Returns a human readable representation of the exception
	 * @param \Throwable $exception
	 * @return string
	 */
	public function print_exception(\Throwable $exception);
	
}
