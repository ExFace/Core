<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\ExceptionTrait;

trait ContextExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $context = null;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::__construct()
	 */
	public function __construct (ContextInterface $context, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
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
	 * @see\exface\Core\Interfaces\Exceptions\ContextExceptionInterface::set_context()
	 */
	public function set_context(ContextInterface $value){
		$this->context = $value;
		return $this;
	}
	
}
?>