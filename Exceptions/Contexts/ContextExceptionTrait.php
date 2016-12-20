<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Contexts\ContextInterface;

trait ContextExceptionTrait {
	
	private $context = null;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::__construct()
	 */
	public function __construct (ContextInterface $context, $message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->set_context($context);
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::get_context()
	 */
	public function get_context(){
		return $this->context;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::set_context()
	 */
	public function set_context(ContextInterface $value){
		$this->context = $value;
		return $this;
	}
	
}
?>