<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\DataQueries\SqlDataQuery;

interface SqlDataConnectorInterface extends DataConnectionInterface
{

    /**
     * Runs any sql returning a data query instance
     *
     * @param string $string            
     * @return SqlDataQuery
     */
    public function runSql($string);

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