<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
abstract class AbstractDataQuery implements DataQueryInterface {
	private $query_builder = null;
	
	public function __construct(AbstractQueryBuilder $query_builder = null){
		$this->query_builder = $query_builder;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataQueryInterface::get_query_builder()
	 */
	public function get_query_builder(){
		return $this->query_builder;
	}
	
	/**
	 * 
	 * @param AbstractQueryBuilder $query_builder
	 * @return \exface\Core\CommonLogic\AbstractDataQuery
	 */
	public function set_query_builder(AbstractQueryBuilder $query_builder){
		$this->query_builder = $query_builder;
		return $this;
	}
}