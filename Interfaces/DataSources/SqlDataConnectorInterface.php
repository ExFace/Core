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
     *
     * @param SqlDataQuery $query            
     * @return integer
     */
    public function getAffectedRowsCount(SqlDataQuery $query);

    /**
     *
     * @param SqlDataQuery $query            
     * @return void
     */
    public function freeResult(SqlDataQuery $query);

    /**
     * Returns an instance of SQL explorer for this connection
     *
     * @return ModelizerInterface
     */
    public function getModelizer();
}

?>