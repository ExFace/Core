<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;

/**
 * DataQueries are what query builder actually build. The extact contents of the data query depends solemly on the DataConnector it is
 * meant for. Thus, an SqlDataQuery would have totally different contents than a UrlDataQuery (ans SQL query vs. a PSR7 request). This
 * is the mutual interface "to rule them all".
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataQueryInterface {
	
	/**
	 * Returns the query builder instance, that produced the query
	 * 
	 * @return AbstractQueryBuilder
	 */
	public function get_query_builder(){
		
	}
	
}
?>