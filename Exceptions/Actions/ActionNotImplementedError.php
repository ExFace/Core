<?php namespace exface\Core\Exceptions\Actions;

use exface\Core\Exceptions\DataSources\ActionExceptionInterface;
use exface\Core\Exceptions\Actions\ActionExceptionTrait;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\Actions\ActionInterface;

class ActionNotImplementedError extends NotImplementedError implements ActionExceptionInterface {
	
	use ActionExceptionTrait;
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
	 */
	public function __construct (ActionInterface $action, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_action($action);
	}
	
}
