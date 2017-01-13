<?php namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionConfigurationError;

abstract class AbstractDataConnector implements DataConnectionInterface {
	private $config_array = array();
	private $exface = null;
	
	/**
	 * @deprecated Use DataConnectorFactory instead!
	 */
	function __construct(Workbench $exface, array $config = null) {
		$this->exface = $exface;
		if ($config){
			$this->import_uxon_object(UxonObject::from_array($config));
		}
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		return new UxonObject();
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		foreach ($uxon as $property => $value){
			$method_name = 'set_' . $property;
			if (method_exists($this, $method_name)){
				call_user_func(array($this, $method_name), $value);
			} else {
				throw new DataConnectionConfigurationError($this, 'Invalid data connection configuration: option "' . $property . '" not found for "' . get_class() . '"!', '6T4F41P');
			}
		}
	}
	
	/**
	 * @return NameResolverInterface
	 */
	public function get_name_resolver() {
		return $this->name_resolver;
	}
	
	/**
	 * 
	 * @param NameResolverInterface $value
	 */
	public function set_name_resolver(NameResolverInterface $value) {
		$this->name_resolver = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::get_alias()
	 */
	public function get_alias(){
		return $this->get_name_resolver()->get_alias();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::get_alias_with_namespace()
	 */
	public function get_alias_with_namespace(){
		return $this->get_name_resolver()->get_alias_with_namespace();
	}
	
	public function get_namespace(){
		return $this->get_name_resolver()->get_namespace();
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::connect()
	 */
	public final function connect(){
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_connection_event($this, 'Connect.Before'));
		$result = $this->perform_connect();
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_connection_event($this, 'Connect.After'));
		return $result;
	}
	
	protected abstract function perform_connect();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::disconnect()
	 */
	public final function disconnect(){
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_connection_event($this, 'Disconnect.Before'));
		$result = $this->perform_disconnect();
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_connection_event($this, 'Disconnect.After'));
		return $result;
	}
	
	protected abstract function perform_disconnect();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::query()
	 */
	public final function query(DataQueryInterface $query){
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_connection_event($this, 'Query.Before'));
		$result = $this->perform_query($query);
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_connection_event($this, 'Query.After'));
		return $result;
	}
	
	protected abstract function perform_query(DataQueryInterface $query);
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::exface()
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transaction_start()
	 */
	public abstract function transaction_start();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transaction_commit()
	 */
	public abstract function transaction_commit();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transaction_rollback()
	 */
	public abstract function transaction_rollback();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transaction_is_started()
	 */
	public abstract function transaction_is_started();
}