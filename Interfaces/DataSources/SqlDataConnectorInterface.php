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
     * Returns the primary key value of the last inserted row if autogeneration of primary keys was used 
     * or NULL otherwise
     * 
     * The specific behavior heavily depends on the PHP extension used in the connection! If it differs
     * from the above definition, it must at least be explicitly supported by the query builder intended
     * to be used with the connector.
     * 
     * @param SqlDataQuery $query            
     * @return string|int|null
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
    
    /**
     * 
     * @param string $string
     * @return string
     */
    public function escapeString(string $string) : string;
}