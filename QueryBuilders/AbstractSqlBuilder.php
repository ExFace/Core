<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataConnectors\AbstractSqlConnector;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSelect;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\CommonLogic\DataSheets\DataAggregator;

/**
 * A query builder for generic SQL syntax.
 * 
 * # Data source options
 * =====================
 * 
 * ## On attribute level
 * ---------------------
 * 
 * - **SQL_DATA_TYPE** - tells the query builder what data type the column has.
 * This is only needed for complex types that require conversion: e.g. binary,
 * LOB, etc. Refer to the description of the specific query builder for concrete
 * usage instructions.
 * 
 * - **SQL_SELECT** - custom SQL statement for the value in a SELECT statement.
 * The placeholders [#alias#] and [#value#] are supported. This is usefull to
 * write wrappers for values (e.g. "NVL('[#value#]', 0)". If the wrapper is
 * placed here, it data address would remain writable, while replacing the
 * column name with a custom SQL statement in the data address itself, would
 * cause an error when writing to it.
 * 
 * - **SQL_SELECT_DATA_ADDRESS** - replaces the data address for SELECT queries
 * 
 * - **SQL_INSERT** - custom SQL statement for the value in an INSERT statement.
 * The placeholders [#alias#] and [#value#] are supported. This is usefull to
 * write wrappers for values (e.g. "to_clob('[#value#]')" to save a string value 
 * to an Oracle CLOB column) or generators (e.g. you could use "UUID()" in MySQL 
 * to have a column always created with a UUID). If you need to use a generator
 * only if no value is given explicitly, use something like this: 
 * IF([#value#]!='', [#value#], UUID())
 * 
 * - **SQL_INSERT_DATA_ADDRESS** - replaces the data address for INSERT queries
 * 
 * - **SQL_UPDATE** - custom SQL statement for the value in an UPDATE statement.
 * Works similarly to SQL_INSERT.
 * 
 * - **SQL_UPDATE_DATA_ADDRESS** - replaces the data address for INSERT queries.
 * Supports the placeholder [#alias#]
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractSqlBuilder extends AbstractQueryBuilder
{

    // CONFIG
    protected $short_alias_max_length = 30;

    // maximum length of SELECT AS aliases
    protected $short_alias_remove_chars = array(
        '.',
        '>',
        '<',
        '-',
        '(',
        ')',
        ':'
    );

    // forbidden chars in SELECT AS aliases
    protected $short_alias_replacer = '_';

    protected $short_alias_forbidden = array(
        'SIZE',
        'SELECT',
        'FROM',
        'AS',
        'PARENT',
        'ID',
        'LEVEL',
        'ORDER',
        'GROUP'
    );

    // forbidden SELECT AS aliases
    protected $short_alias_prefix = 'S';

    // other vars
    protected $select_distinct = false;

    protected $short_aliases = array();

    protected $short_alias_index = 0;

    /**
     * [ [column_name => column_value] ]
     */
    protected $result_rows = array();

    /**
     * [ [column_name => column_value] ] having multiple rows if multiple totals for a single column needed
     */
    protected $result_totals = array();

    protected $result_total_count = 0;

    private $binary_columns = array();

    private $query_id = null;

    private $subquery_counter = 0;

    public function getSelectDistinct()
    {
        return $this->select_distinct;
    }

    public function setSelectDistinct($value)
    {
        $this->select_distinct = $value;
    }

    abstract function buildSqlQuerySelect();

    abstract function buildSqlQueryTotals();

    public function read(AbstractDataConnector $data_connection = null)
    {
        if (! $data_connection)
            $data_connection = $this->getMainObject()->getDataConnection();
        
        $query = $this->buildSqlQuerySelect();
        $q = new SqlDataQuery();
        $q->setSql($query);
        // throw new DataQueryFailedError($q, 'xx');
        // first do the main query
        if ($rows = $data_connection->runSql($query)->getResultArray()) {
            foreach ($this->getBinaryColumns() as $full_alias) {
                $short_alias = $this->getShortAlias($full_alias);
                foreach ($rows as $nr => $row) {
                    $rows[$nr][$full_alias] = $this->decodeBinary($row[$short_alias]);
                }
            }
            // TODO filter away the EXFRN column!
            foreach ($this->short_aliases as $short_alias) {
                $full_alias = $this->getFullAlias($short_alias);
                foreach ($rows as $nr => $row) {
                    $rows[$nr][$full_alias] = $row[$short_alias];
                    unset($rows[$nr][$short_alias]);
                }
            }
            
            $this->result_rows = $rows;
        }
        
        // then do the totals query if needed
        $totals_query = $this->buildSqlQueryTotals();
        if ($totals = $data_connection->runSql($totals_query)->getResultArray()) {
            // the total number of rows is treated differently, than the other totals.
            $this->result_total_count = $totals[0]['EXFCNT'];
            // now save the custom totals.
            foreach ($this->totals as $qpart) {
                $this->result_totals[$qpart->getRow()][$qpart->getAlias()] = $totals[0][$this->getShortAlias($qpart->getAlias())];
            }
        }
        return count($this->result_rows);
    }

    function getResultRows()
    {
        return $this->result_rows;
    }

    function getResultTotals()
    {
        return $this->result_totals;
    }

    function getResultTotalRows()
    {
        return $this->result_total_count;
    }

    /**
     * Checks if writing operations (create, update, delete) are possible for the current query.
     *
     * @return boolean
     */
    protected function isWritable()
    {
        $result = true;
        // First of all find out, if the object's data address is empty or a view. If so, we generally can't write to it!
        if (! $this->getMainObject()->getDataAddress()) {
            throw new QueryBuilderException('The data address of the object "' . $this->getMainObject()->getAlias() . '" is empty. Cannot perform writing operations!');
            $result = false;
            ;
        }
        if ($this->checkForSqlStatement($this->getMainObject()->getDataAddress())) {
            throw new QueryBuilderException('The data address of the object "' . $this->getMainObject()->getAlias() . '" seems to be a view. Cannot write to SQL views!');
            $result = false;
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::create()
     *
     * @param AbstractSqlConnector $data_connection            
     */
    function create(AbstractDataConnector $data_connection = null)
    {
        /* @var $data_connection \exface\Core\AbstractSqlConnector */
        if (! $data_connection)
            $data_connection = $this->getMainObject()->getDataConnection();
        if (! $this->isWritable())
            return 0;
        $insert_ids = array();
        
        $values = array();
        $columns = array();
        $uid_qpart = null;
        // add values
        foreach ($this->getValues() as $qpart) {
            $attr = $qpart->getAttribute();
            if ($attr->getRelationPath()->toString()) {
                throw new QueryBuilderException('Cannot create attribute "' . $attr->getAlias() . '" of object "' . $this->getMainObject()->getAlias() . '". Attributes of related objects cannot be created within the same SQL query!');
                continue;
            }
            // Ignore attributes, that do not reference an sql column (= do not have a data address at all)
            if (! $attr->getDataAddress() || $this->checkForSqlStatement($attr->getDataAddress())) {
                continue;
            }
            // Save the query part for later processing if it is the object's UID
            if ($attr->isUidForObject()) {
                $uid_qpart = $qpart;
            }
            
            // Prepare arrays with column aliases and values to implode them later when building the query
            // Make sure, every column is only addressed once! So the keys of both array actually need to be the column aliases
            // to prevent duplicates
            $columns[$attr->getDataAddress()] = $attr->getDataAddress();
            $custom_insert_sql = $qpart->getDataAddressProperty('SQL_INSERT');
            foreach ($qpart->getValues() as $row => $value) {
                $value = $this->prepareInputValue($value, $attr->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'));
                if ($custom_insert_sql) {
                    // If there is a custom insert SQL for the attribute, use it 
                    // NOTE: if you just write some kind of generator here, it 
                    // will make it impossible to save values passed to the query
                    // via setValues() - they will always be replaced by the 
                    // custom SQL. To allow explicitly set values too, the
                    // INSERT_SQL must include something like IF('[#value#]'!=''...)
                    $values[$row][$attr->getDataAddress()] = str_replace(array(
                        '[#alias#]',
                        '[#value#]'
                    ), array(
                        $this->getMainObject()->getAlias(),
                        $value
                    ), $custom_insert_sql);
                } else {
                    $values[$row][$attr->getDataAddress()] = $value;
                }
            }
        }
        
        // If there is no UID column, but the UID attribute has a custom insert statement, add it at this point manually
        // This is important because the UID will mostly not be marked as a mandatory attribute in order to preserve the
        // possibility of mixed creates and updates among multiple rows. But an empty non-required attribute will never
        // show up as a value here. Still that value is required!
        if (is_null($uid_qpart) && $uid_generator = $this->getMainObject()->getUidAttribute()->getDataAddressProperty('SQL_INSERT')) {
            $uid_generator = str_replace(array(
                '[#alias#]',
                '[#value#]'
            ), array(
                $this->getMainObject()->getAlias(),
                $this->prepareInputValue('', $this->getMainObject()->getUidAttribute()->getDataType(), $this->getMainObject()->getUidAttribute()->getDataAddressProperty('SQL_DATA_TYPE'))
            ), $uid_generator);
            
            $last_uid_sql_var = '@last_uid';
            $columns[] = $this->getMainObject()->getUidAttribute()->getDataAddress();
            foreach ($values as $nr => $row) {
                $values[$nr][] = $last_uid_sql_var . ' := ' . $uid_generator;
            }
        }
        
        foreach ($values as $nr => $row) {
            $sql = 'INSERT INTO ' . $this->getMainObject()->getDataAddress() . ' (' . implode(', ', $columns) . ') VALUES (' . implode(',', $row) . ')';
            $query = $data_connection->runSql($sql);
            
            // Now get the primary key of the last insert.
            if ($last_uid_sql_var) {
                // If the primary key was a custom generated one, it was saved to the corresponding SQL variable.
                // Fetch it from the data base
                $last_id = reset($data_connection->runSql('SELECT CONCAT(\'0x\', HEX(' . $last_uid_sql_var . '))')->getResultArray()[0]);
            } else {
                // If the primary key was autogenerated, fetch it via built-in function
                $last_id = $query->getLastInsertId();
            }
            
            // TODO How to get multipla inserted ids???
            if ($query->countAffectedRows()) {
                $insert_ids[] = $last_id;
            }
        }
        
        // IDEA do bulk inserts instead of separate queries. The problem is:
        // there seems to be no easy way to get all the insert ids of a bulk
        // insert. The code below worked but only returned the first id. 
        // Perhaps, some possibility will be found in future. 
        /*
        foreach ($values as $nr => $row) {
            foreach ($row as $val) {
                $values[$nr] = implode(',', $row);
            }
        }
        $sql = 'INSERT INTO ' . $this->getMainObject()->getDataAddress() . ' (' . implode(', ', $columns) . ') VALUES (' . implode('), (', $values) . ')';
        $query = $data_connection->runSql($sql);
        
        // Now get the primary key of the last insert.
        if ($last_uid_sql_var) {
            // If the primary key was a custom generated one, it was saved to the corresponding SQL variable.
            // Fetch it from the data base
            $last_id = reset($data_connection->runSql('SELECT CONCAT(\'0x\', HEX(' . $last_uid_sql_var . '))')->getResultArray()[0]);
        } else {
            // If the primary key was autogenerated, fetch it via built-in function
            $last_id = $query->getLastInsertId();
        }
        $affected_rows = $query->countAffectedRows();
        
        // TODO How to get multipla inserted ids???
        if ($affected_rows) {
            $insert_ids[] = $last_id;
        }*/
        
        return $insert_ids;
    }

    /**
     * Performs SQL update queries.
     * Depending on the number of rows to be updated, there will be one or more queries performed.
     * Theoretically attributes with one and multiple value rows can be mixed in one QueryBuilder instance: e.g. some attributes
     * need different values per row and others are set to a single value for all rows matching the filter criteria. In this case
     * there will be one SQL query to update all single-value-attribtues and potentially multiple queries to update attributes by
     * row. The latter queries will only have the respecitve primary key in their WHERE clause, whereas the single-value-query
     * will have the filters from the QueryBuilder.
     *
     * In any case, direct updates are only performed on attributes of the main meta object. If an update of a related attribute
     * is needed, a separate update query for the meta object of that attribute will be created and will get executed after the
     * main query. Subqueries are executed in the order in which the respective attributes were added to the QueryBuilder.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::update()
     */
    function update(AbstractDataConnector $data_connection = null)
    {
        if (! $data_connection)
            $data_connection = $this->main_object->getDataConnection();
        if (! $this->isWritable())
            return 0;
        
        // Filters -> WHERE
        // Since UPDATE queries generally do not support joins, tell the build_sql_where() method not to rely on joins in the main query
        $where = $this->buildSqlWhere($this->getFilters(), false);
        $where = $where ? "\n WHERE " . $where : '';
        if (! $where) {
            throw new QueryBuilderException('Cannot perform update on all objects "' . $this->getMainObject()->getAlias() . '"! Forbidden operation!');
        }
        
        // Attributes -> SET
        
        // Array of SET statements for the single-value-query which updates all rows matching the given filters
        // [ 'data_address = value' ]
        $updates_by_filter = array();
        // Array of SET statements to update multiple values per attribute. They will be used to build one UPDATE statement per UID value
        // [ uid_value => [ data_address => 'data_address = value' ] ]
        $updates_by_uid = array();
        // Array of query parts to be placed in subqueries
        $subqueries_qparts = array();
        foreach ($this->getValues() as $qpart) {
            $attr = $qpart->getAttribute();
            if ($attr->getRelationPath()->toString()) {
                $subqueries_qparts[] = $qpart;
                continue;
            }
            
            // Ignore attributes, that do not reference an sql column (= do not have a data address at all)
            if (!$qpart->getDataAddressProperty('SQL_UPDATE') && $this->checkForSqlStatement($attr->getDataAddress())) {
                continue;
            }
            
            if ($qpart->getDataAddressProperty('SQL_UPDATE_DATA_ADDRESS')){
                $column = str_replace('[#alias#]', $this->getShortAlias($this->getMainObject()->getAlias()), $qpart->getDataAddressProperty('SQL_UPDATE_DATA_ADDRESS'));
            } else {
                $column = $this->getShortAlias($this->getMainObject()->getAlias()) . '.' . $attr->getDataAddress();
            }
            
            $custom_update_sql = $qpart->getDataAddressProperty('SQL_UPDATE');
                        
            if (count($qpart->getValues()) == 1) {
                $values = $qpart->getValues();
                $value = $this->prepareInputValue(reset($values), $attr->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'));
                if ($custom_update_sql) {
                    // If there is a custom update SQL for the attribute, use it ONLY if there is no value
                    // Otherwise there would not be any possibility to save explicit values
                    $updates_by_filter[]= $column . ' = ' . $this->buildSqlUpdateCustomValue($custom_update_sql, $this->getShortAlias($this->getMainObject()->getAlias()), $value);
                } else {
                    $updates_by_filter[] = $column . ' = ' . $value;
                }
            } else {
                // TODO check, if there is an id for each value. Those without ids should be put into another query to make an insert
                // $cases = '';
                if (count($qpart->getUids()) == 0) {
                    throw new QueryBuilderException('Cannot update attribute "' . $qpart->getAlias() . "': no UIDs for rows to update given!");
                }
                
                foreach ($qpart->getValues() as $row_nr => $value) {
                    $value = $this->prepareInputValue($value, $attr->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'));
                    if ($custom_update_sql) {
                        // If there is a custom update SQL for the attribute, use it ONLY if there is no value
                        // Otherwise there would not be any possibility to save explicit values
                        $updates_by_uid[$qpart->getUids()[$row_nr]][$column] = $column . ' = ' . $this->buildSqlUpdateCustomValue($custom_update_sql, $this->getShortAlias($this->getMainObject()->getAlias()), $value);
                    } else {
                        /*
                         * IDEA In earlier versions multi-value-updates generated a single query with a CASE statement for each attribute.
                         * This worked fine for smaller numbers of values (<50) but depleted the memory with hundreds of values per attribute.
                         * A quick fix was to introduce separate queries per value. But it takes a lot of time to fire 1000 separate queries.
                         * So we could mix the two approaches and make separate queries every 10-30 values with fairly short CASE statements.
                         * This would shorten the number of queries needed by factor 10-30, but it requires the separation of values of all
                         * participating attributes into blocks sorted by UID. In other words, the resulting queries must have all values for
                         * the UIDs they address and a new filter with exactly this list of UIDs.
                         */
                        // $cases[$qpart->getUids()[$row_nr]] = 'WHEN ' . $qpart->getUids()[$row_nr] . ' THEN ' . $value . "\n";
                        $updates_by_uid[$qpart->getUids()[$row_nr]][$column] = $column . ' = ' . $value;
                    }
                }
                // See comment about CASE-based updates a few lines above
                // $updates_by_filter[] = $this->getShortAlias($this->getMainObject()->getAlias()) . '.' . $attr->getDataAddress() . " = CASE " . $this->getMainObject()->getUidAttribute()->getDataAddress() . " \n" . implode($cases) . " END";
            }
        }
        
        // Execute the main query
        foreach ($updates_by_uid as $uid => $row) {
            $sql = 'UPDATE ' . $this->buildSqlFrom() . ' SET ' . implode(', ', $row) . ' WHERE ' . $this->getMainObject()->getUidAttribute()->getDataAddress() . '=' . $uid;
            $query = $data_connection->runSql($sql);
            $affected_rows += $query->countAffectedRows();
        }
        
        if (count($updates_by_filter) > 0) {
            $sql = 'UPDATE ' . $this->buildSqlFrom() . ' SET ' . implode(', ', $updates_by_filter) . $where;
            $query = $data_connection->runSql($sql);
            $affected_rows = $query->countAffectedRows();
        }
        
        // Execute Subqueries
        foreach ($this->splitByMetaObject($subqueries_qparts) as $subquery) {
            $subquery->update($data_connection);
        }
        
        return $affected_rows;
    }
    
    public function buildSqlUpdateCustomValue($statement, $table_alias, $value){
        return str_replace(array(
            '[#alias#]',
            '[#value#]'
        ), array(
            $table_alias,
            $value
        ), $statement);
    }

    /**
     * Splits the a seta of query parts of the current query into multiple separate queries, each of them containing only query
     * parts with direct attributes of one single object.
     *
     * For example, concider a query for the object ORDER with the following attributes, values, or whatever other query parts:
     * NUMBER, DATE, CUSTOMER->NAME, DELIVER_ADDRESS->STREET, DELIVERY_ADDRESS->NO. A split would give you two queries: one for
     * ORDER (with the columns NUMBER and DATE) and one for ADDRESS (with the columns STREET and NO).
     *
     * @param QueryPartAttribute[] $qparts            
     * @return AbstractSqlBuilder[]
     */
    protected function splitByMetaObject(array $qparts)
    {
        $queries = array();
        foreach ($qparts as $qpart) {
            /* @var $attr \exface\Core\CommonLogic\Model\Attribute */
            $attr = $qpart->getAttribute();
            if (! $queries[$attr->getRelationPath()->toString()]) {
                $q = clone $this;
                if ($attr->getRelationPath()->toString()) {
                    $q->setMainObject($this->getMainObject()->getRelatedObject($attr->getRelationPath()->toString()));
                    $q->setFiltersConditionGroup($this->getFilters()->getConditionGroup()->rebase($attr->getRelationPath()->toString()));
                } else {
                    $q->setFilters($this->getFilters());
                }
                $q->clearValues();
                $queries[$attr->getRelationPath()->toString()] = $q;
                unset($q);
            }
            $queries[$attr->getRelationPath()->toString()]->addQueryPart($qpart->rebase($queries[$attr->getRelationPath()->toString()], $attr->getRelationPath()->toString()));
        }
        return $queries;
    }

    /**
     * Escapes a given value in the proper way for it's data type.
     * The result can be safely used in INSERT
     * or UPDATE queries.
     * IDEA create a new qpart for input values and use it as an argument in this method. Only need one argument then.
     *
     * @param multitype $value            
     * @param AbstractDataType $data_type            
     * @param string $sql_data_type            
     * @return string
     */
    protected function prepareInputValue($value, AbstractDataType $data_type, $sql_data_type = NULL)
    {
        $value = $data_type::parse($value);
        if ($data_type->is(EXF_DATA_TYPE_STRING)) {
            $value = "'" . $this->escapeString($value) . "'";
        } elseif ($data_type->is(EXF_DATA_TYPE_BOOLEAN)) {
            $value = $value ? 1 : 0;
        } elseif ($data_type->is(EXF_DATA_TYPE_NUMBER)) {
            $value = ($value == '' ? 'NULL' : $value);
        } elseif ($data_type->is(EXF_DATA_TYPE_DATE)) {
            if (! $value) {
                $value = 'NULL';
            } else {
                $value = "'" . $this->escapeString($value) . "'";
            }
        } elseif ($data_type->is(EXF_DATA_TYPE_RELATION)) {
            if ($value == '') {
                $value = 'NULL';
            } else {
                $value = NumberDataType::validate($value) ? $value : "'" . $this->escapeString($value) . "'";
            }
        } else {
            $value = "'" . $this->escapeString($value) . "'";
        }
        return $value;
    }

    /**
     * Escapes a given string in order to use it in sql queries
     *
     * @param string $string            
     * @return string
     */
    protected function escapeString($string)
    {
        return addslashes($string);
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::delete()
     */
    function delete(AbstractDataConnector $data_connection = null)
    {
        // filters -> WHERE
        // Relations (joins) are not supported in delete clauses, so check for them first!
        if (count($this->getFilters()->getUsedRelations()) > 0) {
            throw new QueryBuilderException('Filters over attributes of related objects ("' . $attribute . '") are not supported in DELETE queries!');
        }
        $where = $this->buildSqlWhere($this->getFilters());
        $where = $where ? "\n WHERE " . $where : '';
        if (! $where) {
            throw new QueryBuilderException('Cannot delete all data from "' . $this->main_object->getAlias() . '". Forbidden operation!');
        }
        
        $sql = 'DELETE FROM ' . $this->buildSqlFrom() . $where;
        $query = $data_connection->runSql($sql);
        
        return $query->countAffectedRows();
    }

    /**
     * Creats a SELECT statement for an attribute (qpart).
     * The parameters override certain parts of the statement: $group_function( $select_from.$select_column AS $select_as ).
     * Set parameters to null to disable them. Other values (like '') do not disable them!
     *
     * TODO multiple reverse relations in line cause trouble, as the group by only groups the last of them, not the ones
     * in the middle. A possible solutions would be joining the tables starting from the last reverse relation in line back to
     * the first one.
     * Bad:
     * (SELECT
     * (SELECT SUM(POS_TRANSACTIONS.AMOUNT) AS "SALES_QTY_SUM1"
     * FROM PCD_TABLE POS_TRANSACTIONS
     * WHERE POS_TRANSACTIONS.ARTICLE_IDENT = ARTI.OID
     * ) AS "POS_TRANSACTIONS__SALES_QTY_S1"
     * FROM ARTICLE_IDENT ARTI
     * WHERE ARTI.ARTICLE_COLOR_OID = EXFCOREQ.OID
     * ) AS "ARTI__POS_TRANSACTIONS__SALES1"
     * Good:
     * (SELECT SUM(POS_TRANSACTIONS.AMOUNT) AS "SALES_QTY_SUM1"
     * FROM PCD_TABLE POS_TRANSACTIONS
     * LEFT JOIN ARTICLE_IDENT ARTI ON POS_TRANSACTIONS.ARTICLE_IDENT = ARTI.OID
     * WHERE ARTI.ARTICLE_COLOR_OID = EXFCOREQ.OID) AS "ARTI__POS_TRANSACTIONS__SALES1"
     * Another idea might be to enforce grouping after every reverse relation. Don't know, how it would look like in SQL though...
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart            
     * @param string $select_from            
     * @param string $select_column            
     * @param string $select_as
     *            set to false or '' to remove the "AS xxx" part completely
     * @param string $group_function
     *            set to false or '' to remove grouping completely
     * @return string
     */
    /**
     * Creats a SELECT statement for an attribute (qpart).
     * The parameters override certain parts of the statement: $group_function( $select_from.$select_column AS $select_as ).
     * Set parameters to null to disable them. Other values (like '') do not disable them!
     *
     * TODO multiple reverse relations in line cause trouble, as the group by only groups the last of them, not the ones
     * in the middle. A possible solutions would be joining the tables starting from the last reverse relation in line back to
     * the first one.
     * Bad:
     * (SELECT
     * (SELECT SUM(POS_TRANSACTIONS.AMOUNT) AS "SALES_QTY_SUM1"
     * FROM PCD_TABLE POS_TRANSACTIONS
     * WHERE POS_TRANSACTIONS.ARTICLE_IDENT = ARTI.OID
     * ) AS "POS_TRANSACTIONS__SALES_QTY_S1"
     * FROM ARTICLE_IDENT ARTI
     * WHERE ARTI.ARTICLE_COLOR_OID = EXFCOREQ.OID
     * ) AS "ARTI__POS_TRANSACTIONS__SALES1"
     * Good:
     * (SELECT SUM(POS_TRANSACTIONS.AMOUNT) AS "SALES_QTY_SUM1"
     * FROM PCD_TABLE POS_TRANSACTIONS
     * LEFT JOIN ARTICLE_IDENT ARTI ON POS_TRANSACTIONS.ARTICLE_IDENT = ARTI.OID
     * WHERE ARTI.ARTICLE_COLOR_OID = EXFCOREQ.OID) AS "ARTI__POS_TRANSACTIONS__SALES1"
     * Another idea might be to enforce grouping after every reverse relation. Don't know, how it would look like in SQL though...
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart            
     * @param string $select_from            
     * @param string $select_column            
     * @param string $select_as
     *            set to false or '' to remove the "AS xxx" part completely
     * @param string $group_function
     *            set to false or '' to remove grouping completely
     * @return string
     */
    protected function buildSqlSelect(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null, $select_column = null, $select_as = null, $group_function = null)
    {
        $output = '';
        $add_nvl = false;
        $attribute = $qpart->getAttribute();
        
        // skip attributes with no select (e.g. calculated from other values via formatters)
        if (! $attribute->getDataAddress())
            return;
        
        if (! $select_from) {
            // if it's a relation, we need to select from a joined table except for reverse relations
            if ($select_from = $attribute->getRelationPath()->toString()) {
                if ($rev_rel = $qpart->getFirstRelation(Relation::RELATION_TYPE_REVERSE)) {
                    // In case of reverse relations, $select_from is used to connect the subselects.
                    // Here we use the table of the last regular relation relation before the reversed one.
                    $select_from = $attribute->getRelationPath()->getSubpath(0, $attribute->getRelationPath()->getIndexOf($rev_rel))->toString();
                }
            }
            // otherwise select from the main table
            if (! $select_from) {
                $select_from = $this->getMainObject()->getAlias();
            }
            $select_from .= $this->getQueryId();
        }
        if (is_null($select_as))
            $select_as = $qpart->getAlias();
        $select_from = $this->getShortAlias($select_from);
        $select_as = $this->getShortAlias($select_as);
        $group_function = ! is_null($group_function) ? $group_function : $qpart->getAggregateFunction();
        
        // build subselects for reverse relations if the body of the select is not specified explicitly
        if (! $select_column && $qpart->getUsedRelations(Relation::RELATION_TYPE_REVERSE)) {
            $output = $this->buildSqlSelectSubselect($qpart, $select_from);
            $add_nvl = true;
        } // build grouping function if necessary
elseif ($group_function) {
            $output = $this->buildSqlGroupFunction($qpart, $select_from, $select_column, $select_as, $group_function);
            $add_nvl = true;
        } // otherwise create a regular select
else {
            if ($select_column) {
                // if the column to select is explicitly defined, just select it
                $output = $select_from . '.' . $select_column;
            } elseif ($this->checkForSqlStatement($attribute->getDataAddress())) {
                // see if the attribute is a statement. If so, just replace placeholders
                $output = '(' . str_replace(array(
                    '[#alias#]'
                ), $select_from, $attribute->getDataAddress()) . ')';
            } elseif ($custom_select = $attribute->getDataAddressProperty('SQL_SELECT')){
                // IF there is a custom SQL_SELECT statement, use it.
                $output = '(' . str_replace(array(
                    '[#alias#]'
                ), $select_from, $custom_select) . ')';
            } else {
                // otherwise get the select from the attribute
                if (! $data_address = $attribute->getDataAddressProperty('SQL_SELECT_DATA_ADDRESS')){
                    $data_address = $attribute->getDataAddress();
                }
                $output = $select_from . '.' . $data_address;
            }
        }
        
        if ($add_nvl) {
            // do some prettyfying
            // return zero for number fields if the subquery does not return anything
            if ($attribute->getDataType()->is(EXF_DATA_TYPE_NUMBER)) {
                $output = $this->buildSqlSelectNullCheck($output, 0);
            }
        }
        
        if ($select_as) {
            $output = "\n" . $output . ' AS "' . $select_as . '"';
        }
        return $output;
    }

    /**
     * Adds a wrapper to a select statement, that should take care of the returned value if the statement
     * itself returns null (like IFNULL(), NVL() or COALESCE() depending on the SQL dialect).
     *
     * @param string $select_statement            
     * @param string $value_if_null            
     * @return string
     */
    protected function buildSqlSelectNullCheck($select_statement, $value_if_null)
    {
        return 'COALESCE(' . $select_statement . ', ' . (is_numeric($value_if_null) ? $value_if_null : '"' . $value_if_null . '"') . ')';
    }

    /**
     * Builds subselects for reversed relations
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart            
     * @param string $select_from            
     * @param string $select_column            
     * @param string $select_as            
     * @param string $group_function            
     * @return string
     */
    protected function buildSqlSelectSubselect(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null)
    {
        $rev_rel = $qpart->getFirstRelation(Relation::RELATION_TYPE_REVERSE);
        if (! $rev_rel)
            return '';
        
        /*
         * if there is at least one reverse relation, we need to build a subselect. This is a bit tricky since
         * "normal" and reverse relations can be mixed in the chain of relations for a certain attribute. Imagine,
         * we would like to see the customer card number and type in a list of orders. Assuming the customer may
         * have multiple cards we get the following: ORDER->CUSTOMER<-CUSTOMER_CARD->TYPE->LABEL. Thus we need to
         * join ORDER and CUSTOMER in the main query and create a subselect for CUSTOMER_CARD joined with TYPE.
         * The subselect needs to be filtered by ORDER.CUSTOMER_ID which is the foriegn key of CUSTOMER. We will
         * reference this example in the comments below.
         */
        $rel_path = $qpart->getAttribute()->getRelationPath();
        /** @var RelationPath $reg_rel_path part of the relation part up to the first reverse relation */
        $reg_rel_path = $rel_path->getSubpath(0, $rel_path->getIndexOf($rev_rel));
        /** @var RelationPath complete path of the first reverse relation */
        $rev_rel_path = $reg_rel_path->copy()->appendRelation($rev_rel);
        
        // build a subquery
        /* @var $relq \exface\Core\QueryBuilders\AbstractSqlBuilder */
        $qb_class = get_class($this);
        // TODO Use QueryBuilderFactory here instead
        $relq = new $qb_class();
        // the query is based on the first object after the reversed relation (CUSTOMER_CARD for the above example)
        $relq->setMainObject($rev_rel->getRelatedObject());
        $relq->setQueryId($this->getNextSubqueryId());
        
        // Add the key alias relative to the first reverse relation (TYPE->LABEL for the above example)
        $relq->addAttribute(str_replace($rev_rel_path->toString() . RelationPath::RELATION_SEPARATOR, '', $qpart->getAlias()));
        
        // Set the filters of the subquery to all filters of the main query, that need to be applied to objects beyond the reverse relation.
        // In our examplte, those would be any filter on ORDER->CUSTOMER<-CUSTOMER_CARD or ORDER->CUSTOMER<-CUSTOMER_CARD->TYPE, etc. Filters
        // over ORDER oder ORDER->CUSTOMER would be applied to the base query and ar not neeede in the subquery any more.
        // If we rebase and add all filters, it will still work, but the SQL would get much more complex and surely slow with large data sets.
        // Set $remove_conditions_not_matching_the_path parameter to true, to make sure, only applicable filters will get rebased.
        $relq->setFiltersConditionGroup($this->getFilters()->getConditionGroup()->rebase($rev_rel_path->toString(), true));
        // Add a new filter to attach to the main query (WHERE CUSTOMER_CARD.CUSTOMER_ID = ORDER.CUSTOMER_ID for the above example)
        // This only makes sense, if we have a reference to the parent query (= the $select_from parameter is set)
        if ($select_from) {
            if (! $reg_rel_path->isEmpty()) {
                // attach to the related object key of the last regular relation before the reverse one
                $junction_attribute = $this->getMainObject()->getAttribute(RelationPath::relationPathAdd($reg_rel_path->toString(), $this->getMainObject()->getRelation($reg_rel_path->toString())->getRelatedObjectKeyAlias()));
                $junction = $junction_attribute->getDataAddress();
            } else { // attach to the uid of the core query if there are no regular relations preceeding the reversed one
                $junction = $this->getMainObject()->getUidAttribute()->getDataAddress();
            }
            // The filter needs to be an EQ, since we want a to compare by "=" to whatever we define without any quotes
            // Putting the value in brackets makes sure it is treated as an SQL expression and not a normal value
            $relq->addFilterFromString($rev_rel->getForeignKeyAlias(), '(' . $select_from . '.' . $junction . ')', EXF_COMPARATOR_EQUALS);
        }
        
        $output = '(' . $relq->buildSqlQuerySelect() . ')';
        
        return $output;
    }

    /**
     * Builds a group function for the SQL select statement (e.g.
     * "SUM(field)") from an ExFace aggregator
     * function. This method translates ExFace aggregators to SQL und thus will probably differ between
     * SQL dialects.
     * TODO Currently this method also parses the ExFace aggregator. This probably should be moved to the
     * \exface\Core\CommonLogic\QueryBuilder\QueryPart because it is something entirely ExFace-specific an does not depend on the data source. It
     * would also make it easier to override this method for specific sql dialects while reusing some
     * basics (like SUM or AVG) from the general sql query builder.
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart            
     * @param string $select_from            
     * @param string $select_column            
     * @param string $select_as            
     * @param string $group_function            
     * @return string
     */
    protected function buildSqlGroupFunction(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null, $select_column = null, $select_as = null, $group_function = null)
    {
        $output = '';
        $group_function = ! is_null($group_function) ? $group_function : $qpart->getAggregateFunction();
        $group_function = trim($group_function);
        $select = $this->buildSqlSelect($qpart, $select_from, $select_column, false, false);
        $args = array();
        if ($args_pos = strpos($group_function, '(')) {
            $func = substr($group_function, 0, $args_pos);
            $args = explode(',', substr($group_function, ($args_pos + 1), - 1));
        } else {
            $func = $group_function;
        }
        
        switch ($func) {
            case 'SUM':
            case 'AVG':
            case 'COUNT':
            case 'MAX':
            case 'MIN':
                $output = $func . '(' . $select . ')';
                break;
            case 'LIST':
                $output = "ListAgg(" . $select . ", " . ($args[0] ? $args[0] : "', '") . ") WITHIN GROUP (order by " . $select . ")";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            case 'LIST_DISTINCT':
                $output = "ListAggDistinct(" . $select . ")";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            case 'COUNT_DISTINCT':
                $output = "COUNT(DISTINCT " . $select . ")";
                break;
            default:
                break;
        }
        return $output;
    }

    protected function buildSqlFrom()
    {
        // here we simply have to replace the placeholders in case the from-clause ist a custom sql statement
        return str_replace('[#alias#]', $this->getMainObject()->getAlias(), $this->getMainObject()->getDataAddress()) . ' ' . $this->getShortAlias($this->getMainObject()->getAlias() . $this->getQueryId());
    }

    /**
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart            
     * @param string $left_table_alias            
     * @return array [ relation_path_relative_to_main_object => join_string ]
     */
    protected function buildSqlJoins(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $left_table_alias = '')
    {
        $joins = array();
        
        if ($qpart instanceof QueryPartFilterGroup) {
            // This extra if for the filter groups is a somewhat strange solutions for reverse relation filters being ignored within groups. It seems, that
            // if you use $qpart->getUsedRelations() on a FilterGroup and just continue with the "else" part of this if, reverse relations are being ignored.
            // The problem is, that the special treatment for attributes of the main object and an explicit left_table_alias should be applied to filter group
            // at some point, but it is not, because it is not possible to determine, what object the filter group belongs to (it might have attributes from
            // many object). I don not understand, however, why that special treatment seems to be important for reverse relations... In any case, this recursion
            // does the job.
            foreach ($qpart->getFiltersAndNestedGroups() as $f) {
                $joins = array_merge($joins, $this->buildSqlJoins($f));
            }
        } else {
            $rels = $qpart->getUsedRelations();
            
            if (count($rels) === 0 && $qpart->getAttribute()->getObjectId() == $this->getMainObject()->getId() && $left_table_alias) {
                // Special treatment if we are joining attributes of the main object to an explicitly specified table alias.
                // This is necessary when putting some special attributes of the main object (i.e. those with custom
                // sql) into the enrichment query. In this case, we need to join the table of the main object to
                // the core query again, after pagination, so possible back references within the custom select can
                // still be resolved.
                $right_table_alias = $this->getShortAlias($this->getMainObject()->getAlias() . $this->getQueryId());
                $joins[$right_table_alias] = "\n LEFT JOIN " . str_replace('[#alias#]', $right_table_alias, $this->getMainObject()->getDataAddress()) . ' ' . $right_table_alias . ' ON ' . $left_table_alias . '.' . $this->getMainObject()->getUidAlias() . ' = ' . $right_table_alias . '.' . $this->getMainObject()->getUidAlias();
            } else {
                // In most cases we will build joins for attributes of related objects.
                $left_table_alias = $this->getShortAlias(($left_table_alias ? $left_table_alias : $this->getMainObject()->getAlias()) . $this->getQueryId());
                $left_obj = $this->getMainObject();
                foreach ($rels as $alias => $rel) {
                    if ($rel->isForwardRelation()) {
                        $right_table_alias = $this->getShortAlias($alias . $this->getQueryId());
                        $right_obj = $this->getMainObject()->getRelatedObject($alias);
                        // generate the join sql
                        $left_join_on = $this->buildSqlJoinSide($left_obj->getAttribute($rel->getForeignKeyAlias())->getDataAddress(), $left_table_alias);
                        $right_join_on = $this->buildSqlJoinSide($rel->getRelatedObjectKeyAttribute()->getDataAddress(), $right_table_alias);
                        $joins[$right_table_alias] = "\n " . $rel->getJoinType() . ' JOIN ' . str_replace('[#alias#]', $right_table_alias, $right_obj->getDataAddress()) . ' ' . $right_table_alias . ' ON ' . $left_join_on . ' = ' . $right_join_on;
                        // continue with the related object
                        $left_table_alias = $right_table_alias;
                        $left_obj = $right_obj;
                    } elseif ($rel->getType() == '11') {
                        // TODO 1-to-1 relations
                    } else {
                        // stop joining as all the following joins will be add in subselects of the enrichment select
                        break;
                    }
                }
            }
        }
        return $joins;
    }

    protected function buildSqlJoinSide($data_address, $table_alias)
    {
        $join_side = $data_address;
        if ($this->checkForSqlStatement($join_side)) {
            $join_side = str_replace('[#alias#]', $table_alias, $join_side);
        } else {
            $join_side = $table_alias . '.' . $join_side;
        }
        return $join_side;
    }

    /**
     * Builds the SQL HAVING clause based on the filter group of this query. 
     * This is similar to buildSqlWhereCondition but it takes care of filters 
     * with aggregators.
     * 
     * Returns an empty string if no HAVING clause is needed for this query.
     * 
     * @param QueryPartFilterGroup $qpart            
     * @param string $rely_on_joins            
     * @return string
     * 
     * @see BuildSqlWhere
     */
    protected function buildSqlHaving(QueryPartFilterGroup $qpart, $rely_on_joins = true)
    {
        $having = '';
        $op = $this->buildSqlLogicalOperator($qpart->getOperator());
        
        foreach ($qpart->getFilters() as $qpart_fltr) {
            if ($fltr_string = $this->buildSqlHavingCondition($qpart_fltr, $rely_on_joins)) {
                $having .= "\n " . ($having ? $op . " " : '') . $fltr_string;
            }
        }
        
        foreach ($qpart->getNestedGroups() as $qpart_grp) {
            if ($grp_string = $this->buildSqlHaving($qpart_grp, $rely_on_joins)) {
                $having .= "\n " . ($having ? $op . " " : '') . "(" . $grp_string . ")";
            }
        }
        
        return $having;
    }

    /**
     * Builds the SQL for a condition within the HAVING clause. This is similar
     * to buildSqlWhereCondition but it takes care of filters with aggregators.
     * 
     * @param QueryPartFilter $qpart            
     * @param boolean $rely_on_joins            
     * @return string
     * 
     * @see buildSqlWhereCondition()
     */
    protected function buildSqlHavingCondition(QueryPartFilter $qpart, $rely_on_joins = true)
    {
        // The query part belongs in the WHERE-clause if it does not have an aggregator
        if (! $this->checkFilterBelongsInHavingClause($qpart, $rely_on_joins)) {
            return '';
        }
        
        $val = $qpart->getCompareValue();
        $attr = $qpart->getAttribute();
        $comp = $qpart->getComparator();
        $delimiter = $qpart->getValueListDelimiter();
        
        $select = $this->buildSqlGroupFunction($qpart);
        $where = $qpart->getDataAddressProperty('WHERE');
        $object_alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() : $this->getMainObject()->getAlias());
        
        // doublecheck that the attribut is known
        if (! ($select || $where) || $val === '') {
            throw new QueryBuilderException('Illegal filter on object "' . $this->getMainObject()->getAlias() . ', expression "' . $qpart->getAlias() . '", Value: "' . $val . '".');
            return false;
        }
        
        // build the having
        if ($where) {
            // check if it has an explicit where clause. If not try to filter based on the select clause
            $output = str_replace(array(
                '[#alias#]',
                '[#value#]'
            ), array(
                $this->getShortAlias($object_alias . $this->getQueryId()),
                $val
            ), $where);
        } else {
            // Determine, what we are going to compare to the value: a subquery or a column
            if ($this->checkForSqlStatement($attr->getDataAddress())) {
                $subj = str_replace(array(
                    '[#alias#]'
                ), array(
                    $this->getShortAlias($object_alias . $this->getQueryId()) 
                ), $select);
            } else {
                $subj = $select;
            }
            // Do the actual comparing
            $output = $this->buildSqlWhereComparator($subj, $comp, $val, $attr->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'), $delimiter);
        }
        
        return $output;
    }

    /**
     * Builds a where statement for a group of filters, concatennating the conditions with the goups logical operator
     * (e.g.
     * " condition1 AND condition 2 AND (condition3 OR condition4) ")
     *
     * @param QueryPartFilterGroup $qpart            
     * @return string
     */
    protected function buildSqlWhere(QueryPartFilterGroup $qpart, $rely_on_joins = true)
    {
        $where = '';
        $op = $this->buildSqlLogicalOperator($qpart->getOperator());
        
        foreach ($qpart->getFilters() as $qpart_fltr) {
            if ($fltr_string = $this->buildSqlWhereCondition($qpart_fltr, $rely_on_joins)) {
                $where .= "\n " . ($where ? $op . " " : '') . $fltr_string;
            }
        }
        
        foreach ($qpart->getNestedGroups() as $qpart_grp) {
            if ($grp_string = $this->buildSqlWhere($qpart_grp, $rely_on_joins)) {
                $where .= "\n " . ($where ? $op . " " : '') . "(" . $grp_string . ")";
            }
        }
        
        return $where;
    }

    /**
     * Translates the given condition group operator into it's SQL version: EXF_LOGICAL_AND => AND, etc.
     *
     * @param string $operator            
     * @return string
     */
    protected function buildSqlLogicalOperator($operator)
    {
        switch ($operator) {
            case EXF_LOGICAL_AND:
                $op = 'AND';
                break;
            case EXF_LOGICAL_OR:
                $op = 'OR';
                break;
            case EXF_LOGICAL_XOR:
                $op = 'XOR';
                break;
            case EXF_LOGICAL_NOT:
                $op = 'NOT';
                break;
        }
        return $op;
    }

    /**
     * Returns TRUE if the given filter query part belongs in the HAVING clause 
     * of the current query rather than the WHERE clause.
     *
     * This is the case if the query part has an aggregator and it is not 
     * related via reverse relation. In the latter case, a subquery will be 
     * added to the where clause which - in turn - will normally contain the
     * HAVING clause
     *
     * @param QueryPartFilter $qpart            
     * @param boolean $rely_on_joins            
     * @return boolean
     */
    protected function checkFilterBelongsInHavingClause(QueryPartFilter $qpart, $rely_on_joins = true)
    {
        return $qpart->getAggregateFunction() && ! $qpart->getFirstRelation(Relation::RELATION_TYPE_REVERSE) ? true : false;
    }

    /**
     * Builds a single filter condition for the where clause (e.g.
     * " table.column LIKE '%string%' ")
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter $qpart            
     * @return boolean|string
     */
    protected function buildSqlWhereCondition(QueryPartFilter $qpart, $rely_on_joins = true)
    {
        // The given
        if ($this->checkFilterBelongsInHavingClause($qpart, $rely_on_joins)) {
            return '';
        }
        
        $val = $qpart->getCompareValue();
        $attr = $qpart->getAttribute();
        $comp = $qpart->getComparator();
        $delimiter = $qpart->getValueListDelimiter();
        
        if ($attr->isRelation() && $comp != EXF_COMPARATOR_IN) {
            // always use the equals comparator for foreign keys! It's faster!
            $comp = EXF_COMPARATOR_EQUALS;
        } elseif ($attr->isExactly($this->getMainObject()->getUidAttribute()) && $comp != EXF_COMPARATOR_IN && ! $qpart->getAggregateFunction()) {
            $comp = EXF_COMPARATOR_EQUALS;
        } elseif ($attr->getDataType()->is(EXF_DATA_TYPE_NUMBER) && $comp == EXF_COMPARATOR_IS && is_numeric($val)) {
            // also use equals for the NUMBER data type, but make sure, the value to compare to is really a number (otherwise the query will fail!)
            $comp = EXF_COMPARATOR_EQUALS;
        } elseif ($attr->getDataType()->is(EXF_DATA_TYPE_BOOLEAN) && $comp == EXF_COMPARATOR_IS) {
            // also use equals for the BOOLEAN data type
            $comp = EXF_COMPARATOR_EQUALS;
        } elseif ($attr->getDataType()->is(EXF_DATA_TYPE_DATE) && $comp == EXF_COMPARATOR_IS) {
            // also use equals for the NUMBER data type, but make sure, the value to compare to is really a number (otherwise the query will fail!)
            $comp = EXF_COMPARATOR_EQUALS;
        }
        
        $select = $attr->getDataAddress();
        $where = $qpart->getDataAddressProperty('WHERE');
        $where_data_address = $qpart->getDataAddressProperty('SQL_WHERE_DATA_ADDRESS');
        $object_alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() : $this->getMainObject()->getAlias());
        
        // doublecheck that the attribute is known
        if (! ($select || $where) || $val === '') {
            throw new QueryBuilderException('Illegal filter on object "' . $this->getMainObject()->getAlias() . ', expression "' . $qpart->getAlias() . '", Value: "' . $val . '".');
            return false;
        }
        
        if ($qpart->getFirstRelation(Relation::RELATION_TYPE_REVERSE) || ($rely_on_joins == false && count($qpart->getUsedRelations()) > 0)) {
            // Use subqueries for attributes with reverse relations and in case we know, tha main query will not have any joins (e.g. UPDATE queries)
            $output = $this->buildSqlWhereSubquery($qpart, $rely_on_joins);
        } else {
            // build the where
            if ($where) {
                // check if it has an explicit where clause. If not try to filter based on the select clause
                $output = str_replace(array(
                    '[#alias#]',
                    '[#value#]'
                ), array(
                    $this->getShortAlias($object_alias . $this->getQueryId()),
                    $val
                ), $where);
                return $output;
            } elseif($where_data_address) {
                $subj = str_replace(array(
                    '[#alias#]'
                ), array(
                    $this->getShortAlias($object_alias . $this->getQueryId())
                ), $where_data_address);
            } else {
                // Determine, what we are going to compare to the value: a subquery or a column
                if ($this->checkForSqlStatement($attr->getDataAddress())) {
                    $subj = str_replace(array(
                        '[#alias#]'
                    ), array(
                        $this->getShortAlias($object_alias . $this->getQueryId())
                    ), $select);
                } else {
                    $subj = $this->getShortAlias($object_alias . $this->getQueryId()) . '.' . $select;
                }
            }
            // Do the actual comparing
            $output = $this->buildSqlWhereComparator($subj, $comp, $val, $attr->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'), $delimiter);
        }
        return $output;
    }
    
    /**
     * 
     * @param string $subject column name or subselect
     * @param string $comparator one of the EXF_COMPARATOR_xxx constants
     * @param string $value value or SQL expression to compare to
     * @param AbstractDataType $data_type
     * @param string $sql_data_type value of SQL_DATA_TYPE data source setting
     * @param string $value_list_delimiter delimiter used to separate concatenated lists of values
     * @return string
     */
    protected function buildSqlWhereComparator($subject, $comparator, $value, AbstractDataType $data_type, $sql_data_type = NULL, $value_list_delimiter = EXF_LIST_SEPARATOR)
    {
        // Check if the value is of valid type.
        try {
            // Pay attention to comparators expecting concatennated values (like IN) - the concatennated value will not validate against
            // the data type, but the separated parts should
            if ($comparator != EXF_COMPARATOR_IN && $comparator != EXF_COMPARATOR_NOT_IN) {
                $value = $data_type::parse($value);
            } else {
                $values = explode($value_list_delimiter, $value);
                $value = '';
                // $values = explode($value_list_delimiter, trim($value, $value_list_delimiter));
                foreach ($values as $nr => $val) {
                    // If there is an empty string among the values, this means that the value may be empty (NULL). NULL is not a valid
                    // value for an IN-statement, though, so we need to append an "OR IS NULL" here.
                    if ($val === '') {
                        unset($values[$nr]);
                        $value = $subject . ($comparator == EXF_COMPARATOR_IN ? ' IS NULL' : ' IS NOT NULL');
                        continue;
                    }
                    // Normalize non-empty values
                    $values[$nr] = $data_type::parse($val);
                }
                $value = '(' . implode(',', $values) . ')' . ($value ? ' OR ' . $value : '');
            }
        } catch (DataTypeValidationError $e) {
            // If the data type is incompatible with the value, return a WHERE clause, that is always false.
            // A comparison of a date field with a string or a number field with
            // a string simply cannot result in TRUE.
            return '1 = 0 /* ' . $subject . ' cannot pass comparison to "' . $value . '" via comparator "' . $comparator . '": wrong data type! */';
        }
        
        // If everything is OK, build the SQL
        switch ($comparator) {
            case EXF_COMPARATOR_IN:
                $output = "(" . $subject . " IN " . $value . ")";
                break; // The braces are needed if there is a OR IS NULL addition (see above)
            case EXF_COMPARATOR_NOT_IN:
                $output = "(" . $subject . " NOT IN " . $value . ")";
                break; // The braces are needed if there is a OR IS NULL addition (see above)
            case EXF_COMPARATOR_EQUALS:
                $output = $subject . " = " . $this->prepareWhereValue($value, $data_type, $sql_data_type);
                break;
            case EXF_COMPARATOR_EQUALS_NOT:
                $output = $subject . " != " . $this->prepareWhereValue($value, $data_type, $sql_data_type);
                break;
            case EXF_COMPARATOR_GREATER_THAN:
            case EXF_COMPARATOR_LESS_THAN:
            case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS:
            case EXF_COMPARATOR_LESS_THAN_OR_EQUALS:
                $output = $subject . " " . $comparator . " " . $this->prepareWhereValue($value, $data_type, $sql_data_type);
                break;
            case EXF_COMPARATOR_IS_NOT:
                $output = 'UPPER(' . $subject . ") NOT LIKE '%" . $this->prepareWhereValue(strtoupper($value), $data_type) . "%'";
            case EXF_COMPARATOR_IS:
            default:
                $output = 'UPPER(' . $subject . ") LIKE '%" . $this->prepareWhereValue(strtoupper($value), $data_type) . "%'";
        }
        return $output;
    }

    protected function prepareWhereValue($value, AbstractDataType $data_type, $sql_data_type = NULL)
    {
        // IDEA some data type specific procession here
        if ($data_type->is(EXF_DATA_TYPE_BOOLEAN)) {
            $output = $value ? 1 : 0;
        } else {
            $output = $this->escapeString($value);
        }
        return $output;
    }

    /**
     * Builds a WHERE clause with a subquery (e.g. "column IN ( SELECT ... )" ). 
     * 
     * This is mainly used to handle filters over reversed relations, but also
     * for filters on joined columns in UPDATE queries, where the main query 
     * does not support joining. The optional parameter $rely_on_joins controls 
     * whether the method can rely on the main query have all neccessary joins.
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter $qpart            
     * @param boolean $rely_on_joins            
     */
    protected function buildSqlWhereSubquery(QueryPartFilter $qpart, $rely_on_joins = true)
    {
        /* @var $start_rel \exface\Core\CommonLogic\Model\relation */
        // First of all, see if we can rely on all joins being performed in the main query.
        // This is implicitly also the case, if there are no joins needed (= the data in the main query will be sufficient in any case)
        if ($rely_on_joins || count($qpart->getUsedRelations()) === 0) {
            // If so, just need to include those relations in the subquery, which follow a reverse relation
            $start_rel = $qpart->getFirstRelation(Relation::RELATION_TYPE_REVERSE);
        } else {
            // Otherwise, all relations (starting from the first one) must be put into the subquery, because there are no joins in the main one
            $start_rel = $qpart->getFirstRelation();
        }
        
        if ($start_rel) {
            $qpart_rel_path = $qpart->getAttribute()->getRelationPath();
            /** @var RelationPath $prefix_rel_path part of the relation part up to the first reverse relation */
            $prefix_rel_path = $qpart_rel_path->getSubpath(0, $qpart_rel_path->getIndexOf($start_rel));
            
            // build a subquery
            /* @var $relq \exface\Core\QueryBuilders\AbstractSqlBuilder */
            $qb_class = get_class($this);
            $relq = new $qb_class();
            $relq->setMainObject($start_rel->getRelatedObject());
            $relq->setQueryId($this->getNextSubqueryId());
            if ($start_rel->isReverseRelation()) {
                // If we are dealing with a reverse relation, build a subquery to select foreign keys from rows of the joined tables,
                // that match the given filter
                $rel_filter = $qpart->getAttribute()->rebase($qpart_rel_path->getSubpath($qpart_rel_path->getIndexOf($start_rel) + 1))->getAliasWithRelationPath();
                // Remember to keep the aggregator of the attribute filtered over. Since we are interested in a list of keys, the
                // subquery should GROUP BY these kees.
                if ($qpart->getAggregateFunction()) {
                    // IDEA HAVING-subqueries can be very slow. Perhaps we can optimize the subquery a litte in certain cases:
                    // e.g. if we are filtering over a SUM of natural numbers with "> 0", we could simply add a "> 0" filter 
                    // without any aggregation and it should yield the same results
                    $rel_filter .= DataAggregator::AGGREGATION_SEPARATOR . $qpart->getAggregateFunction();
                    $relq->addAggregation($start_rel->getForeignKeyAlias());
                }
                $relq->addAttribute($start_rel->getForeignKeyAlias());
                // Add the filter relative to the first reverse relation with the same $value and $comparator
                $relq->addFilterFromString($rel_filter, $qpart->getCompareValue(), $qpart->getComparator());
                // FIXME add support for related_object_special_key_alias
                if (! $prefix_rel_path->isEmpty()) {
                    $prefix_rel_qpart = new QueryPartSelect(RelationPath::relationPathAdd($prefix_rel_path->toString(), $this->getMainObject()->getRelatedObject($prefix_rel_path->toString())->getUidAlias()), $this);
                    $junction = $this->buildSqlSelect($prefix_rel_qpart, null, null, '');
                } else {
                    $junction = $this->getShortAlias($this->getMainObject()->getAlias() . $this->getQueryId()) . '.' . $this->getMainObject()->getUidAttribute()->getDataAddress();
                }
            } else {
                // If we are dealing with a regular relation, build a subquery to select primary keys from joined tables and match them to the foreign key of the main table
                $relq->addFilter($qpart->rebase($relq, $start_rel->getAlias()));
                $relq->addAttribute($start_rel->getRelatedObjectKeyAlias());
                $junction_qpart = new \exface\Core\CommonLogic\QueryBuilder\QueryPartSelect($start_rel->getForeignKeyAlias(), $this);
                $junction = $this->buildSqlSelect($junction_qpart, null, null, '');
            }
            
            $output = $junction . ' IN (' . $relq->buildSqlQuerySelect() . ')';
        }
        
        return $output;
    }

    /**
     * Builds the contents of an ORDER BY statement for one column (e.g.
     * "ATTRIBUTE_ALIAS DESC" to sort via the column ALIAS of the table 
     * ATTRIBUTE). The result does not contain the words "ORDER BY", the 
     * results of multiple calls to this method with different attributes can 
     * be concatennated into a comple ORDER BY clause.
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPartSorter $qpart            
     * @return string
     */
    protected function buildSqlOrderBy(\exface\Core\CommonLogic\QueryBuilder\QueryPartSorter $qpart)
    {
        if ($qpart->getDataAddressProperty("ORDER_BY")) {
            $output = $this->getShortAlias($this->getMainObject()->getAlias()) . '.' . $qpart->getDataAddressProperty("ORDER_BY");
        } else {
            $output = $this->getShortAlias($qpart->getAlias());
        }
        $output .= ' ' . $qpart->getOrder();
        return $output;
    }

    /**
     * Builds the contents of an GROUP BY statement for one column (e.g.
     * "ATTRIBUTE.ALIAS" to group by the
     * the column ALIAS of the table ATTRIBUTE). The result does not contain the words "GROUP BY", thus
     * the results of multiple calls to this method with different attributes can be concatennated into
     * a comple GROUP BY clause.
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPartSorter $qpart            
     * @param string $select_from            
     * @return string
     */
    protected function buildSqlGroupBy(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null)
    {
        $output = '';
        if ($this->checkForSqlStatement($qpart->getAttribute()->getDataAddress())) {
            // Seems like SQL statements are not supported in the GROUP BY clause in general
            throw new QueryBuilderException('Cannot use the attribute "' . $qpart->getAttribute()->getAliasWithRelationPath() . '" for aggregation in an SQL data source, because it\'s data address is defined via custom SQL statement');
        } else {
            // If it's not a custom SQL statement, it must be a column, so we need to prefix it with the table alias
            if (is_null($select_from)) {
                $select_from = $qpart->getAttribute()->getRelationPath()->toString() ? $qpart->getAttribute()->getRelationPath()->toString() : $this->getMainObject()->getAlias();
            }
            $output = ($select_from ? $this->getShortAlias($select_from . $this->getQueryId()) . '.' : '') . $qpart->getAttribute()->getDataAddress();
        }
        return $output;
    }

    /**
     * Shortens an alias (or any string) to $short_alias_max_length by cutting off the rest and appending
     * a unique id.
     * Also replaces forbidden words and characters ($short_alias_forbidden and $short_alias_remove_chars).
     * The result can be translated back to the original via get_full_alias($short_alias)
     * Every SQL-alias (like "SELECT xxx AS alias" or "SELECT * FROM table1 alias") should be shortened
     * because most SQL dialects only allow a limited number of characters in an alias (this number should
     * be set in $short_alias_max_length).
     *
     * @param string $full_alias            
     * @return string
     */
    protected function getShortAlias($full_alias)
    {
        if (isset($this->short_aliases[$full_alias])) {
            $short_alias = $this->short_aliases[$full_alias];
        } elseif (strlen($full_alias) <= $this->short_alias_max_length && $this->getCleanAlias($full_alias) == $full_alias && ! in_array($full_alias, $this->short_alias_forbidden)) {
            $short_alias = $full_alias;
        } else {
            $this->short_alias_index ++;
            $short_alias = $this->short_alias_prefix . str_pad($this->short_alias_index, 3, '0', STR_PAD_LEFT) . $this->short_alias_replacer . substr($this->getCleanAlias($full_alias), - 1 * ($this->short_alias_max_length - 3 - 1 - 1));
            $this->short_aliases[$full_alias] = $short_alias;
        }
        
        return $short_alias;
    }

    protected function getCleanAlias($alias)
    {
        $output = '';
        $output = str_replace($this->short_alias_remove_chars, '_', $alias);
        return $output;
    }

    protected function getFullAlias($short_alias)
    {
        $full_alias = array_search($short_alias, $this->short_aliases);
        if ($full_alias === false) {
            $full_alias = $short_alias;
        }
        return $full_alias;
    }

    /**
     * Checks, if the given string is complex SQL-statement (in contrast to simple column references).
     * It is important to know this, because you cannot write to statements etc.
     *
     * @param string $string            
     * @return boolean
     */
    protected function checkForSqlStatement($string)
    {
        if (strpos($string, '(') !== false && strpos($string, ')') !== false)
            return true;
        return false;
    }

    protected function addBinaryColumn($full_alias)
    {
        $this->binary_columns[] = $full_alias;
        return $this;
    }

    protected function getBinaryColumns()
    {
        return $this->binary_columns;
    }

    protected function decodeBinary($value)
    {
        $hex_value = bin2hex($value);
        return ($hex_value ? '0x' : '') . $hex_value;
    }

    /**
     * Returns an array with attributes to be joined over reverse relations (similarly to get_attributes(), which returns all attributes)
     *
     * @return QueryPartAttribute[]
     */
    protected function getAttributesWithReverseRelations()
    {
        $result = array();
        foreach ($this->getAttributes() as $alias => $qpart) {
            if ($qpart->getUsedRelations(Relation::RELATION_TYPE_REVERSE)) {
                $result[$alias] = $qpart;
            }
        }
        return $result;
    }

    public function getQueryId()
    {
        return $this->query_id;
    }

    public function setQueryId($value)
    {
        $this->query_id = $value;
        return $this;
    }

    protected function getNextSubqueryId()
    {
        return ++ $this->subquery_counter;
    }

    /**
     * Appends a custom where statement pattern to the given original where statement.
     * Replaces the [#alias#] placeholder with the $table_alias if given or the main table alias otherwise
     *
     * @param string $original_where_statement            
     * @param string $custom_statement            
     * @param string $table_alias            
     * @param string $operator            
     * @return string
     */
    protected function appendCustomWhere($original_where_statement, $custom_statement, $table_alias = null, $operator = 'AND')
    {
        return $original_where_statement . ($original_where_statement ? ' ' . $operator . ' ' : '') . str_replace('[#alias#]', ($table_alias ? $table_alias : $this->getShortAlias($this->getMainObject()->getAlias())), $custom_statement);
    }
}
?>