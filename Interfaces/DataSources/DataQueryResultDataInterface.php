<?php
namespace exface\Core\Interfaces\DataSources;

/**
 * Interface for the result object of query builder operations (create, read, update, delete, count)
 * 
 * This common result object allows the query builder to return any set of result information for
 * every operation. This way, every query builder can decide, which (additional) information to return
 * on what occasion: e.g. a GraphQL query builder might return the total nummber of selectable rows
 * with every read query, while an SQL builder will only do so on an explicit count() operation to
 * avoid expensive COUNT(*) selects.
 * 
 * It is a good idea to let query builders fill-in as many result values as possible without losing
 * performance in the query - this will prevent additional requests to the data source. For example, 
 * a paged read request limited for N rows should request N+1 rows to check if there are more rows
 * to read. This will not affect the performance too much, but will spare a count() requrest unless
 * it is explicitly required.
 * 
 * @author Andrej Kabachnik
 *        
 */
interface DataQueryResultDataInterface
{
    /**
     * Returns the data rows as an array of assotiative arrays with column names as keys.
     * 
     * @return array
     */
    public function getResultRows() : array;
    
    /**
     * Returns TRUE if the result would have more rows if a limit was not used in the query builder.
     * 
     * This is mainly used for pagination: if the result of a read operation has more rows, there are
     * more pages to read.
     * 
     * @return bool
     */
    public function hasMoreRows() : bool;
    
    /**
     * Returns the totals (footer) rows as an array of assotiative arrays with column names as keys.
     * 
     * @return array
     */
    public function getTotalsRows() : array;
    
    /**
     * Returns the number of all rows matching the query (without pagination) if the result has such a counter.
     * 
     * @return int|NULL
     */
    public function getAllRowsCounter() : ?int;
    
    /**
     * Resturns the number of data rows in this result.
     * 
     * @return int
     */
    public function countResultRows() : int;
    
    /**
     * Returns TRUE if the result has at least one data row and FALSE otherwise.
     * 
     * @return bool
     */
    public function hasResultRows() : bool;
    
    /**
     * Returns the number of rows explicitly affected by the query.
     * 
     * The difference to getAllRowsCounter() is, that for a paged read operation, getAffectedRowsCounter() returns
     * the number of rows that were actually read (= included in the result), while getAllRowsCounter() would
     * return the number of rows, that could be read if there would not be any pagination.
     * 
     * @return int|NULL
     */
    public function getAffectedRowsCounter() : ?int;
}
?>