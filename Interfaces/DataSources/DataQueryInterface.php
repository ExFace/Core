<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\iCanBeConvertedToString;

/**
 * DataQueries are what query builder actually build. The extact contents of the data query depends solemly on the DataConnector it is
 * meant for. Thus, an SqlDataQuery would have totally different contents than a UrlDataQuery (ans SQL query vs. a PSR7 request). This
 * is the mutual interface "to rule them all".
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataQueryInterface extends iCanBeConvertedToUxon, iCanBeConvertedToString {
	
	/**
	 * Returns the number of rows affected by the this query
	 * @return integer
	 */
	public function count_affected_rows();
	

}
?>