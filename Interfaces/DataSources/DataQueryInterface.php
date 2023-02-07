<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\iCanBeConvertedToString;
use exface\Core\Interfaces\iCanBePrinted;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;

/**
 * DataQueries are what query builder actually build.
 * The extact contents of the data query depends solemly on the DataConnector it is
 * meant for. Thus, an SqlDataQuery would have totally different contents than a UrlDataQuery (ans SQL query vs. a PSR7 request). This
 * is the mutual interface "to rule them all".
 *
 * @author Andrej Kabachnik
 *        
 */
interface DataQueryInterface extends iCanBeConvertedToUxon, iCanBeConvertedToString, iCanBePrinted, iCanGenerateDebugWidgets
{

    /**
     * Returns the number of rows affected by the this query
     *
     * @return integer
     */
    public function countAffectedRows();
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBePrinted::toString()
     */
    public function toString($prettify = true);
    
    /**
     * Returns the time zone for time values inside the data source
     *
     * @return string|NULL
     */
    public function getTimeZone() : ?string;
    
    /**
     *
     * @param string|NULL $value
     * @return DataQueryInterface
     */
    public function setTimeZone(string $value = null) : DataQueryInterface;
}
?>