<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Exceptions\ExceptionTrait;

trait DataConnectorExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $connector = null;
	
	/**
	 * 
	 * @param DataConnectionInterface $connector
	 * @param string $message
	 * @param string $code
	 * @param \Throwable $previous
	 */
	public function __construct (DataConnectionInterface $connector, $message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->set_connector($connector);
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::get_connector()
	 */
	public function get_connector(){
		return $this->connector;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::set_connector()
	 */
	public function set_connector(DataConnectionInterface $connector){
		$this->connector = $connector;
		return $this;
	}
	
}
?>