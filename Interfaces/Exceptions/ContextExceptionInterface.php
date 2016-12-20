<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Contexts\ContextInterface;

Interface ContextExceptionInterface {
	
	/**
	 * 
	 * @param ContextInterface $context
	 * @param string $message
	 * @param string $code
	 * @param \Throwable $previous
	 */
	public function __construct (ContextInterface $context, $message, $code, $previous = null);
	
	/**
	 * 
	 * @return ContextInterface
	 */
	public function get_context();
	
	/**
	 * 
	 * @param ContextInterface $sheet
	 * @return ContextExceptionInterface
	 */
	public function set_context(ContextInterface $context);
	
}
?>