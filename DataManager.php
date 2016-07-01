<?php
namespace exface\Core;
use exface\Core\Factories\DataConnectorFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Factories\DataSourceFactory;

class DataManager implements DataManagerInterface {
	private $active_connections;
	private $active_sources;
	private $cache;
	private $exface;
	
	function __construct(\exface\Core\exface &$exface){
		$this->exface = $exface;
		$this->active_sources = array();
		$this->active_connections = array();		
	}
	
	function get_data_source($id, $data_connection_id_or_alias=NULL){
		// first check the cache
		if ($this->active_sources[$id . '-' . $data_connection_id_or_alias]) return $this->active_sources[$id . '-' . $data_connection_id_or_alias];		
		
		// if it is a new source, create it here
		$model = $this->exface()->model();
		$data_source = DataSourceFactory::create_for_data_connection($model, $id, $data_connection_id_or_alias);
		$this->active_sources[$id . '-' . $data_connection_id_or_alias] = $data_source;
		return $data_source;
	}
	
	function connect($data_connector, $config, $data_connection_id){
		// check if connection exists (we only need a data_connection once!)
		if ($data_connection_id && $this->active_connections[$data_connection_id]){
			return $this->active_connections[$data_connection_id];
		}
		
		$con = DataConnectorFactory::create_from_alias($this->exface, $data_connector, $config);
		$con->connect();
		
		// cache the new connection 
		$this->active_connections[$data_connection_id] = $con;
		return $con;
	}
	
	/**
	 * Shut down all open connections
	 */
	function disconnect_all(){
		foreach ($this->active_connections as $src){
			$src->disconnect();
		}
	}
	
	/**
	 * Creates the data connection described in the given data source and returns the connector object
	 * @param int $data_source_id
	 */
	function get_data_connection($data_source_id, $data_connection_id_or_alias = NULL){
		$data_source = $this->get_data_source($data_source_id, $data_connection_id_or_alias);
		return $this->connect($data_source->get_data_connector_alias(), $data_source->get_connection_config(), $data_source->get_connection_id());
	}
	
	/**
	 * Returns the default query builder for the given data source
	 * @param unknown $data_source_id
	 */
	function get_query_builder($data_source_id){
		$data_source = $this->get_data_source($data_source_id);
		return $data_source->get_query_builder_alias();
	}
	
	function create_data_sheet(\exface\Core\Model\Object $meta_object){
		return DataSheetFactory::create_from_object($meta_object);
	}
	
	function set_cache($path, $id, $value){
		$this->cache[$path][$id] = $value;
	}
	
	function get_cache($path, $id){
		return $this->cache[$path][$id];
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::start_transaction()
	 */
	public function start_transaction(){
		$transaction = new DataTransaction($this);
		$transaction->start();
		return $transaction;
	}
	
	function exface(){
		return $this->exface;
	}
}
?>