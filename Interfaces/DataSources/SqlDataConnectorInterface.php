<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\DataQueries\SqlDataQuery;

interface SqlDataConnectorInterface extends DataConnectionInterface, TextualQueryConnectorInterface
{

    /**
     * Runs any sql returning a data query instance.
     * 
     * Using the optional $multiquery parameter you can force the SQL to be
     * executed in multiquery mode (true) or not (false). Multiqueries are
     * obviously more versatile, but also vulnarable to SQL injections. Use
     * this with caution!!!
     *
     * @param string $string      
     * @param bool $multiquery      
     * @return SqlDataQuery
     */
    public function runSql($string, bool $multiquery = null);

    /**
     *
     * @param resource $resource            
     * @return array
     */
    public function makeArray(SqlDataQuery $query);

    /**
     *
     * @param SqlDataQuery $query            
     * @return string
     */
    public function getInsertId(SqlDataQuery $query);

    /**
     * Returns the number of rows affected by the query or NULL if counting was not possible.
     * 
     * @param SqlDataQuery $query            
     * @return int|NULL
     */
    public function getAffectedRowsCount(SqlDataQuery $query);

    /**
     *
     * @param SqlDataQuery $query            
     * @return void
     */
    public function freeResult(SqlDataQuery $query);
}

?>