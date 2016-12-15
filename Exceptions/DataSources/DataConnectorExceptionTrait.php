<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

trait DataConnectorExceptionTrait {
	
	private $connector = null;
	
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