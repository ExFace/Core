<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataConnectors\AbstractSqlConnector;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSelect;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\TimeDataType;

/**
 * A query builder for generic SQL syntax.
 * 
 * ## Data addresses
 * 
 * The data address of an object stored in an SQL database can be a table name,
 * or any SQL usable within the FROM clause. Placeholders for filters can
 * be used as usual (e.g. `[#my_attribute_alias#]` for the value of a filter on
 * the attribute my_attribute_alias of the current object - making it a
 * mandatory filter).
 * 
 * The data address of an attribute stored in an SQL database can be a column
 * name or any SQL usable in the SELECT clause. Custom SQL must be enclosed
 * in regular braces `()` to ensure it is correctly distinguished from column
 * names. 
 * 
 * Placeholders can be used within these custom data addresses. On object level
 * the [#~alias#] placehloder will be replaced by the alias of the current object. 
 * This is especially usefull to prevent table alias collisions in custom 
 * subselects:
 * 
 * `(SELECT mt_[#~alias#].my_column FROM my_table mt_[#~alias#] WHERE ... )`
 * 
 * This way you can control which uses of my_table are unique within the
 * generated SQL.
 * 
 * On attribute level any other attribute alias can be used as placeholder
 * additionally to `[#~alias#]`. Thus, attribute addresses can be reused. This
 * is handy if an attribute builds upon other attributes. E.g. a precentage
 * would be an attribute being calculated from two other attributes. This can
 * easily be done via attribute placeholders in it's data address: 
 * 
 * `([#RELATION_TO_OBJECT1__ATTRIBUTE1#]/[#RELATION_TO_OBJECT2__ATTRIBUTE2#])`
 * 
 * You can even use relation paths here! It will even work if the placeholders
 * point to attributes, that are based on custom SQL statements themselves.
 * Just keep in mind, that these expressions may easily become complex and
 * kill query performance if used uncarefully.
 * 
 * ## Data source options
 * 
 * ### On object level
 *  
 * - `SQL_SELECT_WHERE` - custom where statement automatically appended to 
 * direct selects for this object (not if the object's table is joined!). 
 * Usefull for generic tables, where different meta objects are stored and 
 * distinguished by specific keys in a special column. The value of 
 * `SQL_SELECT_WHERE` should contain the `[#~alias#]` placeholder: e.g. 
 * `[#~alias#].mycolumn = 'myvalue'`.
 * 
 * ### On attribute level
 * 
 * - `SQL_DATA_TYPE` - tells the query builder what data type the column has.
 * This is only needed for complex types that require conversion: e.g. binary,
 * LOB, etc. Refer to the description of the specific query builder for concrete
 * usage instructions.
 * 
 * - `SQL_SELECT` - custom SQL SELECT statement. It replaces the entire select
 * generator and will be used as-is except for replacing placeholders. The
 * placeholder `[#~alias#]` is supported as well as placeholders for other attributes. 
 * This is usefull to write wrappers for columns (e.g. `NVL([#~value#].MY_COLUMN, 0)`. 
 * If the wrapper is placed here, the data address would remain writable, while 
 * replacing the column name with a custom SQL statement in the data address itself, 
 * would cause an SQL error when writing to it (unless `SQL_UPDATE` and `SQL_INSERT`
 * are used, of course). Note, that since this is a complete replacement, the
 * table to select from must be specified manually or via [#~alias#] placeholder.
 * 
 * - `SQL_SELECT_DATA_ADDRESS` - replaces the data address for SELECT queries.
 * In contrast to SQL_SELECT, this property will be processed by the generator
 * just like a data address would be (including all placeholders). In particular,
 * the table alias will be generated automatically, while in SQL_SELECT it
 * must be defined by the user.
 * 
 * - `SQL_JOIN_ON` - replaces the ON-part for JOINs generated from this attribute.
 * This only works for attributes, that represent a forward (n-1) relation! The
 * option only supports these static placeholders: `[#~left_alias#]` and 
 * `[#~right_alias#]` (will be replaced by the aliases of the left and right 
 * tables in the JOIN accordingly). Use this option to JOIN on multiple columns
 * like `[#~left_alias#].col1 = [#~right_alias#].col3 AND [#~left_alias#].col2 
 * = [#~right_alias#].col4` or introduce other conditions like `[#~left_alias#].col1 
 * = [#~right_alias#].col2 AND [#~right_alias#].status > 0`.
 * 
 * - `SQL_INSERT` - custom SQL INSERT statement used instead of the generator.
 * The placeholders [#~alias#] and [#~value#] are supported in addition to 
 * attribute placeholders. This is usefull to write wrappers for values 
 * (e.g. `to_clob('[#~value#]')` to save a string value to an Oracle CLOB column) 
 * or generators (e.g. you could use `UUID()` in MySQL to have a column always created 
 * with a UUID). If you need to use a generator only if no value is given explicitly, 
 * use something like this: IF([#~value#]!='', [#~value#], UUID()).
 * 
 * - `SQL_UPDATE` - custom SQL for UPDATE statement. It replaces the generator
 * completely and must include the data address and the value. In contrast to
 * this, using `SQL_UPDATE_DATA_ADDRESS` will only replace the data address, while
 * the value will be generated automatically. `SQL_UPDATE` supports the placeholders
 * [#~alias#] and [#~value#] in addition to placeholders for other attributes.
 * The `SQL_UPDATE` property is usefull to write wrappers for values (e.g. 
 * `to_clob('[#~value#]')` to save a string value to an Oracle CLOB column) or 
 * generators (e.g. you could use `NOW()` in MySQL to have a column always updated 
 * with the current date). If you need to use a generator only if no value is given 
 * explicitly, use something like this: `IF([#~value#]!='', [#~value#], UUID())`.
 * 
 * - `SQL_UPDATE_DATA_ADDRESS` - replaces the data address for UPDATE queries.
 * In contrast to `SQL_UPDATE`, the value will be added automatically via generator.
 * `SQL_UPDATE_DATA_ADDRESS` supports the placeholder [#~alias#] only!
 * 
 * - `SQL_WHERE` - an entire custom WHERE clause with place with static placeholders
 * `[#~alias#]` and `[#~value#]`. It is particularly usefull for attribute 
 * with custom SQL in the data address, that you do not want to calculate within the
 * WHERE clause: e.g. if you have an attribute, which concatenates `col1` and `col2`
 * via SQL, you could use the following `SQL_WHERE`: `([#~alias#].col1 LIKE '[#~value#]%' 
 * OR [#~alias#].col2 LIKE '[#~value#]%')`. However, this property has a major drawback:
 * the comparator is being hardcoded. Use `SQL_WHERE_DATA_ADDRESS` instead, unless you
 * really require multiple columns.
 * 
 * - `SQL_WHERE_DATA_ADDRESS` - replaces the data address in the WHERE clause.
 * The comparator and the value will added automatically be the generator. 
 * Supports the [#~alias#] placeholder in addition to placeholders for other
 * attributes. This is usefull to write wrappers to be used in filters: e.g.
 * `NVL([#~alias#].MY_COLUMN, 10)` to change comparing behavior of NULL values.
 * 
 * - `SQL_ORDER_BY` - a custom ORDER BY clause. This option currently does not
 * support any placeholders!
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractSqlBuilder extends AbstractQueryBuilder
{
    // Config
    private $reserved_words = array(
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
    
    // Aliases
    private $short_alias_remove_chars = array(
        '.',
        '>',
        '<',
        '-',
        '(',
        ')',
        ':',
        ' ',
        '='
    );
    
    private $short_alias_replacer = '_';
    
    private $short_alias_prefix = 'S';

    private $short_aliases = array();

    private $short_alias_index = 0;

    // Runtime vars
    private $select_distinct = false;

    private $binary_columns = array();

    private $query_id = null;

    private $subquery_counter = 0;
    
    private $customFilterSqlPredicates = [];

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
    
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $query = $this->buildSqlQuerySelect();
        $q = new SqlDataQuery();
        $q->setSql($query);
        // first do the main query
        $qr = $data_connection->query($q);
        $rows = $this->getReadResultRows($qr);
        // If the query already includes a total row counter, use it!
        $result_total_count = $qr->getResultRowCounter();
        $qr->freeResult();
        
        // then do the totals query if needed
        $result_totals = [];
        if ($this->hasTotals() === true) {
            $totals_query = $this->buildSqlQueryTotals();
            $qrt = $data_connection->runSql($totals_query);
            if ($totals = $qrt->getResultArray()) {
                // the total number of rows is treated differently, than the other totals.
                $result_total_count = $result_total_count ?? $totals[0]['EXFCNT'];
                // now save the custom totals.
                foreach ($this->getTotals() as $qpart) {
                    $result_totals[$qpart->getRow()][$qpart->getColumnKey()] = $totals[0][$this->getShortAlias($qpart->getColumnKey())];
                }
            }
            $qrt->freeResult();
        }
        
        $rowCount = count($rows);
        $hasMoreRows = ($this->getLimit() > 0 && $rowCount > $this->getLimit());
        if ($hasMoreRows === true) {
            $affectedCounter = $this->getLimit();
            array_pop($rows);
        } else {
            $affectedCounter = $rowCount;
        }
        
        if ($result_total_count === null && $hasMoreRows === false) {
            $result_total_count = $rowCount + $this->getOffset();
        }
        
        return new DataQueryResultData($rows, $affectedCounter, $hasMoreRows, $result_total_count, $result_totals);
    }
    
    /**
     * Transforms the query result into rows of the future data sheet.
     * 
     * Override this method if you need special treatment for data types, 
     * value decoding, etc.
     * 
     * @param SqlDataQuery $query
     * @return array
     */
    protected function getReadResultRows(SqlDataQuery $query) : array
    {
        if ($rows = $query->getResultArray()) {
            foreach ($this->getBinaryColumns() as $full_alias) {
                $short_alias = $this->getShortAlias($full_alias);
                foreach ($rows as $nr => $row) {
                    $rows[$nr][$full_alias] = $this->decodeBinary($row[$short_alias]);
                }
            }
            // TODO filter away the EXFRN column!
            foreach ($this->short_aliases as $short_alias) {
                $full_alias = $this->getFullAlias($short_alias);
                if ($full_alias !== $short_alias) {
                    foreach ($rows as $nr => $row) {
                        $rows[$nr][$full_alias] = $row[$short_alias];
                        unset($rows[$nr][$short_alias]);
                    }
                }
            }
        } else {
            $rows = [];
        }
        return $rows;
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
    public function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        /* @var $data_connection \exface\Core\DataConnectors\AbstractSqlConnector */
        if (! $this->isWritable())
            return new DataQueryResultData([], 0);
        
        $values = array();
        $columns = array();
        $uid_qpart = null;
        // add values
        foreach ($this->getValues() as $qpart) {
            $attr = $qpart->getAttribute();
            if ($attr->getRelationPath()->toString()) {
                throw new QueryBuilderException('Cannot create attribute "' . $attr->getAliasWithRelationPath() . '" of object "' . $this->getMainObject()->getAliasWithNamespace() . '". Attributes of related objects cannot be created within the same SQL query!');
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
                $value = $this->prepareInputValue($value, $qpart->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'));
                if ($custom_insert_sql) {
                    // If there is a custom insert SQL for the attribute, use it 
                    // NOTE: if you just write some kind of generator here, it 
                    // will make it impossible to save values passed to the query
                    // via setValues() - they will always be replaced by the 
                    // custom SQL. To allow explicitly set values too, the
                    // INSERT_SQL must include something like IF('[#~value#]'!=''...)
                    $insert_sql = $this->replacePlaceholdersInSqlAddress($custom_insert_sql, null, ['~alias' => $this->getMainObject()->getAlias(), '~value' => $value], $this->getMainObject()->getAlias());
                } else {
                    $insert_sql = $value;
                }
                $values[$row][$attr->getDataAddress()] = $insert_sql;
            }
        }
        
        // If there is no UID column, but the UID attribute has a custom insert statement, add it at this point manually
        // This is important because the UID will mostly not be marked as a mandatory attribute in order to preserve the
        // possibility of mixed creates and updates among multiple rows. But an empty non-required attribute will never
        // show up as a value here. Still that value is required!
        if ($uid_qpart === null) {
            $uid_qpart = $this->addValue($this->getMainObject()->getUidAttributeAlias(), '');
        }
        
        // If the UID query part has a custom SQL insert statement, render it here and make sure it's saved
        // into a variable because all sorts of last_insert_id() function will not return such a value.
        if ($uid_qpart->hasValues() === false && $uid_generator = $uid_qpart->getDataAddressProperty('SQL_INSERT')) {
            $uid_generator = str_replace(array(
                '[#~alias#]',
                '[#~value#]'
            ), array(
                $this->getMainObject()->getAlias(),
                $this->prepareInputValue('', $uid_qpart->getAttribute()->getDataType(), $uid_qpart->getDataAddressProperty('SQL_DATA_TYPE'))
            ), $uid_generator);
            
            $columns[$uid_qpart->getDataAddress()] = $uid_qpart->getDataAddress();
            $last_uid_sql_var = '@last_uid';
            foreach ($values as $nr => $row) {
                $values[$nr][$uid_qpart->getDataAddress()] = $last_uid_sql_var . ' := ' . $uid_generator;
            }
        }
        
        $insertedIds = [];
        $uidAlias = $this->getMainObject()->getUidAttribute()->getAlias();
        $insertedCounter = 0;
        foreach ($values as $nr => $row) {
            $sql = 'INSERT INTO ' . $this->getMainObject()->getDataAddress() . ' (' . implode(', ', $columns) . ') VALUES (' . implode(',', $row) . ')';
            $query = $data_connection->runSql($sql);
            
            // Now get the primary key of the last insert.
            if ($last_uid_sql_var) {
                // If the primary key was a custom generated one, it was saved to the corresponding SQL variable.
                // Fetch it from the data base
                if (strcasecmp($uid_qpart->getDataAddressProperty('SQL_DATA_TYPE'), 'binary') === 0) {
                    $last_id_q = $data_connection->runSql('SELECT CONCAT(\'0x\', LOWER(HEX(' . $last_uid_sql_var . ')))');
                } else {
                    $last_id_q = $data_connection->runSql('SELECT ' . $last_uid_sql_var );
                }
                $last_id = reset($last_id_q->getResultArray()[0]);
            } else {
                // If the primary key was autogenerated, fetch it via built-in function
                $last_id = $query->getLastInsertId();
            }
            
            // TODO How to get multiple inserted ids???
            if ($cnt = $query->countAffectedRows()) {
                $insertedCounter += $cnt;
                $insertedIds[] = [$uidAlias => $last_id];
            }
            
            $query->freeResult();
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
        
        return new DataQueryResultData($insertedIds, $insertedCounter);
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
    function update(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        if (! $this->isWritable())
            return new DataQueryResultData([], 0);
        
        // Filters -> WHERE
        // Since UPDATE queries generally do not support joins, tell the build_sql_where() method not to rely on joins in the main query
        $where = $this->buildSqlWhere($this->getFilters(), false);
        $where = $where ? "\n WHERE " . $where : '';
        if (! $where) {
            throw new QueryBuilderException('Cannot perform update on all objects "' . $this->getMainObject()->getAlias() . '"! Forbidden operation!');
        }
        
        // Attributes -> SET
        $table_alias = $this->getShortAlias($this->getMainObject()->getAlias());
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
            
            // Ignore attributes, that do not reference an sql column (or do not have a data address at all)
            if (! $qpart->getDataAddressProperty('SQL_UPDATE') && ! $qpart->getDataAddressProperty('SQL_UPDATE_DATA_ADDRESS') && $this->checkForSqlStatement($attr->getDataAddress())) {
                continue;
            }
            
            if ($qpart->getDataAddressProperty('SQL_UPDATE_DATA_ADDRESS')){
                $column = str_replace('[#~alias#]', $table_alias, $qpart->getDataAddressProperty('SQL_UPDATE_DATA_ADDRESS'));
            } else {
                $column = $table_alias . $this->getAliasDelim() . $attr->getDataAddress();
            }
            
            $custom_update_sql = $qpart->getDataAddressProperty('SQL_UPDATE');
                        
            if (count($qpart->getValues()) == 1) {
                $values = $qpart->getValues();
                $value = $this->prepareInputValue(reset($values), $qpart->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'));
                if ($custom_update_sql) {
                    // If there is a custom update SQL for the attribute, use it ONLY if there is no value
                    // Otherwise there would not be any possibility to save explicit values
                    $updates_by_filter[]= $column . ' = ' . $this->replacePlaceholdersInSqlAddress($custom_update_sql, null, ['~alias' => $table_alias, '~value' => $value], $table_alias);
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
                    try {
                        $value = $this->prepareInputValue($value, $qpart->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'));
                    } catch (\Throwable $e) {
                        throw new QueryBuilderException('Cannot build SQL SET clause for query part "' . $qpart->getAlias() . '" with value "' . $value . '" for query on object ' . $this->getMainObject()->getAliasWithNamespace() . '!', null, $e);
                    }
                    if ($custom_update_sql) {
                        // If there is a custom update SQL for the attribute, use it ONLY if there is no value
                        // Otherwise there would not be any possibility to save explicit values
                        $updates_by_uid[$qpart->getUids()[$row_nr]][$column] = $column . ' = ' . $this->replacePlaceholdersInSqlAddress($custom_update_sql, null, ['~alias' => $table_alias, '~value' => $value], $table_alias);
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
                // $updates_by_filter[] = $this->getShortAlias($this->getMainObject()->getAlias()) . $this->getAliasDelim() . $attr->getDataAddress() . " = CASE " . $this->getMainObject()->getUidAttribute()->getDataAddress() . " \n" . implode($cases) . " END";
            }
        }
        
        // Execute the main query
        foreach ($updates_by_uid as $uid => $row) {
            $sql = 'UPDATE ' . $this->buildSqlFrom() . ' SET ' . implode(', ', $row) . ' WHERE ' . $this->getMainObject()->getUidAttribute()->getDataAddress() . '=' . $uid;
            $query = $data_connection->runSql($sql);
            $affected_rows += $query->countAffectedRows();
            $query->freeResult();
        }
        
        if (count($updates_by_filter) > 0) {
            $sql = 'UPDATE ' . $this->buildSqlFrom() . ' SET ' . implode(', ', $updates_by_filter) . $where;
            $query = $data_connection->runSql($sql);
            $affected_rows = $query->countAffectedRows();
            $query->freeResult();
        }
        
        // Execute Subqueries
        foreach ($this->splitByMetaObject($subqueries_qparts) as $subquery) {
            $subquery->update($data_connection);
        }
        
        return new DataQueryResultData([], $affected_rows);
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
            /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface */
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
     * @param mixed $value            
     * @param DataTypeInterface $data_type            
     * @param string $sql_data_type            
     * @return string
     */
    protected function prepareInputValue($value, DataTypeInterface $data_type, $sql_data_type = NULL)
    {
        $value = $data_type->parse($value);
        switch (true) {
            case $data_type instanceof StringDataType:
                // JSON values are strings too, but their columns should be null even if the value is an
                // empty object or empty array (otherwise the cells would never be null)
                if (($data_type instanceof JsonDataType) && $data_type::isEmptyValue($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = $value === null ? 'NULL' : "'" . $this->escapeString($value) . "'";
                }  
                break;
            case $data_type instanceof BooleanDataType:
                if ($data_type::isEmptyValue($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = $value ? 1 : 0;
                }
                break;
            case $data_type instanceof NumberDataType:
                $value = $data_type::isEmptyValue($value) === true ? 'NULL' : $value;
                break;
            case $data_type instanceof DateDataType:
            case $data_type instanceof TimeDataType:
                $value = $data_type::isEmptyValue($value) === true ? 'NULL' : "'" . $this->escapeString($value) . "'";
                break;
            default:
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
    function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
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
        
        return new DataQueryResultData([], $query->countAffectedRows());
    }
    
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result = $data_connection->runSql($this->buildSqlQueryCount());
        $cnt = $result->getResultArray()[0]['EXFCNT'];
        $result->freeResult();
        return new DataQueryResultData([], $cnt, true, $cnt);
    }
    
    protected function buildSqlQueryCount() : string
    {
        return $this->buildSqlQueryTotals();
    }
    
    /**
     * Creats a SELECT statement for an attribute (qpart).
     * The parameters override certain parts of the statement: $aggregator( $select_from.$select_column AS $select_as ).
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
     * @param boolean|AggregatorInterface $aggregator
     *            set to FALSE to remove grouping completely
     * @param boolean $make_groupable
     *            set to TRUE to force the result to be compatible with GROUP BY
     * @return string
     */
    protected function buildSqlSelect(QueryPartAttribute $qpart, $select_from = null, $select_column = null, $select_as = null, $aggregator = null, $make_groupable = false)
    {
        $output = '';
        $comment = "\n-- buildSqlSelect(" . $qpart->getAlias() . ", " . $select_from . ", " . $select_as . ", " . $aggregator . ", " . $make_groupable . ")\n";
        $add_nvl = false;
        $attribute = $qpart->getAttribute();
        
        // skip attributes with no select (e.g. calculated from other values via formatters)
        if (! $qpart->getDataAddress() && ! $qpart->getDataAddressProperty('SQL_SELECT') && ! $qpart->getDataAddressProperty('SQL_SELECT_DATA_ADDRESS')) {
            return;
        }
        
        if (! $select_from) {
            // if it's a relation, we need to select from a joined table except for reverse relations
            if ($select_from = $attribute->getRelationPath()->toString()) {
                if ($rev_rel = $qpart->getFirstRelation(RelationTypeDataType::REVERSE)) {
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
        
        $select_as = $this->getShortAlias($select_as ?? $qpart->getColumnKey());
        $select_from = $this->getShortAlias($select_from);
        $aggregator = ! is_null($aggregator) ? $aggregator : $qpart->getAggregator();
        
        // build subselects for reverse relations if the body of the select is not specified explicitly
        if (! $select_column && $qpart->getUsedRelations(RelationTypeDataType::REVERSE)) {
            $output = $this->buildSqlSelectSubselect($qpart, $select_from);
            if ($make_groupable && $aggregator){
                if ($aggregator && $aggregator === $qpart->getAggregator()){
                    switch ($aggregator->getFunction()->getValue()){
                        case AggregatorFunctionsDataType::COUNT:
                        case AggregatorFunctionsDataType::COUNT_IF:
                        case AggregatorFunctionsDataType::COUNT_DISTINCT:
                            $aggregator = new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::SUM);
                            break;
                    }
                }
                $output = $this->buildSqlGroupByExpression($qpart, $output, $aggregator);
            } else {
                $add_nvl = true;
            }
        }  elseif ($aggregator) {
            // build grouping function if necessary
            $output = $this->buildSqlSelectGrouped($qpart, $select_from, $select_column, $select_as, $aggregator);
            $add_nvl = true;
        } else {
            // otherwise create a regular select
            if ($select_column) {
                // if the column to select is explicitly defined, just select it
                $output = $select_from . $this->getAliasDelim() . $select_column;
            } elseif ($this->checkForSqlStatement($qpart->getDataAddress())) {
                // see if the attribute is a statement. If so, just replace placeholders
                $output = $this->replacePlaceholdersInSqlAddress($qpart->getDataAddress(), $qpart->getAttribute()->getRelationPath(), ['~alias' => $select_from], $select_from);
            } elseif ($custom_select = $qpart->getDataAddressProperty('SQL_SELECT')){
                // IF there is a custom SQL_SELECT statement, use it.
                $output = $this->replacePlaceholdersInSqlAddress($custom_select, $qpart->getAttribute()->getRelationPath(), ['~alias' => $select_from], $select_from);
            } else {
                // otherwise get the select from the attribute
                if (! $data_address = $qpart->getDataAddressProperty('SQL_SELECT_DATA_ADDRESS')){
                    $data_address = $qpart->getDataAddress();
                }
                $output = $select_from . $this->getAliasDelim() . $data_address;
            }
        }
        
        if ($add_nvl) {
            // do some prettyfying
            // return zero for number fields if the subquery does not return anything
            if ($qpart->getDataType() instanceof NumberDataType) {
                $output = $this->buildSqlSelectNullCheck($output, 0);
            }
        }
        
        if ($output === '*') {
            return $output;
        }
        
        if ($select_as) {
            $output = "\n" . $output . $this->buildSqlAsForSelects($select_as);
        }
        return $comment . $output;
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
        return $this->buildSqlSelectNullCheckFunctionName() . '(' . $select_statement . ', ' . (is_numeric($value_if_null) ? $value_if_null : '"' . $value_if_null . '"') . ')';
    }
    
    protected function buildSqlSelectNullCheckFunctionName(){
        return 'COALESCE';
    }

    /**
     * Builds subselects for reversed relations
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart            
     * @param string $select_from       
     * @return string
     */
    protected function buildSqlSelectSubselect(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null)
    {
        $rev_rel = $qpart->getFirstRelation(RelationTypeDataType::REVERSE);
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
        /** @var MetaRelationPathInterface $reg_rel_path part of the relation part up to the first reverse relation */
        $reg_rel_path = $rel_path->getSubpath(0, $rel_path->getIndexOf($rev_rel));
        /** @var MetaRelationPathInterface complete path of the first reverse relation */
        $rev_rel_path = $reg_rel_path->copy()->appendRelation($rev_rel);
        
        // build a subquery
        /* @var $relq \exface\Core\QueryBuilders\AbstractSqlBuilder */
        $relq = QueryBuilderFactory::createFromSelector($this->getSelector());
        // the query is based on the first object after the reversed relation (CUSTOMER_CARD for the above example)
        $relq->setMainObject($rev_rel->getRightObject());
        $relq->setQueryId($this->getNextSubqueryId());
        
        // Add the key alias relative to the first reverse relation (TYPE->LABEL for the above example)
        $relq_attribute_alias = str_replace($rev_rel_path->toString() . RelationPath::getRelationSeparator(), '', $qpart->getAlias());
        $relq->addAttribute($relq_attribute_alias);
        
        // Let the subquery inherit all filters of the main query, that need to be applied to objects beyond the reverse relation.
        // In our examplte, those would be any filter on ORDER->CUSTOMER<-CUSTOMER_CARD or ORDER->CUSTOMER<-CUSTOMER_CARD->TYPE, etc. Filters
        // over ORDER oder ORDER->CUSTOMER would be applied to the base query and ar not neeede in the subquery any more.
        // If we would rebase and add all filters, it will still work, but the SQL would get much more complex and surely 
        // slow with large data sets.
        // Filtering out applicable filters (conditions) is done via the following callback, that returns TRUE only if the
        // path we rebase to matches the beginning of the condition's relation path.
        $relq_condition_filter = function($condition, $relation_path_to_new_base_object) {
            if ($condition->getExpression()->isMetaAttribute() && stripos($condition->getExpression()->toString(), $relation_path_to_new_base_object) !== 0) {
                return false;
            } else {
                return true;
            }
        };
        $relq->setFiltersConditionGroup($this->getFilters()->getConditionGroup()->rebase($rev_rel_path->toString(), $relq_condition_filter));
        // Add a new filter to attach to the main query (WHERE CUSTOMER_CARD.CUSTOMER_ID = ORDER.CUSTOMER_ID for the above example)
        // This only makes sense, if we have a reference to the parent query (= the $select_from parameter is set)
        if ($select_from) {
            $rightKeyAttribute = $rev_rel->getRightKeyAttribute();
            if (! $reg_rel_path->isEmpty()) {
                // attach to the related object key of the last regular relation before the reverse one
                $junction_attribute = $this->getMainObject()->getAttribute(RelationPath::relationPathAdd($reg_rel_path->toString(), $this->getMainObject()->getRelation($reg_rel_path->toString())->getRightKeyAttribute()->getAlias()));
            } else { 
                // attach to the uid of the core query if there are no regular relations preceeding the reversed one
                $junction_attribute = $this->getMainObject()->getUidAttribute();
            }
            // The filter needs to be an EQ, since we want a to compare by "=" to whatever we define without any quotes
            // Putting the value in brackets makes sure it is treated as an SQL expression and not a normal value
            $junctionQpart = $relq->addFilterWithCustomSql($rightKeyAttribute->getAlias(), '(' . $select_from . $this->getAliasDelim() . $junction_attribute->getDataAddress() . ')', EXF_COMPARATOR_EQUALS);
            
            if ($customJoinOn = $qpart->getDataAddressProperty('SQL_JOIN_ON')) {
                // If it's a custom JOIN, calculate it here
                $customJoinOn = StringDataType::replacePlaceholders($customJoinOn, ['~left_alias' => $this->getShortAlias($this->getMainObject()->getAlias()), '~right_alias' => $select_from]);
                $junctionQpart->setDataAddressProperty('SQL_WHERE', $customJoinOn);
            }
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
     * @param AggregatorInterface $aggregator            
     * @return string
     */
    protected function buildSqlSelectGrouped(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null, $select_column = null, $select_as = null, AggregatorInterface $aggregator = null)
    {
        $aggregator = ! is_null($aggregator) ? $aggregator : $qpart->getAggregator();
        $select = $this->buildSqlSelect($qpart, $select_from, $select_column, false, false);
        
        return $this->buildSqlGroupByExpression($qpart, $select, $aggregator);
    }
    
    /**
     * 
     * @param QueryPartAttribute $qpart
     * @param string $sql
     * @param AggregatorInterface $aggregator
     * @throws QueryBuilderException
     * @return string
     */
    protected function buildSqlGroupByExpression(QueryPartAttribute $qpart, $sql, AggregatorInterface $aggregator){
        $output = '';
        
        $args = $aggregator->getArguments();
        $function_name = $aggregator->getFunction()->getValue();
        
        switch ($function_name) {
            case AggregatorFunctionsDataType::SUM:
            case AggregatorFunctionsDataType::AVG:
            case AggregatorFunctionsDataType::COUNT:
            case AggregatorFunctionsDataType::MAX:
            case AggregatorFunctionsDataType::MIN:
                $output = $function_name . '(' . $sql . ')';
                break;
            case AggregatorFunctionsDataType::LIST_DISTINCT:
            case AggregatorFunctionsDataType::LIST_ALL:
                $output = "GROUP_CONCAT(" . ($function_name == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . $sql . " SEPARATOR " . ($args[0] ? $args[0] : "', '") . ")";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                $output = "COUNT(DISTINCT " . $sql . ")";
                break;
            case AggregatorFunctionsDataType::COUNT_IF:
                $cond = $args[0];
                list($if_comp, $if_val) = explode(' ', $cond, 2);
                if (!$if_comp || is_null($if_val)) {
                    throw new QueryBuilderException('Invalid argument for COUNT_IF aggregator: "' . $cond . '"!', '6WXNHMN');
                }
                $output = "SUM(" . $this->buildSqlWhereComparator($sql,  $if_comp, $if_val, $qpart->getAttribute()->getDataType()). ")";
                break;
            default:
                break;
        }
        
        return $output;
    }

    protected function buildSqlFrom()
    {
        // Replace static placeholders
        $alias = $this->getMainObject()->getAlias();
        $table = str_replace('[#~alias#]', $alias, $this->getMainObject()->getDataAddress());
        $from = $table . $this->buildSqlAsForTables($this->getMainTableAlias());
        
        // Replace dynamic palceholder
        $from = $this->replacePlaceholdersByFilterValues($from);
        
        return $from;
    }
    
    protected function getMainTableAlias() : string
    {
        return $this->getShortAlias($this->getMainObject()->getAlias() . $this->getQueryId());
    }
    
    /**
     * Returns an SQL snippet to give a table an alias: e.g. ' MYTABLE' or ' AS "MYTABLE"'.
     * 
     * @param string $alias
     * @return string
     */
    protected function buildSqlAsForTables(string $alias) : string
    {
        return ' ' . $alias;
    }
    
    /**
     * Returns an SQL snippet to give a SELECT column an alias: e.g. ' MYCOL' or ' AS "MYCOL"'.
     *
     * @param string $alias
     * @return string
     */
    protected function buildSqlAsForSelects(string $alias) : string
    {
        return ' AS "' . $alias . '"';
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
                $joins[$right_table_alias] = "\n JOIN " . str_replace('[#~alias#]', $right_table_alias, $this->getMainObject()->getDataAddress()) . $this->buildSqlAsForTables($right_table_alias) . ' ON ' . $left_table_alias . $this->getAliasDelim() . $this->getMainObject()->getUidAttributeAlias() . ' = ' . $right_table_alias . $this->getAliasDelim() . $this->getMainObject()->getUidAttributeAlias();
            } else {
                // In most cases we will build joins for attributes of related objects.
                $left_table_alias = $this->getShortAlias(($left_table_alias ? $left_table_alias : $this->getMainObject()->getAlias()) . $this->getQueryId());
                foreach ($rels as $alias => $rel) {
                    /* @var $rel \exface\Core\Interfaces\Model\MetaRelationInterface */
                    if ($rel->isForwardRelation() === true) {
                        $right_table_alias = $this->getShortAlias($alias . $this->getQueryId());
                        $right_obj = $this->getMainObject()->getRelatedObject($alias);
                        // generate the join sql
                        $join = "\n " . $this->buildSqlJoinType($rel) . ' JOIN ' . str_replace('[#~alias#]', $right_table_alias, $right_obj->getDataAddress()) . $this->buildSqlAsForTables($right_table_alias) . ' ON ';
                        if ($customOn = $rel->getLeftKeyAttribute()->getDataAddressProperty('SQL_JOIN_ON')) {
                            // If a custom join condition ist specified in the attribute, that defines the relation, just replace the aliases in it
                            $join .= StringDataType::replacePlaceholders($customOn, ['~left_alias' => $left_table_alias, '~right_alias' => $right_table_alias]);
                        } else {
                            // Otherwise create the ON clause from the attributes on both sides of the relation.
                            $left_join_on = $this->buildSqlJoinSide($rel->getLeftKeyAttribute()->getDataAddress(), $left_table_alias);
                            $right_join_on = $this->buildSqlJoinSide($rel->getRightKeyAttribute()->getDataAddress(), $right_table_alias);
                            $join .=  $left_join_on . ' = ' . $right_join_on;
                            if ($customSelectWhere = $right_obj->getDataAddressProperty('SQL_SELECT_WHERE')) {
                                $join .= ' AND ' . StringDataType::replacePlaceholders($customSelectWhere, ['~alias' => $right_table_alias]);
                            }
                        }
                        $joins[$right_table_alias] = $join;
                        // continue with the related object
                        $left_table_alias = $right_table_alias;
                    } else {
                        // stop joining as all the following joins will be add in subselects of the enrichment select
                        break;
                    }
                }
            }
        }
        return $joins;
    }
    
    protected function buildSqlJoinType(MetaRelationInterface $relation)
    {
        /* FIXME use inner joins for required relations? Supposed to be faster, but it would result in different
         * behavior depending on relation settings... Need to test a bit more!
        if ($relation->isForwardRelation() === true && $relation->getLeftKeyAttribute()->isRequired() === true) {
            return 'INNER';
        }*/
        return 'LEFT';
    }

    protected function buildSqlJoinSide($data_address, $table_alias)
    {
        $join_side = $data_address;
        if ($this->checkForSqlStatement($join_side)) {
            $join_side = str_replace('[#~alias#]', $table_alias, $join_side);
        } else {
            $join_side = $table_alias . $this->getAliasDelim() . $join_side;
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
     * @see buildSqlWhere()
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
     * to buildSqlWhereCondition() but it takes care of filters with aggregators.
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
        $comp = $this->getOptimizedComparator($qpart);
        
        $select = $this->buildSqlSelectGrouped($qpart);
        $customWhereClause = $qpart->getDataAddressProperty('SQL_WHERE');
        $object_alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() : $this->getMainObject()->getAlias());
        $table_alias = $this->getShortAlias($object_alias . $this->getQueryId());
        // doublecheck that the attribut is known
        if (! ($select || $customWhereClause) || $val === '') {
            if ($val === '') {
                $hint = ' (the value is empty)';
            } else {
                $hint = ' (neither a data address, nor a custom SQL_WHERE found for the attribute)';
            }
            throw new QueryBuilderException('Illegal SQL HAVING clause for object "' . $this->getMainObject()->getName() . '" (' . $this->getMainObject()->getAlias() . '): expression "' . $qpart->getAlias() . '", Value: "' . $val . '"' . $hint);
            return false;
        }
        
        // build the HAVING clause
        if ($customWhereClause) {
            // check if it has an explicit where clause. If not try to filter based on the select clause
            $output = $this->replacePlaceholdersInSqlAddress($customWhereClause, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias, '~value' => $val], $table_alias);
        } else {
            // Determine, what we are going to compare to the value: a subquery or a column
            if ($this->checkForSqlStatement($attr->getDataAddress())) {
                $subj = $this->replacePlaceholdersInSqlAddress($select, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias], $table_alias);
            } else {
                $subj = $select;
            }
            // Do the actual comparing
            $output = $this->buildSqlWhereComparator($subj, $comp, $val, $qpart->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'), $delimiter);
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
                $where .= "\n-- buildSqlWhereCondition(" . $qpart_fltr->getCondition()->toString() . ", " . $rely_on_joins . ")"
                        . "\n " . ($where ? $op . " " : '') . $fltr_string;
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
        return $qpart->getAggregator() && ! $qpart->getFirstRelation(RelationTypeDataType::REVERSE) ? true : false;
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
        $comp = $this->getOptimizedComparator($qpart);
        $delimiter = $qpart->getValueListDelimiter();
        
        $select = $attr->getDataAddress();
        $customWhereClause = $qpart->getDataAddressProperty('SQL_WHERE');
        $customWhereAddress = $qpart->getDataAddressProperty('SQL_WHERE_DATA_ADDRESS');
        $object_alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() : $this->getMainObject()->getAlias());
        $table_alias = $this->getShortAlias($object_alias . $this->getQueryId());
        
        // doublecheck that the attribute is known
        if (! ($select || $customWhereClause) || $val === '') {
            if ($val === '') {
                $hint = ' (the value is empty)';
            } else {
                $hint = ' (neither a data address, nor a custom SQL_WHERE found for the attribute)';
            } 
            throw new QueryBuilderException('Illegal SQL WHERE clause for object "' . $this->getMainObject()->getName() . '" (' . $this->getMainObject()->getAlias() . '): expression "' . $qpart->getAlias() . '", Value: "' . $val . '"' . $hint);
            return false;
        }
        
        if (! $customWhereClause && ($qpart->getFirstRelation(RelationTypeDataType::REVERSE) || ($rely_on_joins == false && count($qpart->getUsedRelations()) > 0))) {
            // Use subqueries for attributes with reverse relations (unless a custom WHERE is defined)
            // or in case we know, tha main query will not have any joins (e.g. UPDATE queries)
            $output = $this->buildSqlWhereSubquery($qpart, $rely_on_joins);
        } else {
            // build the where
            if ($customWhereClause) {
                // check if it has an explicit where clause. If not try to filter based on the select clause
                $output = $this->replacePlaceholdersInSqlAddress($customWhereClause, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias, '~value' => $val], $table_alias);
                return $output;
            } elseif($customWhereAddress) {
                $subj = $this->replacePlaceholdersInSqlAddress($customWhereAddress, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias], $table_alias);
            } else {
                // Determine, what we are going to compare to the value: a subquery or a column
                if ($this->checkForSqlStatement($attr->getDataAddress())) {
                    $subj = $this->replacePlaceholdersInSqlAddress($select, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias], $table_alias);
                } else {
                    $subj = $table_alias . $this->getAliasDelim() . $select;
                }
            }
            // Do the actual comparing
            $output = $this->buildSqlWhereComparator($subj, $comp, $val, $qpart->getDataType(), $attr->getDataAddressProperty('SQL_DATA_TYPE'), $delimiter);
        }
        return $output;
    }
    
    protected function getOptimizedComparator(QueryPartFilter $qpart)
    {
        $val = $qpart->getCompareValue();
        $attr = $qpart->getAttribute();
        $comp = $qpart->getComparator();
        
        if ($attr->isRelation() && $comp != EXF_COMPARATOR_IN) {
            // always use the equals comparator for foreign keys! It's faster!
            $comp = EXF_COMPARATOR_EQUALS;
        } elseif ($attr->isExactly($this->getMainObject()->getUidAttribute()) && ($comp == EXF_COMPARATOR_IS || $comp == EXF_COMPARATOR_IS_NOT)) {
            $comp = $comp === EXF_COMPARATOR_IS ? EXF_COMPARATOR_EQUALS : EXF_COMPARATOR_EQUALS_NOT;
        } elseif (($qpart->getDataType() instanceof NumberDataType) && ($comp == EXF_COMPARATOR_IS || $comp == EXF_COMPARATOR_IS_NOT) && is_numeric($val)) {
            // also use equals for the NUMBER data type, but make sure, the value to compare to is really a number (otherwise the query will fail!)
            $comp = $comp === EXF_COMPARATOR_IS ? EXF_COMPARATOR_EQUALS : EXF_COMPARATOR_EQUALS_NOT;
        } elseif (($qpart->getDataType() instanceof BooleanDataType) && ($comp == EXF_COMPARATOR_IS || $comp == EXF_COMPARATOR_IS_NOT)) {
            // also use equals for the BOOLEAN data type
            $comp = $comp === EXF_COMPARATOR_IS ? EXF_COMPARATOR_EQUALS : EXF_COMPARATOR_EQUALS_NOT;
        } elseif (($qpart->getDataType() instanceof DateDataType) && ($comp == EXF_COMPARATOR_IS || $comp == EXF_COMPARATOR_IS_NOT)) {
            // also use equals for the NUMBER data type, but make sure, the value to compare to is really a number (otherwise the query will fail!)
            $comp = $comp === EXF_COMPARATOR_IS ? EXF_COMPARATOR_EQUALS : EXF_COMPARATOR_EQUALS_NOT;
        }
        return $comp;
    }
    
    /**
     * 
     * @param string $subject column name or subselect
     * @param string $comparator one of the EXF_COMPARATOR_xxx constants
     * @param string $value value or SQL expression to compare to
     * @param DataTypeInterface $data_type
     * @param string $sql_data_type value of SQL_DATA_TYPE data source setting
     * @param string $value_list_delimiter delimiter used to separate concatenated lists of values
     * @return string
     */
    protected function buildSqlWhereComparator($subject, $comparator, $value, DataTypeInterface $data_type, $sql_data_type = NULL, $value_list_delimiter = EXF_LIST_SEPARATOR)
    {
        // Check if the value is of valid type.
        try {
            // Pay attention to comparators expecting concatennated values (like IN) - the concatennated value will not validate against
            // the data type, but the separated parts should
            if ($comparator != EXF_COMPARATOR_IN && $comparator != EXF_COMPARATOR_NOT_IN) {
                // If it's a single value, cast it to the data type to make sure, it's a valid value.
                // FIXME how to distinguish between actual values and SQL statements as values? The
                // following switch() makes sure, a number can be compared to an SQL statement 
                // which is ultimately a string - casting the SQL statement would result in a 
                // casting exception. The current solution is insecure though, as it makes it
                // possible to pass SQL statements from outside and it uses them without any
                // sanitization! We could use $qpart->isValueDataAddress() here, but currently
                // we don't have the query part at hand at this point.
                switch (true) {
                    case ($data_type instanceof DateDataType):
                    case ($data_type instanceof NumberDataType):
                    case ($data_type instanceof BooleanDataType):
                        if (! $this->checkForSqlStatement($value)) {
                            $value = $data_type::cast($value);
                        }
                        break;
                    default:
                        $value = $data_type::cast($value);
                }
            } else {
                $values = explode($value_list_delimiter, $value);
                $value = '';
                // $values = explode($value_list_delimiter, trim($value, $value_list_delimiter));
                foreach ($values as $nr => $val) {
                    // If there is an empty string among the values or one of the empty-comparators, 
                    // this means that the value may or may not be empty (NULL). NULL is not a valid
                    // value for an IN-statement, though, so we need to append an "OR IS NULL" here.
                    if ($val === '' || $val === EXF_LOGICAL_NULL) {
                        unset($values[$nr]);
                        $value = $subject . ($comparator == EXF_COMPARATOR_IN ? ' IS NULL' : ' IS NOT NULL');
                        continue;
                    }
                    // Normalize non-empty values
                    $val = $this->prepareWhereValue($val, $data_type, $sql_data_type);
                    if ($data_type instanceof StringDataType) {
                        $values[$nr] = "'" . $val . "'";
                    }
                }
                $value = '(' . (! empty($values) ? implode(',', $values) : 'NULL') . ')' . ($value ? ' OR ' . $value : '');
            }
        } catch (DataTypeCastingError $e) {
            // If the data type is incompatible with the value, return a WHERE clause, that is always false.
            // A comparison of a date field with a string or a number field with
            // a string simply cannot result in TRUE.
            return '/* ' . $subject . ' cannot pass comparison to "' . $value . '" via comparator "' . $comparator . '": wrong data type! */' . "\n"
                    . '1 = 0';
        }
        
        if (is_null($value) || $this->prepareWhereValue($value, $data_type) === EXF_LOGICAL_NULL){
            switch ($comparator) {
                case EXF_COMPARATOR_EQUALS:
                case EXF_COMPARATOR_IS:
                    return $subject . ' IS NULL';
                default:
                    return $subject . ' IS NOT NULL';
            }
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
                if ($data_type instanceof StringDataType) {
                    $output = $subject . " = '" . $this->prepareWhereValue($value, $data_type, $sql_data_type) . "'";
                } else {
                    $output = $subject . " = " . $this->prepareWhereValue($value, $data_type, $sql_data_type);
                }
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
                break;
            case EXF_COMPARATOR_IS:
            default:
                $output = 'UPPER(' . $subject . ") LIKE '%" . $this->prepareWhereValue(strtoupper($value), $data_type) . "%'";
        }
        return $output;
    }

    protected function prepareWhereValue($value, DataTypeInterface $data_type, $sql_data_type = NULL)
    {
        // IDEA some data type specific procession here
        if ($data_type instanceof BooleanDataType) {
            $output = $value ? 1 : 0;
        } elseif (strcasecmp($value, EXF_LOGICAL_NULL) === 0) {
            return EXF_LOGICAL_NULL;
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
            $start_rel = $qpart->getFirstRelation(RelationTypeDataType::REVERSE);
        } else {
            // Otherwise, all relations (starting from the first one) must be put into the subquery, because there are no joins in the main one
            $start_rel = $qpart->getFirstRelation();
        }
        
        if ($start_rel) {
            $qpart_rel_path = $qpart->getAttribute()->getRelationPath();
            /** @var MetaRelationPathInterface $prefix_rel_path part of the relation part up to the first reverse relation */
            $prefix_rel_path = $qpart_rel_path->getSubpath(0, $qpart_rel_path->getIndexOf($start_rel));
            
            // build a subquery
            /* @var $relq \exface\Core\QueryBuilders\AbstractSqlBuilder */
            $relq = QueryBuilderFactory::createFromSelector($this->getSelector());
            $relq->setMainObject($start_rel->getRightObject());
            $relq->setQueryId($this->getNextSubqueryId());
            if ($start_rel->isReverseRelation()) {
                // If we are dealing with a reverse relation, build a subquery to select foreign keys from rows of the joined tables,
                // that match the given filter
                $rel_filter_alias = $qpart->getAttribute()->rebase($qpart_rel_path->getSubpath($qpart_rel_path->getIndexOf($start_rel) + 1))->getAliasWithRelationPath();
                
                // Remember to keep the aggregator of the attribute filtered over. Since we are interested in a list of keys, the
                // subquery should GROUP BY these kees.
                if ($qpart->getAggregator()) {
                    // IDEA HAVING-subqueries can be very slow. Perhaps we can optimize the subquery a litte in certain cases:
                    // e.g. if we are filtering over a SUM of natural numbers with "> 0", we could simply add a "> 0" filter 
                    // without any aggregation and it should yield the same results
                    $rel_filter_alias .= DataAggregation::AGGREGATION_SEPARATOR . $qpart->getAggregator()->exportString();
                    $relq->addAggregation($start_rel->getRightKeyAttribute()->getAlias());
                    
                    // If we are in a WHERE subquery of a filter with an aggregator, this means, we want to filter
                    // over the aggregated value. However, there might be other filters, that affect this aggregated
                    // value: e.g. as SUM over transactions for a product will be different depending on the store
                    // filter set for the query. So we need all applicale non-aggregating filters in our subquery.
                    // This is achieved by rebasing all filters with the following filter callback, that excludes 
                    // certain conditions.
                    $relq_condition_filter = function($condition, $relation_path_to_new_base_object) use ($qpart) {
                        // If the condition is not an attribute, keep it - other partsof the code will deal with it
                        if (! $condition->getExpression()->isMetaAttribute()) {
                            return true;
                        }
                        // If a condition matches the query part we are processing right now, skip it - we will
                        // add it later explicitly.
                        if ($condition->getExpression()->toString() === $qpart->getExpression()->toString()){
                            return false;
                        }
                        // If a conditon  has an aggregator itself - skip it as it will get it's own subquery.
                        if (DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $condition->getExpression()->toString())) {
                            return false;
                        }
                        // If a condition is not applicable (is applied to something else, then the tables in our
                        // subquery) - skip it.
                        if (stripos($condition->getExpression()->toString(), $relation_path_to_new_base_object) !== 0) {
                            return false;
                        }
                        return true;
                    };
                    /* @var MetaRelationPathInterface complete path of the first reverse relation */
                    $rev_rel_path = $prefix_rel_path->copy()->appendRelation($start_rel);
                    $relq->setFiltersConditionGroup($this->getFilters()->getConditionGroup()->rebase($rev_rel_path->toString(), $relq_condition_filter));
                }
                $relqKeyPart = $relq->addAttribute($start_rel->getRightKeyAttribute()->getAlias());
                
                // If the key attribute does not have a data address, but we use a custom join we can 
                // select all (*) - that's OK because custom JOINs use WHERE EXISTS
                if ($relqKeyPart->getDataAddress() === '' 
                    && ! $relqKeyPart->getDataAddressProperty('SQL_SELECT') 
                    && ! $relqKeyPart->getDataAddressProperty('SQL_SELECT_DATA_ADDRESS')
                    && $relqKeyPart->getDataAddressProperty('SQL_JOIN_ON')) {
                    $relqKeyPart->setDataAddressProperty('SQL_SELECT', '*');
                }
                
                // Add the filter relative to the first reverse relation with the same $value and $comparator
                if ($qpart->isValueDataAddress()) {
                    // If the data address is a custom sql, make sure it still remains a custom sql no matter what
                    $relq->addFilterWithCustomSql($rel_filter_alias, $qpart->getCompareValue(), $qpart->getComparator());
                } else {
                    // Otherwise just add a regular filter
                    $relq->addFilterFromString($rel_filter_alias, $qpart->getCompareValue(), $qpart->getComparator());
                }
                
                if (! $prefix_rel_path->isEmpty()) {
                    // FIXME add support for related_object_special_key_alias
                    $prefix_rel_str = RelationPath::relationPathAdd($prefix_rel_path->toString(), $this->getMainObject()->getRelatedObject($prefix_rel_path->toString())->getUidAttributeAlias());
                    $prefix_rel_qpart = new QueryPartSelect($prefix_rel_str, $this, DataColumn::sanitizeColumnName($prefix_rel_str));
                    $junction = $this->buildSqlSelect($prefix_rel_qpart, null, null, '');
                } else {
                    $junctionTableAlias = $this->getShortAlias($start_rel->getLeftObject()->getAlias() . $this->getQueryId());
                    $junctionDataAddress = $start_rel->getLeftKeyAttribute()->getDataAddress();;
                    if ($this->checkForSqlStatement($junctionDataAddress) === true) {
                        $junction = $this->replacePlaceholdersInSqlAddress($junctionDataAddress, null, null, $junctionTableAlias);
                    } else {
                        $junction = $junctionTableAlias . $this->getAliasDelim() . $junctionDataAddress;
                    }
                }
                
                // Handle SQL_JOIN_ON if it is defined for the right attribute (i.e. if we would join our left table to our right table,
                // the JOIN would use this custom ON statement). Here we build the custom ON statement and use it as a WHERE clause in
                // the subselect.
                if ($customJoinOn = $start_rel->getRightKeyAttribute()->getDataAddressProperty('SQL_JOIN_ON')) {
                    $customJoinOn = StringDataType::replacePlaceholders($customJoinOn, ['~left_alias' => $relq->getMainTableAlias(), '~right_alias' => $this->getMainTableAlias()]);
                    $joinFilterQpart = $relq->addFilterFromString($start_rel->getRightKeyAttribute()->getAlias(), $qpart->getCompareValue(), $qpart->getComparator());
                    $joinFilterQpart->setDataAddressProperty('SQL_WHERE', $customJoinOn);
                    $sql = ' EXISTS (' . $relq->buildSqlQuerySelect() . ')';
                    return $sql;
                }
                
            } else {
                // If we are dealing with a regular relation, build a subquery to select primary keys from joined tables and match them to the foreign key of the main table
                $relq->addFilter($qpart->rebase($relq, $start_rel->getAlias()));
                $relq->addAttribute($start_rel->getRightKeyAttribute()->getAlias());
                $junction_qpart = new QueryPartSelect($start_rel->getLeftKeyAttribute()->getAlias(), $this, $start_rel->getLeftKeyAttribute()->getAliasWithRelationPath());
                $junction = $this->buildSqlSelect($junction_qpart, null, null, '');
            }
            
            //$output = $junction . ' IN (' . $relq->buildSqlQuerySelect() . ')';
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
        if ($customOrderBy = $qpart->getDataAddressProperty("SQL_ORDER_BY")) {
            $output = $this->getShortAlias($this->getMainObject()->getAlias()) . $this->getAliasDelim() . $customOrderBy;
        } else {
            $output = $this->getShortAlias($qpart->getColumnKey());
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
            $output = ($select_from ? $this->getShortAlias($select_from . $this->getQueryId()) . $this->getAliasDelim() : '') . $qpart->getAttribute()->getDataAddress();
        }
        return $output;
    }

    /**
     * Shortens an alias (or any string) to $getShortAliasMaxLength() by cutting off the rest and appending
     * a unique id.
     * Also replaces forbidden words and characters ($short_alias_forbidden and $short_alias_remove_chars).
     * The result can be translated back to the original via get_full_alias($short_alias)
     * Every SQL-alias (like "SELECT xxx AS alias" or "SELECT * FROM table1 alias") should be shortened
     * because most SQL dialects only allow a limited number of characters in an alias (this number should
     * be set in $getShortAliasMaxLength()).
     *
     * @param string $full_alias            
     * @return string
     */
    protected function getShortAlias($full_alias)
    {
        if (isset($this->short_aliases[$full_alias])) {
            $short_alias = $this->short_aliases[$full_alias];
        } elseif (strlen($full_alias) <= $this->getShortAliasMaxLength() && $this->getCleanAlias($full_alias) == $full_alias && ! in_array($full_alias, $this->getReservedWords())) {
            $short_alias = $full_alias;
        } else {
            $this->short_alias_index ++;
            $short_alias = $this->short_alias_prefix . str_pad($this->short_alias_index, 3, '0', STR_PAD_LEFT) . $this->short_alias_replacer . substr($this->getCleanAlias($full_alias), - 1 * ($this->getShortAliasMaxLength() - 3 - 1 - 1));
            $this->short_aliases[$full_alias] = $short_alias;
        }
        
        return $short_alias;
    }

    protected function getCleanAlias($alias)
    {
        $output = '';
        $output = str_replace($this->getShortAliasForbiddenChars(), '_', $alias);
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
            if ($qpart->getUsedRelations(RelationTypeDataType::REVERSE)) {
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
     * Replaces the [#~alias#] placeholder with the $table_alias if given or the main table alias otherwise
     *
     * @param string $original_where_statement            
     * @param string $custom_statement            
     * @param string $table_alias            
     * @param string $operator            
     * @return string
     */
    protected function appendCustomWhere($original_where_statement, $custom_statement, $table_alias = null, $operator = 'AND')
    {
        return $original_where_statement . ($original_where_statement ? ' ' . $operator . ' ' : '') . str_replace('[#~alias#]', ($table_alias ? $table_alias : $this->getShortAlias($this->getMainObject()->getAlias())), $custom_statement);
    }
    
    /**
     * 
     * @param QueryPartAttribute $qpart
     * @return boolean
     */
    protected function isQpartRelatedToAggregator(QueryPartAttribute $qpart)
    {
        $related_to_aggregator = false;
        foreach ($this->getAggregations() as $aggr) {
            if (strpos($qpart->getAlias(), $aggr->getAlias()) === 0) {
                $related_to_aggregator = true;
            }
        }
        return $related_to_aggregator;
    }
    
    protected function replacePlaceholdersInSqlAddress($data_address, RelationPath $relation_path = null, array $static_placeholders = null, $select_from = null)
    {
        $original_data_address = $data_address;
        
        if (! empty($static_placeholders)){
            $static_phs = array_map(function($ph){return '[#' . $ph . '#]';}, array_keys($static_placeholders));
            $static_values = array_values($static_placeholders);
            $data_address = str_replace($static_phs, $static_values, $data_address);
        }
        
        if ($relation_path){
            $prefix = $relation_path->toString();
        }
        
        foreach (StringDataType::findPlaceholders($data_address) as $ph) {
            $ph_attribute_alias = RelationPath::relationPathAdd($prefix, $ph);
            if (! $qpart = $this->getAttribute($ph_attribute_alias)) {
                // Throw an error if the attribute cannot be resolved relative to the main object of the query
                try {
                    $qpart = new QueryPartSelect($ph_attribute_alias, $this, DataColumn::sanitizeColumnName($string));
                } catch (MetaAttributeNotFoundError $e){
                    throw new QueryBuilderException('Cannot use placeholder [#' . $ph . '#] in data address "' . $original_data_address . '": no attribute "' . $ph_attribute_alias . '" found for query base object ' . $this->getMainObject()->getAliasWithNamespace() . '!', null, $e);
                }
                // Throw an error if the placeholder contains a relation path (relative to the object of the
                // attribute, where it was used.
                // TODO it would be really cool to support relations in placeholders, but how to add corresponding
                // joins? An attempt was made in feature/sql-placeholders-with-relation, but without ultimate success.
                // Alternatively we could add the query parts to the query and restart it's generation...
                if ($relation_path !== null && ! $relation_path->getEndObject()->getAttribute($ph)->getRelationPath()->isEmpty()){
                    throw new QueryBuilderException('Cannot use placeholder [#' . $ph . '#] in data address "' . $original_data_address . '": placeholders for related attributes currently not supported in SQL query builders unless all required attributes are explicitly selected in the query too.');
                }
            }
            $data_address = str_replace('[#' . $ph . '#]', $this->buildSqlSelect($qpart, $select_from, null, false), $data_address);
        }
        return $data_address;
    }
    
    /**
     * The SQL builder can join of related objects as long as they are located in the same database.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        // TODO Check if all objects along the relation path also belong to the data source
        // TODO Instead of checking the data source, check if it points to the same data base
        return $attribute->getObject()->getDataSourceId() === $this->getMainObject()->getDataSourceId();
    }
    
    /**
     * Returns the alias delimiter (e.g. the dot in MYTABLE.FIELD).
     * 
     * While it defaults to a dot '.' for most SQL dialects, you can change this easily by overriding
     * this method.
     * 
     * @return string
     */
    protected function getAliasDelim() : string
    {
        return '.';
    }
    
    /**
     *
     * @param string $attribute_alias
     * @param string $sql
     * @param string $comparator
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
     */
    protected function addFilterWithCustomSql($attribute_alias, $sql, $comparator = EXF_COMPARATOR_IS)
    {
        $qpart = $this->addFilterFromString($attribute_alias, $sql, $comparator);
        $qpart->setValueIsDataAddress(true);
        return $qpart;
    }
    
    protected function addFilterSql(string $sqlPredicate) : AbstractSqlBuilder
    {
        $this->customFilterSqlPredicates[] = $sqlPredicate;
        return $this;
    }
    
    /**
     * Returns the maximum number of characters allowed in a field or table alias.
     * 
     * Override this method to match the requirements of a specific SQL engine.
     * 
     * @return int
     */
    protected function getShortAliasMaxLength() : int
    {
        return 30;
    }
    
    /**
     *
     * @return array
     */
    protected function getShortAliasForbiddenChars() : array
    {
        return $this->short_alias_remove_chars;
    }
    
    /**
     * 
     * @param array $value
     * @return AbstractSqlBuilder
     */
    protected function setShortAliasForbiddenChars(array $value) : AbstractSqlBuilder
    {
        $this->short_alias_remove_chars = $value;
        return $this;
    }
    
    protected function getShortAliases() : array
    {
        return $this->short_aliases;
    }
    
    /**
     *
     * @return array
     */
    protected function getReservedWords() : array
    {
        return $this->reserved_words;
    }
    
    /**
     * 
     * @param array $value
     * @return AbstractSqlBuilder
     */
    protected function setReservedWords(array $value) : AbstractSqlBuilder
    {
        $this->reserved_words = $value;
        return $this;
    }
}