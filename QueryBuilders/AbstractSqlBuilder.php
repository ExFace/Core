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
use exface\Core\Exceptions\TemplateRenderer\PlaceholderNotFoundError;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
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
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\CommonLogic\QueryBuilder\QueryPart;
use exface\Core\Factories\FormulaFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * A query builder for generic SQL syntax.
 *
 * ## Data addresses
 * 
 * Object data addresses can be a table or view name or any custom SQL usable within the FROM clause. 
 * 
 * Attribute addresses can be column names or any custom SQL usable in the SELECT clause. 
 * 
 * Custom SQL must be enclosed in parentheses `(``)` to ensure it is correctly
 * distinguished from table/column names. Custom SQLs may include placeholders as described
 * below.
 * 
 * ### Placeholders
 * 
 * Placeholders can be used within custom SQL data addresses to include reuse other parts of the
 * model or include runtime information of the query builder like the current set of filters. They 
 * will be replaced by their values when the query is built, so the data source will never get to 
 * see them.
 * 
 * #### Object-level placeholders
 * 
 * On object level the `[#~alias#]` placeholder will be replaced by the alias of
 * the current object. This is especially useful to prevent table alias collisions
 * in custom subselects:
 * 
 * `(SELECT mt_[#~alias#].my_column FROM my_table mt_[#~alias#] WHERE ... )`
 * 
 * This way you can control which uses of my_table are unique within the
 * generated SQL.
 * 
 * You can also use placeholders for filters like in many other query builders:
 * e.g. `[#my_attribute_alias#]` for the value of a filter on the 
 * `attribute my_attribute_alias` of the current object - making it a
 * mandatory filter).
 * 
 * #### Attribute-level placeholders
 * 
 * On attribute level any other attribute alias can be used as placeholder
 * additionally to `[#~alias#]`. Thus, attribute addresses can be reused. This
 * is handy if an attribute builds upon other attributes. E.g. a percentage
 * would be an attribute being calculated from two other attributes. This can
 * easily be done via attribute placeholders in it's data address:
 * 
 * `([#RELATION_TO_OBJECT1__ATTRIBUTE1#]/[#RELATION_TO_OBJECT2__ATTRIBUTE2#])`
 * 
 * You can even use relation paths here! It will even work if the placeholders
 * point to attributes, that are based on custom SQL statements themselves.
 * Just keep in mind, that these expressions may easily become complex and
 * kill query performance if used carelessly.
 * 
 * ### Multi-dialect data addresses
 * 
 * If an app is meant to run on different database engines, custom SQL addresses may
 * require engine-specific syntax. In this case, dialect tags like `@T-SQL:` or `@PL/SQL:`
 * can be used to define variants of SQL statements in a single address field.
 * 
 * Here is an example from the `exface.Core.QUEUED_TASK` object, which uses
 * JSON function with different syntax in MySQL and Microsoft's T-SQL:
 * 
 * ```
 *  |@MySQL: JSON_UNQUOTE(JSON_EXTRACT([#~alias#].task_uxon, '$.action'))
 *  |@T-SQL: JSON_VALUE([#~alias#].task_uxon, '$.action')
 *  |
 * ```
 * 
 * Multi-dialect statements MUST start with an `@`. Every dialect-tag (e.g. `@T-SQL:`) 
 * MUST be placed at the beginning of a new line (illustrated by the pipes in the example
 * above - don't actually use the pipes!). Everything until the next dialect-tag or the end of the field is considered to 
 * be the data address in this dialect. 
 * 
 * Every SQL query builder supports one or more dialects listed in the respective
 * documentation: e.g. a MariaDB query builder would support `@MariaDB:` and `@MySQL`.
 * Should a data address contain multiple supported dialects, the query builder will 
 * use it's internal priority to select the best fit.
 * 
 * The default dialect-tag and `@OTHER:` can be used to define a fallback for all
 * dialects not explicitly addressed.
 * 
 * ### JSON support
 * 
 * You can use JSON path expressions in data addresses to access data inside JSON columns: e.g.
 * `myColumn::$.prop1` will select the value of `prop1` saved in a JSON inside `myColumn`.
 * 
 * Most SQL databases support JSON path queries to access data inside JSON columns. However, every
 * SQL dialect has its own syntax - typically functions like `JSON_VALUE(column, jsonPath)` or
 * `JSON_SET(column, jsonPath, value)` or similar. The common syntax introduced above will be
 * automatically translated into these JSON functions by the query builder.
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractSqlBuilder extends AbstractQueryBuilder
{
    /**
     * Custom where statement automatically appended to direct selects for this object (not if the object's table is joined!).
     * 
     * Useful for generic tables, where different meta objects are stored and
     * distinguished by specific keys in a special column. The value of
     * `SQL_SELECT_WHERE` should contain the `[#~alias#]` placeholder: e.g.
     * `[#~alias#].mycolumn = 'myvalue'`.
     * 
     * You can also use attribute aliases as placeholders: e.g. `[#MY_ATTRIBUTE#]`
     * or even `[#RELATION__RELATED_ATTRIBUTE#]`. Keep in mind, that using relations
     * will produce JOINs or subselects in the resulting SQL, which will ALWAYS
     * be the case if they are used in parts of the data address of the object.
     *
     * @uxon-property SQL_SELECT_WHERE
     * @uxon-target object
     * @uxon-type string
     */
    const DAP_SQL_SELECT_WHERE = 'SQL_SELECT_WHERE';
    
    /**
     * Custom SQL to use in FROM statements.
     * 
     * Use a custom SELECT here and a table name as the data address to write to the table
     * directly while selecting from some complex view-like statement.
     *
     * @uxon-property SQL_READ_FROM
     * @uxon-target object
     * @uxon-type string
     */
    const DAP_SQL_READ_FROM = 'SQL_READ_FROM';
    
    /**
     * Tells the query builder what type the SQL column has.
     * 
     * This is only needed for complex types that require conversion: e.g. binary,
     * LOB, etc. Refer to the description of the specific query builder for concrete
     * usage instructions.
     *
     * @uxon-property SQL_DATA_TYPE
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_DATA_TYPE = 'SQL_DATA_TYPE';
    const DAP_SQL_DATA_TYPE_BINARY = 'binary';
    const DAP_SQL_DATA_TYPE_JSON = 'json';
    
    /**
     * Defines a custom time zone for a datetime or time column if it differs from the connection setting
     * 
     * When reading from such a column, the workbench will convert the value to the server time
     * zone if the time zones differ. When writing, it will do the opposite. If no `SQL_TIME_ZONE`
     * is set, the time zone of the data connection will be used.
     *
     * @uxon-property SQL_TIME_ZONE
     * @uxon-target attribute
     * @uxon-type timezone
     */
    const DAP_SQL_TIME_ZONE = 'SQL_TIME_ZONE';
    
    /**
     * Custom SQL SELECT clause for this attribute. 
     * 
     * It replaces the entire select generator and will be used as-is except for replacing placeholders. 
     * The placeholder `[#~alias#]` is supported as well as placeholders for other attributes.
     * This is usefull to write wrappers for columns (e.g. `NVL([#~alias#].MY_COLUMN, 0)`.
     * If the wrapper is placed here, the data address would remain writable, while
     * replacing the column name with a custom SQL statement in the data address itself,
     * would cause an SQL error when writing to it (unless `SQL_UPDATE` and `SQL_INSERT`
     * are used, of course). Note, that since this is a complete replacement, the
     * table to select from must be specified manually or via `[#~alias#]` placeholder.
     *
     * @uxon-property SQL_SELECT
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_SELECT = 'SQL_SELECT';
    
    /**
     * Replaces the data address for SELECT queries.
     * 
     * In contrast to `SQL_SELECT`, this property will be processed by the generator
     * just like a data address would be (including all placeholders). In particular,
     * the table alias will be generated automatically, while in `SQL_SELECT` it
     * must be defined by the user.
     *
     * @uxon-property SQL_SELECT_DATA_ADDRESS
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_SELECT_DATA_ADDRESS = 'SQL_SELECT_DATA_ADDRESS';
    
    /**
     * Replaces the ON-part for JOINs generated from this attribute.
     * 
     * This only works for attributes, that represent a forward (n-1) relation! The
     * option supports these placeholders: 
     * - `[#~left_alias#]` or `[#~right_alias#]` - alias of the table on left/right side of the JOIN
     * - `[#~left:MYATTR#]` or `[#~right:MYATTR#]` - alias of any attribute of the left/right object
     * 
     * ## Examples
     *
     * - `[#~left:PAGE#] = [#~right:UID#] AND [#~right:IN_MENU#] = 1` - JOIN on an attribute with an additional
     * filter - will only JOIN pages, that are shown in the menu in this case
     * - `[#~left:PAGE#] = [#~right:UID#] AND [#~right:PARENT__PUBLISHED#] = 1` - JOIN with additional condition
     * using relations - will only JOIN pages where the parent is published
     * - `[#~left_alias#].col1 = [#~right_alias#].col3 AND [#~left_alias#].col2 = [#~right_alias#].col4` - JOIN on two
     * two different columns
     * - `[#~left_alias#].col1 = [#~right_alias#].col2 AND [#~right_alias#].status > 0` - JOIN on a column with an
     *  additional filter
     *
     * @uxon-property SQL_JOIN_ON
     * @uxon-target attribute
     * @uxon-type string
     * @uxon-template [#~left_alias#].col1 = [#~right_alias#].col3
     * 
     * @uxon-placeholder [#~left_alias#]
     * @uxon-placeholder [#~right_alias#]
     * @uxon-placeholder [#~left:<metamodel:attribute>#]
     * @uxon-placeholder [#~right:<metamodel:attribute>#]
     */
    const DAP_SQL_JOIN_ON = 'SQL_JOIN_ON';
    
    /**
     * Custom SQL INSERT statement used instead of the value - typically a wrapper for the value.
     * 
     * The placeholders `[#~alias#]` and `[#~value#]` are supported in addition to
     * attribute placeholders. This is usefull to write wrappers for values
     * (e.g. `to_clob('[#~value#]')` to save a string value to an Oracle CLOB column)
     * or generators (e.g. you could use `UUID()` in MySQL to have a column always created
     * with a UUID). If you need to use a generator only if no value is given explicitly,
     * use something like this: `IF([#~value#]!='', [#~value#], UUID())`.
     * 
     * NOTE: if you use a custom `SQL_INSERT` to generate a primary key, you generate it
     * into a variable using `SQL_INSERT_BEFORE` place the variable into `SQL_INSERT` and
     * select that variable in `SQL_INSERT_AFTER`. It should now be correctly returned by
     * the query builder.
     * 
     * Here is an example of the use of an ID-table in MS SQL. Note the `@insertId` being
     * selected with the name of the primary key column at the end.
     * 
     * ```
     *  {
     *      "SQL_INSERT": "@insertedId",
     *      "SQL_INSERT_BEFORE": "DECLARE @insertedId int; EXEC generator; SELECT @insertedId = ID FROM ...;",
     *      "SQL_INSERT_AFTER": "SELECT @insertedId AS Id;"
     *  }
     *  
     * ```
     *
     * @uxon-property SQL_INSERT
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_INSERT = 'SQL_INSERT';
    
    /**
     * SQL statement to be executed before every INSERT - e.g. to initialize a variable used in `SQL_INSERT`
     * 
     * @uxon-property SQL_INSERT_BEFORE
     * @uxon-target attribute
     * @uxon-type string
     * 
     * @var string
     */
    const DAP_SQL_INSERT_BEFORE = 'SQL_INSERT_BEFORE';
    
    /**
     * SQL statement to be executed after every INSERT - e.g. to deal with a variable used in `SQL_INSERT`
     *
     * @uxon-property SQL_INSERT_AFTER
     * @uxon-target attribute
     * @uxon-type string
     *
     * @var string
     */
    const DAP_SQL_INSERT_AFTER = 'SQL_INSERT_AFTER';
    
    /**
     * Replaces the data address for INSERT queries.
     *
     * @uxon-property SQL_INSERT_DATA_ADDRESS
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_INSERT_DATA_ADDRESS = 'SQL_INSERT_DATA_ADDRESS';
    
    /**
     * Set to TRUE to generate a UUID on INSERT that is optimized for column indexing (recommended for UUID primary keys)
     * 
     * If you need UUIDs as primary keys, you can use this built-in generator, that reorders a
     * natively generated time-based UUID in a way, that it is more-or-less sequential (later
     * generated UUIDs are larger when compared to previous ones) - this makes them better
     * suitable for indexing in SQL tables.
     *
     * @uxon-property SQL_INSERT_UUID_OPTIMIZED
     * @uxon-target attribute
     * @uxon-type boolean
     */
    const DAP_SQL_INSERT_UUID_OPTIMIZED = 'SQL_INSERT_UUID_OPTIMIZED';
    
    /**
     * Custom SQL for UPDATE statements to use instead of the value - typically some wrapper for the value. 
     * 
     * The `SQL_UPDATE` property is usefull to write wrappers for values (e.g.
     * `to_clob('[#~value#]')` to save a string value to an Oracle CLOB column) or
     * generators (e.g. you could use `NOW()` in MySQL to have a column always updated
     * with the current date). If you need to use a generator only if no value is given
     * explicitly, use something like this: `IF([#~value#]!='', [#~value#], UUID())`.
     * 
     * `SQL_UPDATE` supports the placeholders `[#~alias#]` and `[#~value#]` in addition to 
     * placeholders for other attributes.
     *
     * @uxon-property SQL_UPDATE
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_UPDATE = 'SQL_UPDATE';
    
    /**
     * Replaces the data address for UPDATE queries.
     *
     * @uxon-property SQL_UPDATE_DATA_ADDRESS
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_UPDATE_DATA_ADDRESS = 'SQL_UPDATE_DATA_ADDRESS';
    
    /**
     * An entire custom WHERE clause with place with static placeholders `[#~alias#]` and `[#~value#]`. 
     * 
     * It is particularly usefull for attribute with custom SQL in the data address, that you 
     * do not want to calculate within the WHERE clause: e.g. if you have an attribute, which 
     * concatenates `col1` and `col2` via SQL, you could use the following: 
     * 
     * `SQL_WHERE`: `([#~alias#].col1 LIKE '[#~value#]%' OR [#~alias#].col2 LIKE '[#~value#]%')`
     * 
     * However, this property has a major drawback: the comparator is being hardcoded. Use 
     * `SQL_WHERE_DATA_ADDRESS` instead, unless you really require multiple columns.
     *
     * @uxon-property SQL_WHERE
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_WHERE = 'SQL_WHERE';
    
    /**
     * Replaces the data address in the WHERE clause.
     * 
     * The comparator and the value will added automatically be the generator.
     * Supports the `[#~alias#]` placeholder in addition to placeholders for other
     * attributes. This is usefull to write wrappers to be used in filters: e.g.
     * `NVL([#~alias#].MY_COLUMN, 10)` to change comparing behavior of NULL values.
     *
     * @uxon-property SQL_WHERE_DATA_ADDRESS
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_WHERE_DATA_ADDRESS = 'SQL_WHERE_DATA_ADDRESS';
    
    /**
     * A custom ORDER BY clause. 
     * 
     * This option currently does not support any placeholders!
     *
     * @uxon-property SQL_ORDER_BY
     * @uxon-target attribute
     * @uxon-type string
     */
    const DAP_SQL_ORDER_BY = 'SQL_ORDER_BY';
    
    const OPERATION_READ = 'read';
    
    const OPERATION_WRITE = 'write';

    // Config
    
    // Reserved (forbidden) words in SQL in general
    // @see https://en.wikipedia.org/wiki/List_of_SQL_reserved_words
    private $reserved_words = ['ALL', 'AS', 'CHECK', 'COLUMN', 'CREATE', 'DEFAULT', 'DISTINCT', 'ELSE', 'FOR', 'FROM', 'GRANT', 'GROUP', 'HAVING', 'IN', 'INTO', 'IS', 'LIKE', 'NOT', 'NULL', 'ON', 'OR', 'ORDER', 'SELECT', 'TABLE', 'THEN', 'TO', 'UNION', 'UNIQUE', 'WHERE', 'WITH'];
    
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
    
    private $query_id = null;
    
    private $subquery_counter = 0;
    
    private $customFilterSqlPredicates = [];
    
    private $dirtyFlag = false;
    
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
    
    /**
     * Function to build an sql UPDATE query with the given SET and WHERE parts.
     *
     * @param string $sqlSet
     * @param string $sqlWhere
     * @return string
     */
    public function buildSqlQueryUpdate(string $sqlSet, string $sqlWhere) : string
    {
        return 'UPDATE ' . $this->buildSqlFrom(static::OPERATION_WRITE) . $sqlSet . $sqlWhere;
    }
    
    /**
     * Function to build an sql DELETE query with the given WHERE part.
     *
     * E.g. `DELETE FROM $this->buildSqlFrom() $where`
     *
     * @param string $sqlWhere
     * @param string $joins
     * @return string
     */
    public function buildSqlQueryDelete(string $sqlWhere, string $joins = null) : string
    {
        return 'DELETE FROM ' . $this->buildSqlFrom(static::OPERATION_WRITE) . ($joins ?? '') . $sqlWhere;
    }
    
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $query = $this->buildSqlQuerySelect();
        if (! empty($this->getAttributes())) {
            $q = new SqlDataQuery();
            $q->setSql($query);
            // first do the main query
            $qr = $data_connection->query($q);
            $rows = $this->getReadResultRows($qr);
            // If the query already includes a total row counter, use it!
            $result_total_count = $qr->getResultRowCounter();
            $qr->freeResult();
        } else {
            $rows = [];
        }
        
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
        if (! $rows = $query->getResultArray()) {  
            return [];
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

        $tzWorkbench = DateTimeDataType::getTimeZoneDefault($this->getWorkbench());
        $tzQuery = $query->getTimeZone();
        $rowCnt = count($rows);
        foreach ($this->getAttributes() as $qpart) {
            $dataType = $qpart->getDataType();
            $colKey = $qpart->getColumnKey();
            switch (true) {
                // Remove explicitly excluded query parts
                case ($qpart instanceof QueryPartSelect) && $qpart->isExcludedFromResult() === true:
                    foreach ($rows as $nr => $row) {
                        unset ($rows[$nr][$colKey]);
                    }
                    break;
                // Convert binary values if needed
                case $this->isBinaryColumn($qpart):
                    if ($qpart->hasAggregator()) {
                        $aggr = $qpart->getAggregator();
                        switch ($aggr->getFunction()->__toString()) {
                            case AggregatorFunctionsDataType::COUNT:
                            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                            case AggregatorFunctionsDataType::LIST_ALL:
                            case AggregatorFunctionsDataType::LIST_DISTINCT:
                                break 2;
                        }
                    }
                    foreach ($rows as $nr => $row) {
                        $rows[$nr][$colKey] = $this->decodeBinary($row[$colKey]);
                    }
                    break;
                // Parse times and apply time zones
                case $dataType instanceof TimeDataType && ($dataType->isTimeZoneDependent() === true) && null !== $tz = $this->getTimeZoneInSQL($tzWorkbench, $tzQuery, $qpart->getDataAddressProperty(self::DAP_SQL_TIME_ZONE)):
                    for ($i = 0; $i < $rowCnt; $i++) {
                        $rows[$i][$colKey] = $dataType::cast($rows[$i][$colKey]);
                        $rows[$i][$colKey] = $dataType::convertTimeZone($rows[$i][$colKey], $tz, $tzWorkbench);
                    }
                    break;
                // Parse dates
                case $dataType instanceof DateTimeDataType && null !== $tz = $this->getTimeZoneInSQL($tzWorkbench, $tzQuery, $qpart->getDataAddressProperty(self::DAP_SQL_TIME_ZONE)):
                    for ($i = 0; $i < $rowCnt; $i++) {
                        $rows[$i][$colKey] = $dataType::cast($rows[$i][$colKey], false, $tz, false);
                    }
                    break;
                // Handle compound attributes
                case $qpart->isCompound() && $qpart->getAttribute() instanceof CompoundAttributeInterface: 
                    $qpartChilds = $qpart->getCompoundChildren();
                    $qpartChildFirstInDB = null;
                    $qpartChildsFormulas = [];
                    foreach ($qpartChilds as $i => $compQpart) {
                        $compAttr = $compQpart->getAttribute();
                        if ($compAttr->hasCalculation()) {
                            if ($compAttr->getCalculationExpression()->isStatic()) {
                                $qpartChildsFormulas[$i] = $compAttr->getCalculationExpression();
                            } else {
                                throw new QueryBuilderException('Cannot read compound attribute ' . $compAttr->__toString() . ' via SQL: compounds with calculated non-static components are not supported!');
                            }
                        } elseif ($qpartChildFirstInDB === null) {
                            $qpartChildFirstInDB = $compQpart;
                        }
                    }
                    foreach ($rows as $nr => $row) {
                        $compValues = [];
                        if ($qpart->hasAggregator() === true) {
                            switch ($qpart->getAggregator()->getFunction()->__toString()) {
                                // Counting compounds is fairly easy - just count the first
                                // part coming from the DB (= not calculated)
                                case AggregatorFunctionsDataType::COUNT:
                                    $rows[$nr][$qpart->getColumnKey()] = $row[$qpartChildFirstInDB->getColumnKey()];
                                    unset ($rows[$nr][$compQpart->getColumnKey()]);
                                    break;
                                default:
                                    throw new RuntimeException('Cannot read compound attributes with aggregator' . $this->getAggregator()->exportString() . '!');
                            }
                        } else {
                            foreach ($qpartChilds as $i => $compQpart) {
                                if (array_key_exists($i, $qpartChildsFormulas)) {
                                    $compValues[] = $qpartChildsFormulas[$i]->evaluate();
                                } else {
                                    $compValues[] = $row[$compQpart->getColumnkey()];
                                }
                            }
                            $rows[$nr][$qpart->getColumnKey()] = $qpart->getAttribute()->mergeValues($compValues);
                        }
                    }
                    break;
            }
        }
        
        return $rows;
    }
    
    /**
     * Returns the time zone to be used in an SQL statement depending to the time zone settings of the server, the data
     * connection an eventually the data address property `SQL_TIME_ZONE` of an attribute.
     * 
     * Returns NULL if no time zone conversion is neccessary
     * 
     * @param string $tzWorkbench
     * @param string|NULL $tzConnection
     * @param string|NULL $tzColumn
     * @return string|NULL
     */
    protected function getTimeZoneInSQL(string $tzWorkbench, string $tzConnection = null, string $tzColumn = null) : ?string
    {
        switch (true) {
            case $tzColumn !== null:
                if (strcasecmp($tzColumn, $tzWorkbench) === 0) {
                    return null;
                }
                return $tzColumn;
            case $tzConnection !== null: 
                if (strcasecmp($tzConnection, $tzWorkbench) === 0) {
                    return null;
                }
                return $tzConnection;
        }
        return null;
    }
    
    /**
     * Checks if writing operations (create, update, delete) are possible for the current query.
     *
     * @return boolean
     */
    protected function isWritable()
    {
        $result = true;
        $addr = $this->buildSqlDataAddress($this->getMainObject(), static::OPERATION_WRITE);
        // First of all find out, if the object's data address is empty or a view. If so, we generally can't write to it!
        if (! $addr) {
            throw new QueryBuilderException('The data address of the object "' . $this->getMainObject()->getAlias() . '" is empty. Cannot perform writing operations!');
        }
        if ($this->isSqlStatement($addr)) {
            throw new QueryBuilderException('The data address of the object "' . $this->getMainObject()->getAlias() . '" seems to be a view. Cannot write to SQL views!');
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
        if (! $this->isWritable()) {
            return new DataQueryResultData([], 0);
        }
        
        $mainObj = $this->getMainObject();
        
        $values = array();
        $columns = array();
        $valuesForJsonCols = [];
        $before_each_insert_sqls = [];
        $after_each_insert_sqls = [];
        $uidQpart = null;
        
        // add values
        $rowPlaceholders = [];
        foreach ($this->getValues() as $qpart) {
            foreach ($qpart->getValues() as $row => $value) {
                $rowPlaceholders[$row][$qpart->getAlias()] = $value;
            }
        }
        foreach ($this->getValues() as $qpart) {
            $attr = $qpart->getAttribute();
            if ($attr->getRelationPath()->toString()) {
                throw new QueryBuilderException('Cannot create attribute "' . $attr->getAliasWithRelationPath() . '" of object "' . $mainObj->getAliasWithNamespace() . '". Attributes of related objects cannot be created within the same SQL query!');
            }
            // Ignore attributes, that do not reference an sql column (= do not have a data address at all)
            $attrAddress = $this->buildSqlDataAddress($attr);
            $attrInsertAddress = $qpart->getDataAddressProperty(self::DAP_SQL_INSERT_DATA_ADDRESS);
            $custom_insert_sql = $qpart->getDataAddressProperty(self::DAP_SQL_INSERT);
            $before_each_insert_sql = $qpart->getDataAddressProperty(self::DAP_SQL_INSERT_BEFORE);
            $after_each_insert_sql = $qpart->getDataAddressProperty(self::DAP_SQL_INSERT_AFTER);
            $column = $attrAddress ? $attrAddress : $attrInsertAddress;
            if ((! $column || $this->isSqlStatement($column)) && ! $custom_insert_sql) {
                continue;
            }
            // Make sure, that attributes stored as JSON have a custom data address property telling everyone the
            // underlying column is JSON and not date/binary, etc. SQL generating functions, that do not get the
            // query part, cannot know about the JSON otherwise. 
            if ($this->isJsonDataAddress($attrAddress) && $qpart->getDataAddressProperty(self::DAP_SQL_DATA_TYPE) === null) {
                $qpart->setDataAddressProperty(self::DAP_SQL_DATA_TYPE, self::DAP_SQL_DATA_TYPE_JSON);
            }
            // Save the query part for later processing if it is the object's UID
            if ($attr->isUidForObject()) {
                $uidQpart = $qpart;
            }
            // There are cases, when system attributes like the UID are non-writable, but still present
            // (since they are system). Their values should be ignored here. For example, MS SQL Server
            // IDENTITY columns are often used as UIDs, but cannot be included in CREATE or UPDATE queries.
            if ($attr->isWritable() === false) {
                continue;
            }
            
            // Prepare arrays with column aliases and values to implode them later when building the query
            // Make sure, every column is only addressed once! So the keys of both array actually need to be the column aliases
            // to prevent duplicates
            $columnIsJson = $this->isJsonDataAddress($column) && ! $custom_insert_sql;
            if ($columnIsJson === true) {
                list($column, $jsonPath) = $this->parseJsonDataAddress($column);
            } 
            
            $columns[$column] = $column;
            
            foreach ($qpart->getValues() as $row => $value) {
                try {
                    $value = $this->prepareInputValue($value, $qpart->getDataType(), $qpart->getDataAddressProperties());
                } catch (\Throwable $e) {
                    throw new QueryBuilderException('Invalid value for "' . $qpart->getAlias() . '" on row ' . $row . ' of CREATE query for "' . $this->getMainObject()->getAliasWithNamespace() . '": ' . StringDataType::truncate($value, 100, false), null, $e);
                }

                // If the value is to be put into a JSON inside a column, store the prepared value in a separate
                // array for later use and skip further processing for this value.
                if ($columnIsJson === true) {
                    if ($value === "''") {
                        $value = null;
                    }
                    $valuesForJsonCols[$row][$column][$jsonPath] = $value;
                    continue;
                }

                $phs = array_merge(['~alias' => $mainObj->getAlias(), '~value' => $value], $rowPlaceholders[$row]);
                if ($custom_insert_sql) {
                    // If there is a custom insert SQL for the attribute, use it
                    // NOTE: if you just write some kind of generator here, it
                    // will make it impossible to save values passed to the query
                    // via setValues() - they will always be replaced by the
                    // custom SQL. To allow explicitly set values too, the
                    // INSERT_SQL must include something like IF('[#~value#]'!=''...)
                    $insert_sql = $this->replacePlaceholdersInSqlAddress($custom_insert_sql, null, $phs, $mainObj->getAlias());
                } else {
                    $insert_sql = $value;
                }

                $values[$row][$column] = $insert_sql;
                
                if ($before_each_insert_sql) {
                    $before_each_insert_sqls[$row] .= $this->replacePlaceholdersInSqlAddress($before_each_insert_sql, null, $phs, $mainObj->getAlias());
                }
                
                if ($after_each_insert_sql) {
                    $after_each_insert_sqls[$row] .= $this->replacePlaceholdersInSqlAddress($after_each_insert_sql, null, $phs, $mainObj->getAlias());
                }
            }
        }
        
        // If there is no UID column, but the UID attribute has a custom insert statement, add it at this point manually
        // This is important because the UID will mostly not be marked as a mandatory attribute in order to preserve the
        // possibility of mixed creates and updates among multiple rows. But an empty non-required attribute will never
        // show up as a value here. Still that value is required!
        if ($mainObj->hasUidAttribute()) {
            $uidAttr = $mainObj->getUidAttribute();
            $uidIsOptimizedUUID = BooleanDataType::cast($uidAttr->getDataAddressProperty(self::DAP_SQL_INSERT_UUID_OPTIMIZED));
            $uidCustomSqlInsert = $uidAttr->getDataAddressProperty(self::DAP_SQL_INSERT);
            // Add before and after queries of the UID attribute if they were not added for
            // the respective query part above already
            if (! $uidQpart) {
                $uidBeforeEach = $uidAttr->getDataAddressProperty(self::DAP_SQL_INSERT_BEFORE);
                if ($uidBeforeEach) {
                    $uidBeforeEach = StringDataType::replacePlaceholders($uidBeforeEach, [
                        '~alias' => $mainObj->getAlias(),
                        '~value' => $this->prepareInputValue('', $uidAttr->getDataType(), $uidAttr->getDataAddressProperties()->toArray())
                    ]);
                }
                $uidAfterEach = $uidAttr->getDataAddressProperty(self::DAP_SQL_INSERT_AFTER);
                if ($uidAfterEach) {
                    $uidAfterEach = StringDataType::replacePlaceholders($uidAfterEach, [
                        '~alias' => $mainObj->getAlias(),
                        '~value' => $this->prepareInputValue('', $uidAttr->getDataType(), $uidAttr->getDataAddressProperties()->toArray())
                    ]);
                }
            }
            if ($uidCustomSqlInsert === '') {
                $uidCustomSqlInsert = null;
            }
        } else {
            $uidIsOptimizedUUID = false;
            $uidCustomSqlInsert = null;
        }
        if ($uidQpart === null && ($uidIsOptimizedUUID == true || $uidCustomSqlInsert)) {
            $uidQpart = $this->addValue($uidAttr->getAlias(), null);
            $uidAddress = $this->buildSqlDataAddress($uidQpart);
            $columns[$uidAddress] = $uidAddress;
        }
        if ($uidQpart) {
            $uidAddress = $this->buildSqlDataAddress($uidQpart);
        }
        
        if ($uidIsOptimizedUUID && $uidCustomSqlInsert) {
            throw new QueryBuilderException('Invalid SQL data address configuration for UID of object "' . $mainObj->getAliasWithNamespace() . '": Cannot use SQL_INSERT and SQL_INSERT_UUID_OPTIMIZED at the same time!');
        }
        
        // If the UID query part has a custom SQL insert statement, render it here and make sure it's saved
        // into a variable because all sorts of last_insert_id() function will not return such a value.
        $uidValuesProvided = $uidQpart !== null && $uidQpart->hasValues();
        if ($uidQpart && $uidValuesProvided === false && $uidCustomSqlInsert) {
            $uidCustomSqlInsert = StringDataType::replacePlaceholders($uidCustomSqlInsert, [
                '~alias' => $mainObj->getAlias(),
                '~value' => $this->prepareInputValue('', $uidQpart->getDataType(), $uidQpart->getDataAddressProperties())
            ]);
            
            $columns[$uidAddress] = $uidAddress;
            foreach ($values as $nr => $row) {
                $values[$nr][$uidAddress] = $uidCustomSqlInsert;
            }
        }
        
        $insertedIds = [];
        $insertedCounter = 0;

        // We now have the following prepared data:
        // - $values - array of rows, where each row is an array with SQL column for keys and their respective prepared values
        // - $columns - array of SQL column names, that need to be updated - each key in a row inside $values should be in this array
        // - $valuesForJsonCols - data for JSON columns: [rowNo => [JSONcol1 => [JSONpath1 => val1, JSONpath2 => val2], JSONcol2 => []]]
        // FIXME how to handle JSON data here? 
        // - create a JSON in PHP and put it into the $values/$columns?
        // - use native SQL functions to update the JSON after the regular INSERT?
        foreach ($values as $rowIdx => $row) {
            // See if a UID (primary key) is provided
            $customUid = null;
            if ($uidQpart !== null) {
                switch (true) {
                    // If optimized uids should be used, build them here and add them to the row.
                    // Remember the value to ensure it is returned as the created UID later.
                    case $uidValuesProvided === false && $uidIsOptimizedUUID === true:
                        $customUid = UUIDDataType::generateSqlOptimizedUuid();
                        $row[$uidAddress] = $customUid;
                        break;
                    // If there is a non-empty UID value provided AND it is not an SQL statement, use
                    // that value directly
                    case $uidValuesProvided === true && $row[$uidAddress] !== null && $row[$uidAddress] !== '' && $this->isSqlStatement($uidAddress) === false:
                        $customUid = $row[$uidAddress];
                        break;
                }
            }

            $jsonsForRow = $valuesForJsonCols[$rowIdx];
            $output = [];
            foreach ($columns as $column) {
                if(!empty($jsonsForRow) && key_exists($column, $jsonsForRow)) {
                    $output[$column] = $this->buildSqlJsonEncodeAsFlat($jsonsForRow[$column]);
                } else {
                    $output[$column] = $row[$column];
                }
            }
            
            $insertColumns = implode(', ', $columns);
            $insertValues = implode(',', $output);
            $sql = 'INSERT INTO ' . $this->buildSqlDataAddress($mainObj, static::OPERATION_WRITE) . ' (' . $insertColumns . ') VALUES (' . $insertValues . ')';
            
            $beforeSql = $before_each_insert_sqls[$rowIdx] . ($uidBeforeEach ?? '');
            $afterSql = $after_each_insert_sqls[$rowIdx] . ($uidAfterEach ?? '');
            if ($beforeSql || $afterSql) {
                $query = $data_connection->runSql($beforeSql . $sql . '; ' . $afterSql, true);
                if ($uidAddress && $customUid === null && $rRow = $query->getResultArray()[0]) {
                    if ($rRow[$uidAddress] !== null) {
                        $customUid = $query->getResultArray()[0][$uidAddress];
                    }
                }
            } else {            
                $query = $data_connection->runSql($sql);
            }
            
            // Now get the primary key of the last insert. If it was not provided by PHP, assume it to be 
            // an auto-increment by means of the database.
            if ($customUid !== null) {
                $insertedId = $customUid;
            } else {
                // If the primary key was autogenerated by the database, fetch it via built-in function
                $insertedId = $query->getLastInsertId();
            }


            // Remember inserted ids for this row if it really was inserted
            if ($cnt = $query->countAffectedRows()) {
                $insertedCounter += $cnt;
                $uidColKey = null;
                // If there was a UID part in the query, use that. But even if the UID was not explicitly
                // part of the query, but the objecdt HAS a UID, add a UID column to the result
                if ($uidQpart) {
                    $uidColKey = $uidQpart->getColumnKey();
                    $uidAttr = $uidQpart->getAttribute();
                } elseif ($this->getMainObject()->hasUidAttribute()) {
                    $uidAttr = $this->getMainObject()->getUidAttribute();
                    $uidColKey = DataColumn::sanitizeColumnName($uidAttr->getAlias());
                }
                // Get the values for the UID either from the last inserted id provided by the SQL
                // engine or derive it from the data in case of compound attributes.
                if ($uidColKey !== null) {
                    // If we have a compound attribute, we
                    if ($uidAttr instanceof CompoundAttributeInterface) {
                        $compVals = [];
                        foreach ($uidAttr->getComponents() as $uidComp) {
                            $uidCompQpart = $this->getValue($uidComp->getAttribute()->getAliasWithRelationPath());
                            // IDEA what if one of the UID parts is not even part of the query? It should not really
                            // happen, because the UID is supposed to be the primary key, but what if? Currently
                            // we just set the UID value for this row to null if at least one of its parts is missing.
                            if (! $uidCompQpart) {
                                $insertedIds[] = [$uidColKey => null];
                                continue 2;
                            }
                            $compVals[] = $uidCompQpart->getValues()[$rowIdx];
                        }
                        $insertedIds[] = [$uidColKey => $uidAttr->mergeValues($compVals)];
                    } else {
                        if ($uidColKey !== null) {
                            $insertedIds[] = [$uidColKey => $insertedId];
                        }
                    }
                }
            }
            
            $query->freeResult();
        }
        
        // IDEA do bulk inserts instead of separate queries. The problem is:
        // there seems to be no easy way to get all the insert ids of a bulk
        // insert. The code below worked but only returned the first id.
        // Perhaps, some possibility will be found in future.
        
        /*foreach ($values as $rowIdx => $row) {
         foreach ($row as $val) {
         $values[$rowIdx] = implode(',', $row);
         }
         }
         $sql = 'INSERT INTO ' . $mainObj->getDataAddress() . ' (' . implode(', ', $columns) . ') VALUES (' . implode('), (', $values) . ')';
         $query = $data_connection->runSql($sql);
         
         // Now get the primary key of the last insert.
         if ($last_uid_sql_var) {
         // If the primary key was a custom generated one, it was saved to the corresponding SQL variable.
         // Fetch it from the data base
         $last_id = reset($data_connection->runSql('SELECT ' . $this->buildSqlSelectBinaryAsHEX($last_uid_sql_var))->getResultArray()[0]);
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
        if (! $this->isWritable()) {
            return new DataQueryResultData([], 0);
        }
            
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
        $jsonColumnsByFilter = array();
        // Array of SET statements to update multiple values per attribute. They will be used to build one UPDATE statement per UID value
        // [ uid_value => [ data_address => 'data_address = value' ] ]
        $updates_by_uid = array();
        $jsonColumnsByUid = array();
        // Array of query parts to be placed in subqueries
        $subqueries_qparts = array();
        foreach ($this->getValues() as $qpart) {
            $attr = $qpart->getAttribute();
            if ($attr->getRelationPath()->toString()) {
                $subqueries_qparts[] = $qpart;
                continue;
            }
            
            $attrAddress = $this->buildSqlDataAddress($attr);
            // Ignore attributes, that do not reference an sql column (or do not have a data address at all)
            if (! $qpart->getDataAddressProperty(self::DAP_SQL_UPDATE) && ! $qpart->getDataAddressProperty(self::DAP_SQL_UPDATE_DATA_ADDRESS) && $this->isSqlStatement($attrAddress)) {
                continue;
            }
            // Make sure, that attributes stored as JSON have a custom data address property telling everyone the
            // underlying column is JSON and not date/binary, etc. SQL generating functions, that do not get the
            // query part, cannot know about the JSON otherwise. 
            if ($this->isJsonDataAddress($attrAddress) && $qpart->getDataAddressProperty(self::DAP_SQL_DATA_TYPE) === null) {
                $qpart->setDataAddressProperty(self::DAP_SQL_DATA_TYPE, self::DAP_SQL_DATA_TYPE_JSON);
            }
            
            if ($qpart->getDataAddressProperty(self::DAP_SQL_UPDATE_DATA_ADDRESS)){
                $column = str_replace('[#~alias#]', $table_alias, $qpart->getDataAddressProperty(self::DAP_SQL_UPDATE_DATA_ADDRESS));
            } else {
                $column = $table_alias . $this->getAliasDelim() . $attrAddress;
            }
            
            $custom_update_sql = $qpart->getDataAddressProperty(self::DAP_SQL_UPDATE);
            
            // If there is only a single row and there is no UID for it, it will become an update-by-filter
            // Otherwise we will do an UPDATE with a WHERE over the UID-column
            if (count($qpart->getValues()) == 1 && (! $qpart->hasUids() || '' === $qpart->getUids()[array_keys($qpart->getValues())[0] ?? null] ?? '')) {
                $values = $qpart->getValues();
                $value = reset($values);
                try {
                    $value = $this->prepareInputValue($value, $qpart->getDataType(), $qpart->getDataAddressProperties());
                } catch (\Throwable $e) {
                    throw new QueryBuilderException('Invalid value for "' . $qpart->getAlias() . '" on row 0 of UPDATE query for "' . $this->getMainObject()->getAliasWithNamespace() . '": ' . $value, null, $e);
                }
                if ($custom_update_sql) {
                    // If there is a custom update SQL for the attribute, use it ONLY if there is no value
                    // Otherwise there would not be any possibility to save explicit values
                    $updates_by_filter[] = $column . ' = ' . $this->replacePlaceholdersInSqlAddress($custom_update_sql, null, ['~alias' => $table_alias, '~value' => $value], $table_alias);
                } else {
                    if($this->isJsonDataAddress($column)) {
                        list($column, $jsonPath) = $this->parseJsonDataAddress($column);
                        $jsonColumnsByFilter[$column][$jsonPath] = $value;
                    } else {
                        $updates_by_filter[] = $column . ' = ' . $value;
                    }
                }
            } else {
                // TODO check, if there is an id for each value. Those without ids should be put into another query to make an insert
                // $cases = '';
                if (count($qpart->getUids()) == 0) {
                    throw new QueryBuilderException('Cannot update attribute "' . $qpart->getAlias() . "': no UIDs for rows to update given!");
                }
                // There are cases, when system attributes like the UID are non-writable, but still present
                // (since they are system). Their values should be ignored here. For example, MS SQL Server
                // IDENTITY columns are often used as UIDs, but cannot be included in CREATE or UPDATE queries.
                if ($qpart->getAttribute()->isWritable() === false) {
                    continue;
                }
                
                foreach ($qpart->getValues() as $row_nr => $value) {
                    try {
                        $value = $this->prepareInputValue($value, $qpart->getDataType(), $qpart->getDataAddressProperties());
                    } catch (\Throwable $e) {
                        throw new QueryBuilderException('Invalid value for "' . $qpart->getAlias() . '" on row ' . $row_nr . ' of SQL UPDATE query for "' . $this->getMainObject()->getAliasWithNamespace() . '": ' . $value, null, $e);
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
                        if($this->isJsonDataAddress($column)) {
                            // Remove the JSON property if the value is empty!
                            if ($value === "''" || $value === 'null') {
                                $value = null;
                            }
                            list($jsonColumn, $jsonPath) = $this->parseJsonDataAddress($column);
                            $jsonColumnsByUid[$qpart->getUids()[$row_nr]][$jsonColumn][$jsonPath] = $value;
                        } else {
                            $updates_by_uid[$qpart->getUids()[$row_nr]][$column] = $column . ' = ' . $value;
                        }
                    }
                }
                // See comment about CASE-based updates a few lines above
                // $updates_by_filter[] = $this->getShortAlias($this->getMainObject()->getAlias()) . $this->getAliasDelim() . $attr->getDataAddress() . " = CASE " . $this->getMainObject()->getUidAttribute()->getDataAddress() . " \n" . implode($cases) . " END";
            }
        }

        // Process JSON values for update by filter columns.
        foreach ($jsonColumnsByFilter as $columnName => $keyValuePairs) {
            $updates_by_filter[] = $columnName . ' = ' . $this->buildSqlJsonEncodeAsFlat($keyValuePairs, $this->buildSqlJsonInitial($columnName));
        }

        // Process JSON values for update by UID columns.
        foreach ($jsonColumnsByUid as $uid => $columns) {
            foreach ($columns as $columnName => $keyValuePairs) {
                $updates_by_uid[$uid][$columnName] = $columnName . ' = ' . $this->buildSqlJsonEncodeAsFlat($keyValuePairs, $this->buildSqlJsonInitial($columnName));
            }
        }
        
        // Execute UPDATE statements
        // First the rows, that can be updated filtering over 
        if (! empty($updates_by_uid)) {
            $uidAttr = $this->getUidAttribute() ?? ($this->getMainObject()->hasUidAttribute() ? $this->getMainObject()->getUidAttribute() : null);
            if ($uidAttr === null) {
                throw new QueryBuilderException('Cannot perform SQL update by UID: no UID attribtue or query part found!');
            }
            $uidConditionGrp = ConditionGroupFactory::createAND($this->getMainObject());
            $uidConditionGrp->addConditionFromAttribute($uidAttr, '', ComparatorDataType::IN, false);
            foreach ($updates_by_uid as $uid => $row) {
                $uidConditionGrp->getConditions()[0]->setValue($uid);
                $uidWhere = $this->buildSqlWhere(
                    QueryPartFilterGroup::createQueryPartFromConditionGroup(
                        $uidConditionGrp,
                        $this
                    )
                );
                $sql = $this->buildSqlQueryUpdate(' SET ' . implode(', ', $row), ' WHERE ' . $uidWhere);
                $query = $data_connection->runSql($sql);
                $affected_rows += $query->countAffectedRows();
                $query->freeResult();
            }
        }
        // Then those to be update filtering over other values (i.e. mass-updates without selection of specific rows)
        if (count($updates_by_filter) > 0) {
            $sql = $this->buildSqlQueryUpdate(' SET ' . implode(', ', $updates_by_filter), $where);
            $query = $data_connection->runSql($sql);
            $affected_rows = $query->countAffectedRows();
            $query->freeResult();
        }
        
        // Execute Subqueries
        foreach ($this->splitByMetaObject($subqueries_qparts) as $subquery) {
            $subquery->update($data_connection);
        }
        
        return new DataQueryResultData([], $affected_rows ?? 0);
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
            if (! $this->canReadAttribute($attr)) {
                continue;
            }
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
     * @param string[] $dataAddressProps
     * @param bool $parse
     * @return string|mixed
     */
    protected function prepareInputValue($value, DataTypeInterface $data_type, array $dataAddressProps = [], bool $parse = true)
    {
        $value = $parse ? $data_type->parse($value) : $data_type::cast($value);
        switch (true) {
            case $data_type instanceof StringDataType:
                // JSON values are strings too, but their columns should be null even if the value is an
                // empty object or empty array (otherwise the cells would never be null)
                if (($data_type instanceof JsonDataType) && $data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = $value === null ? 'NULL' : "'" . $this->escapeString($value) . "'";
                }
                break;
            case $data_type instanceof BooleanDataType:
                if ($data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = $value ? 1 : 0;
                }
                break;
            case $data_type instanceof NumberDataType:
                $value = $data_type::isValueEmpty($value) === true ? 'NULL' : $value;
                break;
            case $data_type instanceof DateTimeDataType:
            case $data_type instanceof TimeDataType:
                if ($data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    if ($data_type->isTimeZoneDependent() === true && null !== $tz = $this->getTimeZoneInSQL($data_type::getTimeZoneDefault($this->getWorkbench()), $this->getTimeZone(), $dataAddressProps[self::DAP_SQL_TIME_ZONE] ?? null)) {
                        $value = $data_type::convertTimeZone($value, $data_type::getTimeZoneDefault($this->getWorkbench()), $tz);
                    }
                    $value = "'" . $this->escapeString($value) . "'";
                }
                break;
            case $data_type instanceof DateDataType:
                if ($data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = "'" . $this->escapeString($value) . "'";
                }
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
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
        } else {
            return preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
        }
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::delete()
     */
    public function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        // filters -> WHERE + JOIN
        // NOTE: Not all DB engines allow JOINs in DELETE queries: e.g. MySQL does not. In
        // this case, override this method - see MySqlBuilder for an example.
        $joins = $this->buildSqlJoins($this->getFilters());
        $where = $this->buildSqlWhere($this->getFilters(), true);
        // add custom sql where from the object
        if ($custom_where = $this->getMainObject()->getDataAddressProperty(static::DAP_SQL_SELECT_WHERE)) {
            $where = $this->appendCustomWhere($where, $custom_where);
        }
        $where = $where ? "\n WHERE " . $where : '';
        if (! $where) {
            throw new QueryBuilderException('Cannot delete all data from "' . $this->main_object->getAlias() . '". Forbidden operation!');
        }
        
        $sql = $this->buildSqlQueryDelete($where, implode(' ', $joins));
        $query = $data_connection->runSql($sql);
        
        return new DataQueryResultData([], $query->countAffectedRows());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::count()
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result = $data_connection->runSql($this->buildSqlQueryCount());
        $cnt = $result->getResultArray()[0]['EXFCNT'];
        $result->freeResult();
        return new DataQueryResultData([], $cnt, true, $cnt);
    }
    
    /**
     * 
     * @return string
     */
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
     * @param QueryPart $qpart
     * @param string|null $select_from
     * @param string|null $select_column
     * @param string|null|bool $select_as set to false or '' to remove the "AS xxx" part completely
     * @param AggregatorInterface|bool $aggregator set to FALSE to remove grouping completely
     * @param bool $make_groupable set to TRUE to force the result to be compatible with GROUP BY
     * @param bool $addComments
     * 
     * @return string
     */
    protected function buildSqlSelect(QueryPartAttribute $qpart, $select_from = null, $select_column = null, $select_as = null, $aggregator = null, bool $make_groupable = null, $addComments = true)
    {
        $output = '';
        $comment = $addComments === false ? '' : "\n" . $this->buildSqlComment("buildSqlSelect(" . $qpart->getAlias() . ", " . $select_from . ", " . $select_as . ", " . $aggregator . ", " . $make_groupable . ")") . "\n";
        $add_nvl = false;
        $attribute = $qpart->getAttribute();
        $address = $this->buildSqlDataAddress($qpart);
        
        // skip attributes with no select (e.g. calculated from other values via formatters)
        if (! $address && ! $qpart->getDataAddressProperty(self::DAP_SQL_SELECT) && ! $qpart->getDataAddressProperty(self::DAP_SQL_SELECT_DATA_ADDRESS)) {
            return '';
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
        $make_groupable = $make_groupable ?? $this->isSubquery();
        
        // Skip reverse relations without a specific attribute (e.g. `ATTRIBUTE` of `exface.Core.ATTRIBUTE`
        // which is the reverse of `RELATION_TO_ATTRIBUTE`). SQL builders do not support nested objects
        // for now! Technically such a query would produce recursion on buildSqlSelectSubselect() -> buildSqlSelect().
        // BUT do not skip such attributes with a custom `SQL_SELECT` property! This constellation occurs
        // when filtering over non-readable relation atributes with a custom `SQL_JOIN_ON` - in this case
        // `SQL_SELECT` is automatically set to `*` and MUST be processed here regularly.
        if ($attribute->isRelation() && $aggregator === null && ! $qpart->getDataAddressProperty(self::DAP_SQL_SELECT)) {
            $rel = $this->getMainObject()->getRelation($qpart->getAlias());
            if ($rel->isReverseRelation()) {
                // TODO it would be nice to output this comment here, but this will break some statements
                // on write operations with nested sheets. The columns with subsheets for some reason try
                // to read the reverse realtion directly, which results in this comment without any SQL
                // and ultimately a broken SQL like this `SELECT field1, /* Skipping ... */, field3 FROM ...`.
                // Need to debug, why these queries include the the reverse relation as an attribute in the
                // first place. This definitely seems wrong.
                // return "\n" . $this->buildSqlComment('Skipping ' . $qpart->getAlias() . ' as reverse relation without a specific attribute');
                return '';
            }
        }
        
        // build subselects for reverse relations if the body of the select is not specified explicitly
        if (! $select_column && $qpart->getUsedRelations(RelationTypeDataType::REVERSE)) {
            $output = $this->buildSqlSelectSubselect($qpart, $select_from);
            if ($make_groupable && $aggregator) {
                if ($aggregator === $qpart->getAggregator()){
                    $aggregator = $aggregator->getNextLevelAggregator();
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
            switch (true) {
                // if the column to select is explicitly defined, just select it
                case $select_column:
                    $output = $select_from . $this->getAliasDelim() . $select_column;
                    break;
                // see if the attribute is a JSON path
                case $this->isJsonDataAddress($address):
                    list ($address, $jsonPath) = $this->parseJsonDataAddress($address);
                    $column = $this->buildSqlSelect(
                        $qpart, 
                        $select_from, 
                        $address, 
                        false, // Do not use an alias
                        false, // Do not aggregate inside JSON_VALUE()!
                        $make_groupable, 
                        false
                    );
                    $output = $this->buildSqlJsonRead($column, $jsonPath);
                    break;
                // see if the attribute is a statement. If so, just replace placeholders
                case $this->isSqlStatement($address):
                    $output = $this->replacePlaceholdersInSqlAddress($address, $qpart->getAttribute()->getRelationPath(), ['~alias' => $select_from], $select_from);
                    break;
                // IF there is a custom SQL_SELECT statement, use it.
                case $custom_select = $qpart->getDataAddressProperty(self::DAP_SQL_SELECT):
                    $output = $this->replacePlaceholdersInSqlAddress($custom_select, $qpart->getAttribute()->getRelationPath(), ['~alias' => $select_from], $select_from);
                    break;
                // otherwise get the select from the attribute
                default:
                    if (! $data_address = $qpart->getDataAddressProperty(self::DAP_SQL_SELECT_DATA_ADDRESS)){
                        $data_address = $address;
                    }
                    $output = $select_from . $this->getAliasDelim() . $data_address;
                    break;
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
     * @param QueryPart $qpart
     * @param string $select_from
     * @return string
     */
    protected function buildSqlSelectSubselect(QueryPart $qpart, $select_from = null)
    {
        $rev_rel = $qpart->getFirstRelation(RelationTypeDataType::REVERSE);
        if (! $rev_rel) {
            return '';
        }
            
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

        // Some aggregators require customized subselects - handle them here
        if (($qpart instanceof QueryPartAttribute) && $qpart->hasAggregator()) {
            $aggr = $qpart->getAggregator()->getFunction()->__toString();
            // MIN_OF and MAX_OF basically do subselect with LIMIT 1 ordered by the first argument of the aggregator
            if ($aggr === AggregatorFunctionsDataType::MAX_OF || $aggr === AggregatorFunctionsDataType::MIN_OF) {
                $relq->setLimit(1, 0);
                $relq->addSorter(
                    $qpart->getAggregator()->getArguments()[0], 
                    $aggr === AggregatorFunctionsDataType::MAX_OF ? SortingDirectionsDataType::DESC : SortingDirectionsDataType::ASC,
                    false
                );
                $relq_attribute_alias = DataAggregation::stripAggregator($relq_attribute_alias);
            }
        }
        
        if ($this->getMainObject()->isExactly($relq->getMainObject()) && $qpart->getAlias() === $relq_attribute_alias) {
            throw new QueryBuilderException('Cannot build SQL query: found circular self-reference "' . $relq_attribute_alias . '" for object ' . $this->getMainObject()->__toString());
        }
        
        $relq->addAttribute($relq_attribute_alias);
        
        // Let the subquery inherit all filters of the main query, that need to be applied to objects beyond the reverse relation.
        // In our examplte, those would be any filter on ORDER->CUSTOMER<-CUSTOMER_CARD or ORDER->CUSTOMER<-CUSTOMER_CARD->TYPE, etc. Filters
        // over ORDER oder ORDER->CUSTOMER would be applied to the base query and ar not neeede in the subquery any more.
        // If we would rebase and add all filters, it will still work, but the SQL would get much more complex and surely
        // slow with large data sets.
        // Filtering out applicable filters (conditions) is done via the following callback, that returns TRUE only if the
        // path we rebase to matches the beginning of the condition's relation path.
        $relq_condition_filter = function($condition, $relation_path_to_new_base_object) use ($qpart) {
            if(($qpart instanceof QueryPartAttribute) && $qpart->hasAggregator() && !$condition->appliesToAggregatedValues()) {
                return false;
            }

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
            $customJoinOn = $rightKeyAttribute->getDataAddressProperty(self::DAP_SQL_JOIN_ON);
            if (! $reg_rel_path->isEmpty()) {
                // attach to the related object key of the last regular relation before the reverse one
                $junction_attribute = $this->getMainObject()->getAttribute(RelationPath::join($reg_rel_path->toString(), $rev_rel->getLeftKeyAttribute()->getAlias()));
            } else {
                // attach to the target key in the core query if there are no regular relations preceeding the reversed one
                $junction_attribute = $rev_rel->getLeftKeyAttribute();
            } 
            
            // The filter needs to be an EQ, since we want a to compare by "=" to whatever we define without any quotes
            // Putting the value in brackets makes sure it is treated as an SQL expression and not a normal value
            if ($rightKeyAttribute instanceof CompoundAttributeInterface) {
                // If it's a compound attribute, we need filter query parts for every compound
                if (! $junction_attribute instanceof CompoundAttributeInterface) {
                    throw new QueryBuilderException('Cannot render SQL subselect from "' . $qpart->getAlias() . '": Relations with compound attributes as keys only supported in SQL query builders if both keys are compounds!');
                }
                if (count($rightKeyAttribute->getComponents()) !== count($junction_attribute->getComponents())) {
                    throw new QueryBuilderException('Cannot render SQL subselect from "' . $qpart->getAlias() . '": the compound attribute keys on both sides have different number of components!');
                }
                foreach ($rightKeyAttribute->getComponents() as $compIdx => $rightKeyComp) {
                    $relq->addFilterWithCustomSql($rightKeyComp->getAttribute()->getAlias(), $this->buildSqlSelectSubselectJunctionWhere($qpart, $junction_attribute->getComponent($compIdx)->getAttribute(), $select_from), EXF_COMPARATOR_EQUALS);
                }
            } else {
                if (! $this->buildSqlDataAddress($junction_attribute) && ! $customJoinOn) {
                    throw new QueryBuilderException('Cannot render SQL subselect from "' . $qpart->getAlias() . '": one of the relation key attributes has neither a data address nor an SQL_JOIN_ON custom address property!');
                }
                $junctionQpart = $relq->addFilterWithCustomSql($rightKeyAttribute->getAlias(), $this->buildSqlSelectSubselectJunctionWhere($qpart, $junction_attribute, $select_from), EXF_COMPARATOR_EQUALS);
            }
            
            if ($customJoinOn) {
                if (! $junctionQpart) {
                    throw new QueryBuilderException('Cannot render SQL subselect from "' . $qpart->getAlias() . '": custom JOINs via SQL_JOIN_ON only supported for regular key attributes (no compounds, etc.)!');
                }
                // If it's a custom JOIN, calculate it here
                $customJoinOn = $this->replacePlaceholdersInSqlJoin(
                    $customJoinOn,
                    $relq->getMainTableAlias(),
                    $select_from,
                    $rev_rel,
                    $relq
                );
                $junctionQpart->setDataAddressProperty(self::DAP_SQL_WHERE, $customJoinOn);
            }
        }
        
        $output = '(' . $relq->buildSqlQuerySelect() . ')';
        
        return $output;
    }
    
    /**
     * Returns the SQL for y in `<subselect> WHERE x = y`
     * 
     * @param QueryPart $qpart
     * @param MetaAttributeInterface $junctionAttribute
     * @param string $select_from
     * @return string
     */
    protected function buildSqlSelectSubselectJunctionWhere(QueryPart $qpart, MetaAttributeInterface $junctionAttribute, string $select_from) : string
    {
        return '(' . $select_from . $this->getAliasDelim() . $this->buildSqlDataAddress($junctionAttribute) . ')';
    }
    
    /**
     * Builds a group function for the SQL select statement (e.g. "SUM(field)") from an ExFace aggregator function. 
     * 
     * This method translates ExFace aggregators to SQL und thus will probably differ between SQL dialects.
     *
     * @param QueryPartAttribute $qpart
     * @param string $select_from
     * @param string $select_column
     * @param string $select_as
     * @param AggregatorInterface $aggregator
     * @return string
     */
    protected function buildSqlSelectGrouped(QueryPartAttribute $qpart, $select_from = null, $select_column = null, $select_as = null, AggregatorInterface $aggregator = null)
    {
        $aggregator = $aggregator ?? $qpart->getAggregator();
        $select = $this->buildSqlSelect($qpart, $select_from, $select_column, false, false);
        
        // Can't just list binary values - need to transform them to strings first!
        $aggrFunc = $aggregator->getFunction()->__toString();
        if ($this->isBinaryColumn($qpart) && ($aggrFunc === AggregatorFunctionsDataType::LIST_ALL || $aggrFunc == AggregatorFunctionsDataType::LIST_DISTINCT)) {
            $select = $this->buildSqlSelectBinaryAsHEX($select);
        }
        
        return $this->buildSqlGroupByExpression($qpart, $select, $aggregator);
    }
    
    /**
     * Returns the SQL to transform the given binary SELECT predicate into something like 0x12433.
     *
     * @param string $select_from
     * @return string
     */
    protected function buildSqlSelectBinaryAsHEX(string $select_from) : string
    {
        return 'CONCAT(\'0x\', LOWER(HEX(' . $select_from . ')))';
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
            case AggregatorFunctionsDataType::MAX_OF:
            case AggregatorFunctionsDataType::MIN_OF:
                // MIN_OF/MAX_OF is handled in buildSqlSelectSubselect()
                $output = $sql;
                break;
            case AggregatorFunctionsDataType::LIST_DISTINCT:
            case AggregatorFunctionsDataType::LIST_ALL:
                $delim = $args[0] ?? $this->buildSqlGroupByListDelimiter($qpart);
                $output = "GROUP_CONCAT(" . ($function_name == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . $sql . " SEPARATOR '{$this->escapeString($delim)}')";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                $output = "COUNT(DISTINCT " . $sql . ")";
                break;
            case AggregatorFunctionsDataType::COUNT_IF:
                $cond = $args[0];
                list($if_comp, $if_val) = explode(' ', $cond ?? '', 2);
                if (!$if_comp || is_null($if_val)) {
                    throw new QueryBuilderException('Invalid argument for COUNT_IF aggregator: "' . $cond . '"!', '6WXNHMN');
                }
                //we have to explicitly use the datatype of the attribute here so we can parte the values correctly in the where part
                $output = "SUM(CASE WHEN " . $this->buildSqlWhereComparator($qpart, $sql, $if_comp, $if_val, false, false, $qpart->getAttribute()->getDataType()) . " THEN 1 ELSE 0 END)";
                break;
            default:
                break;
        }
        
        return $output;
    }
    
    protected function buildSqlGroupByListDelimiter(QueryPartAttribute $qpart = null) {
        return ($qpart === null ? EXF_LIST_SEPARATOR : rtrim($qpart->getAttribute()->getValueListDelimiter(), " ")) . ' ';
    }
    
    protected function buildSqlFrom(string $operation = self::OPERATION_READ)
    {
        // Replace static placeholders
        $alias = $this->getMainObject()->getAlias();
        $table = str_replace('[#~alias#]', $alias, $this->buildSqlDataAddress($this->getMainObject(), $operation));
        $from = $table . $this->buildSqlAsForTables($this->getMainTableAlias());
        
        // Replace dynamic palceholders
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
     * @param QueryPart $qpart
     * @param string $left_table_alias
     * @return array [ relation_path_relative_to_main_object => join_string ]
     */
    protected function buildSqlJoins(QueryPart $qpart, $left_table_alias = '')
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
                $joins[$right_table_alias] = "\n JOIN " . str_replace('[#~alias#]', $right_table_alias, $this->buildSqlDataAddress($this->getMainObject())) . $this->buildSqlAsForTables($right_table_alias) . ' ON ' . $left_table_alias . $this->getAliasDelim() . $this->getMainObject()->getUidAttributeAlias() . ' = ' . $this->buildSqlJoinSide($this->buildSqlDataAddress($this->getMainObject()->getUidAttribute()), $right_table_alias);
            } else {
                // In most cases we will build joins for attributes of related objects.
                $left_table_alias = ($left_table_alias ? $left_table_alias : $this->getShortAlias($this->getMainObject()->getAlias()) . $this->getQueryId());
                foreach ($rels as $alias => $rel) {
                    /* @var $rel \exface\Core\Interfaces\Model\MetaRelationInterface */
                    if ($rel->isForwardRelation() === true) {
                        // Forward relations are simple JOINs
                        $right_table_alias = $this->getShortAlias($alias . $this->getQueryId());
                        $right_obj = $this->getMainObject()->getRelatedObject($alias);
                        // generate the join sql
                        $join_table = str_replace('[#~alias#]', $right_table_alias, $this->buildSqlDataAddress($right_obj)) . $this->buildSqlAsForTables($right_table_alias);
                        $leftKeyAttr = $rel->getLeftKeyAttribute();
                        if ($customOn = $leftKeyAttr->getDataAddressProperty(self::DAP_SQL_JOIN_ON)) {
                            // If a custom join condition ist specified in the attribute, that defines the relation, just replace the aliases in it
                            $join_on = $this->replacePlaceholdersInSqlJoin(
                                $customOn,
                                $left_table_alias,
                                $right_table_alias,
                                $rel,
                                null,
                                null,
                                $join_table
                            );
                        } else {
                            // Otherwise create the ON clause from the attributes on both sides of the relation.
                            $join_on = $this->buildSqlJoinOn($leftKeyAttr, $rel->getRightKeyAttribute(), $left_table_alias, $right_table_alias);
                        }
                        $join = "\n " . $this->buildSqlJoinType($rel) . ' JOIN ' . $join_table . ' ON ' . $join_on;
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
    
    /**
     * Builds string for sql join on.
     * When $leftKeyAttr and $rightKeyAttr are compound attributes, string is build for each component and
     * connected with `AND`.
     *
     * @param MetaAttributeInterface $leftKeyAttr
     * @param MetaAttributeInterface $rightKeyAttr
     * @param string $leftTableAlias
     * @param string $rightTableAlias
     * @throws RuntimeException
     * @return string
     */
    protected function buildSqlJoinOn(MetaAttributeInterface $leftKeyAttr, MetaAttributeInterface $rightKeyAttr, string $leftTableAlias, string $rightTableAlias) : string
    {
        $join = '';
        // If the keys are compounds, we need a comlex ON with multiple predicates.
        // For regular a key attributes a simple `ON left.col = right.col` is enough (see else-branch)
        if ($leftKeyAttr instanceof CompoundAttributeInterface) {
            if (! $rightKeyAttr instanceof CompoundAttributeInterface) {
                throw new QueryBuilderException('Cannot render SQL join on for attributes  "' . $leftKeyAttr->getAliasWithRelationPath() . '" and "' . $rightKeyAttr->getAliasWithRelationPath() . '": Relations with compound attributes as keys only supported in SQL query builders if both keys are compounds!');
            }
            if (count($leftKeyAttr->getComponents()) !== count($rightKeyAttr->getComponents())) {
                throw new QueryBuilderException('Cannot render SQL join on for attributes  "' . $leftKeyAttr->getAliasWithRelationPath() . '" and "' . $rightKeyAttr->getAliasWithRelationPath() . '": the compound attribute keys on both sides have different number of components!');
            }
            $compoundJoins= array();
            foreach($leftKeyAttr->getComponents() as $compIdx => $comp) {
                $compoundJoins[] = $this->buildSqlJoinOn($comp->getAttribute(), $rightKeyAttr->getComponent($compIdx)->getAttribute(), $leftTableAlias, $rightTableAlias);
            }
            $join = implode(' AND ', $compoundJoins);
        } else {
            // Build the left side of the JOIN
            $right_obj = $rightKeyAttr->getObject();
            $leftAddress = $this->buildSqlDataAddress($leftKeyAttr);
            $leftJoinOn = $this->buildSqlJoinSide($leftAddress, $leftTableAlias);
            // If this side is a binary and the other side is not, select the binary as a HEX string
            if ($this->isBinaryColumn($leftKeyAttr) && ! $this->isBinaryColumn($rightKeyAttr)) {
                $leftJoinOn = $this->buildSqlSelectBinaryAsHEX($leftJoinOn);
            }

            // Build the right side of the JOIN
            $rightAddress = $this->buildSqlDataAddress($rightKeyAttr);
            $rightJoinOn = $this->buildSqlJoinSide($rightAddress, $rightTableAlias);
            // If this side is a binary and the other side is not, select the binary as a HEX string
            if ($this->isBinaryColumn($rightKeyAttr) && ! $this->isBinaryColumn($leftKeyAttr)) {
                $rightJoinOn = $this->buildSqlSelectBinaryAsHEX($rightJoinOn);
            }

            // Combine to a JOIN
            $join .=  $leftJoinOn . ' = ' . $rightJoinOn;

            // Add a custom WHERE if the right side has one. No need to add a custom WHERE for the left side
            // as it will be part of the regular WHERE of the query
            if ($customSelectWhere = $right_obj->getDataAddressProperty(self::DAP_SQL_SELECT_WHERE)) {
                if (mb_stripos($customSelectWhere, 'SELECT ') === false) {
                    $join .= ' AND ' . $this->replacePlaceholdersInSqlAddress($customSelectWhere, null, ['~alias' => $rightTableAlias]);
                } else {
                    $join .= $this->buildSqlComment('Cannot use SQL_SELECT_WHERE of object "' . $right_obj->getName() . '" (' . $right_obj->getAliasWithNamespace() . ') in a JOIN - a column may not be outer-joined to a subquery!');
                }
            }
        }
        return $join;
    }
    
    /**
     * LEFT vs. INNER JOIN etc.
     *
     * @param MetaRelationInterface $relation
     * @return string
     */
    protected function buildSqlJoinType(MetaRelationInterface $relation)
    {
        /* FIXME use inner joins for required relations? Supposed to be faster, but it would result in different
         * behavior depending on relation settings... Need to test a bit more!
         if ($relation->isForwardRelation() === true && $relation->getLeftKeyAttribute()->isRequired() === true) {
         return 'INNER';
         }*/
        return 'LEFT';
    }
    
    /**
     * E.g. `table_alias.data_address`
     *
     * @param string $data_address
     * @param string $table_alias
     * @return string
     */
    protected function buildSqlJoinSide($data_address, $table_alias)
    {
        $join_side = $data_address;
        switch (true) {
            // Custom SQL statements
            case $this->isSqlStatement($join_side):
                $join_side = str_replace('[#~alias#]', $table_alias, $join_side);
                if (! empty(StringDataType::findPlaceholders($join_side))) {
                    throw new QueryBuilderException('Cannot use placeholders in SQL JOIN keys: "' . $join_side . '"');
                }
                // IDEA Allow placeholders in JOINed data addresses. This would allow to use compound
                // attributes with placeholders for JOINs very effectively. However, replacePlaceholdersInSqlAddress()
                // will need the correct base object then - a different one on each side. Not quite sure
                // how to do this.
                // $join_side = $this->replacePlaceholdersInSqlAddress($join_side, null, ['~alias' => $table_alias], $table_alias);
                break;
            // JSON address as column::$.path
            case $this->isJsonDataAddress($data_address):
                list ($address, $jsonPath) = $this->parseJsonDataAddress($join_side);
                $join_side = $this->buildSqlJsonRead($table_alias . '.' . $address, $jsonPath);
                break;
            // SQL column names
            default:
                $join_side = $table_alias . $this->getAliasDelim() . $join_side;
                break;
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
        $delimiter = $qpart->getValueListDelimiter();
        
        $select = $this->buildSqlSelectGrouped($qpart);
        $customWhereClause = $qpart->getDataAddressProperty(self::DAP_SQL_WHERE);
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
        }
        
        // build the HAVING clause
        if ($customWhereClause) {
            // check if it has an explicit where clause. If not try to filter based on the select clause
            $output = $this->replacePlaceholdersInSqlAddress($customWhereClause, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias, '~value' => $val], $table_alias);
        } else {
            // Determine, what we are going to compare to the value: a subquery or a column
            if ($this->isSqlStatement($this->buildSqlDataAddress($attr))) {
                $subj = $this->replacePlaceholdersInSqlAddress($select, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias], $table_alias);
            } else {
                $subj = $select;
            }
            // Do the actual comparing
            $output = $this->buildSqlWhereComparator($qpart, $subj, $comp, $val, $rely_on_joins);
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
            switch (true) {
                case $qpart_fltr->isCompound() === true:
                    if ($grp_string = $this->buildSqlWhere($qpart_fltr->getCompoundFilterGroup(), $rely_on_joins)) {
                        $where .= "\n " . ($where ? $op . " " : '') . "(" . $grp_string . ")";
                    }
                    break;
                case $fltr_string = $this->buildSqlWhereCondition($qpart_fltr, $rely_on_joins):
                    $where .= "\n" . $this->buildSqlComment("buildSqlWhereCondition(" . StringDataType::truncate($qpart_fltr->getCondition()->toString(), 100, false, true) . ", " . $rely_on_joins . ")")
                           . "\n " . ($where ? $op . " " : '') . $fltr_string;
                    break;
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
     * @param QueryPartFilter $qpart
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
        
        $select = $this->buildSqlDataAddress($attr);
        $customWhereClause = $qpart->getDataAddressProperty(self::DAP_SQL_WHERE);
        $customWhereAddress = $qpart->getDataAddressProperty(self::DAP_SQL_WHERE_DATA_ADDRESS);
        $object_alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() : $this->getMainObject()->getAlias());
        $table_alias = $this->getShortAlias($object_alias . $this->getQueryId());
        $ignoreEmptyValues = !$qpart->getCondition() || $qpart->getCondition()->willIgnoreEmptyValues();
        
        // If the attribute has no data address AND is a static calculation, generate a 
        // static WHERE clause that compares the result of the static expression evaluation 
        // and the value provided. This ensures, that such comparisons behave roughly the 
        // same as those with real SQL addresses. This is required for compound attributes 
        // with static components!
        if (empty($select) && empty($customWhereClause) && empty($customWhereAddress) && $attr->hasCalculation() && $attr->getCalculationExpression()->isStatic()) {
            $staticVal = $attr->getCalculationExpression()->evaluate();
            return $this->buildSqlWhereComparator($qpart, "'{$this->escapeString($staticVal)}'", $comp, $val, $rely_on_joins);
        }
        
        // Doublecheck that the filter actually can be used
        if (! ($select || $customWhereClause) || ($ignoreEmptyValues && $val === '')) {
            if ($val === '') {
                $hint = ' (the value is empty)';
            } else {
                $hint = ' (neither a data address, nor a custom SQL_WHERE found for the attribute)';
            }
            // At this point we know, that the filter does not produce a WHERE clause, so the only
            // option left is being a placeholder in the data address. If it's not the case, throw
            // an error!
            if (! in_array($qpart->getAlias(), StringDataType::findPlaceholders($this->buildSqlDataAddress($this->getMainObject())))) {
                throw new QueryBuilderException('Illegal SQL WHERE clause for object "' . $this->getMainObject()->getName() . '" (' . $this->getMainObject()->getAlias() . '): expression "' . $qpart->getAlias() . '", Value: "' . $val . '"' . $hint);
            }
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
                if ($this->isSqlStatement($this->buildSqlDataAddress($attr))) {
                    $subj = $this->replacePlaceholdersInSqlAddress($select, $qpart->getAttribute()->getRelationPath(), ['~alias' => $table_alias], $table_alias);
                } else {
                    $subj = $table_alias . $this->getAliasDelim() . $select;
                }
            }
            // Do the actual comparing
            $output = $this->buildSqlWhereComparator($qpart, $subj, $comp, $val, $rely_on_joins);
        }
        return $output;
    }
    
    protected function getOptimizedComparator(QueryPartFilter $qpart)
    {
        $val = $qpart->getCompareValue();
        $attr = $qpart->getAttribute();
        $comp = $qpart->getComparator();
        $type = $qpart->getDataType();
        $compIsOrIsNot = ($comp === ComparatorDataType::IS || $comp === ComparatorDataType::IS_NOT);
        
        switch (true) {
            // always use the equals comparator for foreign keys! It's faster!
            case $attr->isRelation() && $compIsOrIsNot && ! ($type instanceof StringDataType):
            case $this->getMainObject()->hasUidAttribute() && $attr->isExactly($this->getMainObject()->getUidAttribute()) && $compIsOrIsNot:
            // also use equals for the NUMBER data type, but make sure, the value to compare to is really a number (otherwise the query will fail!)
            case ($type instanceof NumberDataType) && is_numeric($val) && $compIsOrIsNot:
            // also use equals for the BOOLEAN data type
            case ($type instanceof BooleanDataType) && $compIsOrIsNot:
            // also use equals for the NUMBER data type, but make sure, the value to compare to is really a number (otherwise the query will fail!)
            case ($type instanceof DateDataType) && $compIsOrIsNot:
                $comp = $comp === ComparatorDataType::IS ? ComparatorDataType::EQUALS : ComparatorDataType::EQUALS_NOT;
                break;
        }
        return $comp;
    }
    
    /**
     * 
     * @param QueryPartAttribute $qpart
     * @param string $subject
     * @param string $comparator
     * @param mixed $value
     * @param bool $rely_on_joins
     * @param bool $valueIsSQL
     * @param DataTypeInterface|null $data_type
     * @throws QueryBuilderException
     * @return bool|string
     */
    protected function buildSqlWhereComparator(QueryPartAttribute $qpart, string $subject, string $comparator, $value, bool $rely_on_joins, bool $valueIsSQL = null, DataTypeInterface $data_type = null) : string
    {
        $data_type = $data_type ?? $qpart->getDataType();
        $dataAddressProps = $qpart->getDataAddressProperties();
        $value_list_delimiter = $qpart->getValueListDelimiter();
        $valueIsSQL = $valueIsSQL ?? $qpart->isValueDataAddress();
        
        if($this->isJsonDataAddress($subject)) {
            [$column, $path] = $this->parseJsonDataAddress($subject);
            $subject = $this->buildSqlJsonRead($column, $path);
        }

        $valueRaw = $value;
        // Check if the value is of valid type.
        try {
            // Pay attention to comparators expecting concatennated values (like IN) - the concatennated value will not validate against
            // the data type, but the separated parts should
            switch (true) {
                case $valueIsSQL === true:
                    break;
                case $comparator != EXF_COMPARATOR_IN && $comparator != EXF_COMPARATOR_NOT_IN:
                    // If it's a single value, cast it to the data type to make sure, it's a valid value.
                    switch (true) {
                        
                        case ($data_type instanceof DateDataType):
                        case ($data_type instanceof NumberDataType):
                        case ($data_type instanceof BooleanDataType):
                            $value = $data_type::cast($value);
                            break;
                        default:
                            $value = $data_type::cast($value);
                    }
                    break;
                    
                default:
                    $values = explode($value_list_delimiter, $value ?? '');
                    $value = '';
                    $valueNullChecks = [];
                    
                    foreach ($values as $nr => $val) {
                        // If there is an empty string among the values or one of the empty-comparators,
                        // this means that the value may or may not be empty (NULL). NULL is not a valid
                        // value for an IN-statement, though, so we need to append an "OR IS NULL" here.
                        if ($val === '' || $val === EXF_LOGICAL_NULL) {
                            unset($values[$nr]);
                            $valueNullChecks[] = $subject . ($comparator == EXF_COMPARATOR_IN ? ' IS NULL' : ' IS NOT NULL');
                            if ($data_type instanceof StringDataType) {
                                $valueNullChecks[] = $subject . ($comparator == EXF_COMPARATOR_IN ? " = ''" : " != ''");
                            }
                            continue;
                        }
                        // Normalize non-empty values
                        $val = trim($val);
                        $values[$nr] = $this->prepareWhereValue($val, $data_type, $dataAddressProps);
                    }
                    
                    switch (true) {
                        // If there is only one value, it is better to use = than IN - it is exactly the same
                        // and often is significantly faster. Keep in mind thogh, that the null-check will not
                        // be part of the $values array, so need to check for it too.
                        case count($values) === 1 && empty($valueNullChecks):
                            $val = $values[0];
                            if ($comparator == ComparatorDataType::IN) {
                                return $subject . ' = ' . $val;
                            } else {
                                return $subject . ' != ' . $val;
                            }
                        // IN(null) will result in empty $values and a NULL-check, so just use the NULL-check in this case.
                        case empty($values) === true && ! empty($valueNullChecks):
                            $value = EXF_LOGICAL_NULL;
                            $comparator = $comparator === EXF_COMPARATOR_IN ? EXF_COMPARATOR_EQUALS : EXF_COMPARATOR_EQUALS_NOT;
                            break;
                        // Otherwise create a (...) list and append the NULL-check with an OR if there is one.
                        default:
                            $value = '(' . (! empty($values) ? implode(',', $values) : 'NULL') . ')';
                            $valueIsSQL = true;
                            if (! empty($valueNullChecks)) {
                                if ($comparator === ComparatorDataType::IN) {
                                    $value .= ' OR ' . implode(' OR ', $valueNullChecks);
                                } else {
                                    $value .= ' AND ' . implode(' AND ', $valueNullChecks);
                                }
                            }
                    }
                    break;
            }
        } catch (DataTypeCastingError $e) {
            // If the data type is incompatible with the value, return a WHERE clause, that is always false.
            // A comparison of a date field with a string or a number field with
            // a string simply cannot result in TRUE.
            $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::WARNING);
            return '/* ' . $subject . ' cannot pass comparison to "' . $valueRaw . '" via comparator "' . $comparator . '": wrong data type! */' . "\n"
                . '1 = 0';
        }
        
        if (is_null($value) || (! $valueIsSQL && $this->prepareWhereValue($value, $data_type, $dataAddressProps) === EXF_LOGICAL_NULL)){
            switch ($comparator) {
                case EXF_COMPARATOR_EQUALS:
                case EXF_COMPARATOR_IS:
                    return $subject . ' IS NULL';
                default:
                    return $subject . ' IS NOT NULL';
            }
        }
        
        // If everything is OK, build the SQL
        switch (true) {
            case $comparator === EXF_COMPARATOR_IN:
                $output = "(" . $subject . " IN " . $value . ")";
                break; // The parentheses are needed if there is a OR IS NULL addition (see above)
            case $comparator === EXF_COMPARATOR_NOT_IN:
                $output = "(" . $subject . " NOT IN " . $value . ")";
                break; // The parentheses are needed if there is a OR IS NULL addition (see above)
            case $comparator === EXF_COMPARATOR_EQUALS:
                $output = $subject . " = " . ($valueIsSQL ? $value : $this->prepareWhereValue($value, $data_type, $dataAddressProps));
                break;
            case $comparator === EXF_COMPARATOR_EQUALS_NOT:
                $output = $subject . " != " . ($valueIsSQL ? $value : $this->prepareWhereValue($value, $data_type, $dataAddressProps));
                break;
            case $comparator === EXF_COMPARATOR_GREATER_THAN:
            case $comparator === EXF_COMPARATOR_LESS_THAN:
            case $comparator === EXF_COMPARATOR_GREATER_THAN_OR_EQUALS:
            case $comparator === EXF_COMPARATOR_LESS_THAN_OR_EQUALS:
                $output = $subject . " " . $comparator . " " . ($valueIsSQL ? $value : $this->prepareWhereValue($value, $data_type, $dataAddressProps));
                break;
            case $comparator === EXF_COMPARATOR_IS_NOT:
            case $comparator === EXF_COMPARATOR_IS:
                $like = $comparator === EXF_COMPARATOR_IS_NOT ? 'NOT LIKE' : 'LIKE';
                $output = "UPPER({$subject}) $like ";
                if ($valueIsSQL) {
                    $output .= "CONCAT('%', {$value}, '%')";
                } else {
                    $output .= "'%{$this->escapeString(mb_strtoupper($value))}%'";
                }
                break;
            // If the query builder cannot deal with the comparator, but the comparator can be transformed into other (atomic)
            // comparators, try building a query with the atomized version of the comparator
            case ! ComparatorDataType::isAtomic($comparator) && $qpart instanceof QueryPartFilter:
                $atomized = $qpart->atomize();
                if ($this->checkFilterBelongsInHavingClause($qpart)) {
                    $output = $atomized instanceof QueryPartFilterGroup ? $this->buildSqlHaving($atomized, $rely_on_joins) : $this->buildSqlHavingCondition($atomized, $rely_on_joins);
                } else {
                    $output = $atomized instanceof QueryPartFilterGroup ? $this->buildSqlWhere($atomized, $rely_on_joins) : $this->buildSqlWhereCondition($atomized, $rely_on_joins);
                }
                break;
            default:
                throw new QueryBuilderException('Comparator "' . $comparator . '" is not supported in SQL query builders');
        }
        return $output;
    }
    
    /**
     * 
     * @param mixed $value
     * @param DataTypeInterface $data_type
     * @param string[] $dataAddressProps
     * @return string
     */
    protected function prepareWhereValue($value, DataTypeInterface $data_type, array $dataAddressProps = [])
    {
        switch (true) {
            case $data_type instanceof BooleanDataType:
                $output = $value ? 1 : 0;
                break;
            case strcasecmp($value, EXF_LOGICAL_NULL) === 0:
                return EXF_LOGICAL_NULL;
            // No need to check if the value is valid JSON to search for it - any string is OK
            case $data_type instanceof JsonDataType:
                $output =  "'" . $this->escapeString($value) . "'";
                break;
            // In most cases apply the same checks and formatting as for INSERT/UPDATE statements
            default:
                $output = $this->prepareInputValue($value, $data_type, $dataAddressProps, false);
                break;
        }
        return $output;
    }
    
    /**
     * Builds a WHERE clause with a subquery (e.g. "column IN ( SELECT ... )" ).
     *
     * This is mainly used to handle filters over reversed relations, but also
     * for filters on joined columns in UPDATE queries, where the main query
     * does not support joining. The optional parameter $rely_on_joins controls
     * whether the method can rely on the main query to have all necessary joins.
     *
     * @param QueryPartFilter $qpart
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
        
        if (! $start_rel) {
            return '';
        }
        
        $qpart_rel_path = $qpart->getAttribute()->getRelationPath();
        /** @var MetaRelationPathInterface $prefix_rel_path part of the relation part up to the first reverse relation */
        $prefix_rel_path = $qpart_rel_path->getSubpath(0, $qpart_rel_path->getIndexOf($start_rel));
        
        // build a subquery
        /* @var $relq \exface\Core\QueryBuilders\AbstractSqlBuilder */
        $relq = QueryBuilderFactory::createFromSelector($this->getSelector());
        $relq->setMainObject($start_rel->getRightObject());
        $relq->setQueryId($this->getNextSubqueryId());
        
        // What kind of subquery structure?
        $comp = $qpart->getComparator();
        switch (true) {
            // See if we just need to check if the subuqery EXISTS. That is the case if it is a `IS NULL` 
            // or `IS NOT NULL` applied to the right-side key of the relation
            case $qpart->getCompareValue() === EXF_LOGICAL_NULL 
            && ($comp === ComparatorDataType::EQUALS || $comp === ComparatorDataType::EQUALS_NOT) 
            && $qpart->getFirstRelation() === $start_rel 
            && $start_rel->isReverseRelation()
            // Shouldn't the same logic apply to any not-nullable column of the right-most related table?
            // After all, if we check if a required attribute of a reverser relation is NULL, doesn't it
            // automatically mean, that no matching row exists?
            && $qpart->getAttribute()->is($start_rel->getRightKeyAttribute()):
                $relqFilterComp = ComparatorDataType::EQUALS;
                $junctionOp = ($comp === ComparatorDataType::EQUALS ? 'NOT ' : '') . 'EXISTS';
                break;
            // For negative comparators `attr NOT IN (subquery with inverted comparator)` 
            case ComparatorDataType::isNegative($comp):
                $relqFilterComp = ComparatorDataType::invert($comp);
                $junctionOp = 'NOT IN';
                break;
            // Otherwise `attr IN (subquery)`
            default:
                $relqFilterComp = $comp;
                $junctionOp = 'IN';
        }
        
        if ($start_rel->isReverseRelation()) {
            // If we are dealing with a reverse relation, build a subquery to select foreign keys from rows of the joined tables,
            // that match the given filter
            $rel_filter_alias = $qpart->getAttribute()->rebase($qpart_rel_path->getSubpath($qpart_rel_path->getIndexOf($start_rel) + 1))->getAliasWithRelationPath();
            
            // Remember to keep the aggregator of the attribute filtered over. Since we are interested in a list of keys, the
            // subquery should GROUP BY these kees.
            if ($qpart->getAggregator()) {
                // IDEA HAVING-subqueries can be very slow. Perhaps we can optimize the subquery a little in certain cases:
                // e.g. if we are filtering over a SUM of natural numbers with "> 0", we could simply add a "> 0" filter
                // without any aggregation, and it should yield the same results
                $rel_filter_alias .= DataAggregation::AGGREGATION_SEPARATOR . $qpart->getAggregator()->exportString();
                $relq->addAggregation($start_rel->getRightKeyAttribute()->getAlias());
                
                // If we are in a WHERE subquery of a filter with an aggregator, this means, we want to filter
                // over the aggregated value. However, there might be other filters, that affect this aggregated
                // value: e.g. as SUM over transactions for a product will be different depending on the store
                // filter set for the query. So we need all applicable non-aggregating filters in our subquery.
                // This is achieved by rebasing all filters with the following filter callback, that excludes
                // certain conditions.
                $relq_condition_filter = function($condition, $relation_path_to_new_base_object) use ($qpart) {
                    // If the condition is not an attribute, keep it - other parts of the code will deal with it
                    if (! $condition->getExpression()->isMetaAttribute()) {
                        return true;
                    }
                    // If a condition matches the query part we are processing right now, skip it - we will
                    // add it later explicitly.
                    if ($condition->getExpression()->toString() === $qpart->getExpression()->__toString()){
                        return false;
                    }
                    // If a condition  has an aggregator itself - skip it as it will get its own subquery.
                    if (DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $condition->getExpression()->toString())) {
                        return false;
                    }
                    // If a condition is not applicable (is applied to something else than the tables in our
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
            if ($this->buildSqlDataAddress($relqKeyPart) === ''
                && ! $relqKeyPart->getDataAddressProperty(self::DAP_SQL_SELECT)
                && ! $relqKeyPart->getDataAddressProperty(self::DAP_SQL_SELECT_DATA_ADDRESS)
                && $relqKeyPart->getDataAddressProperty(self::DAP_SQL_JOIN_ON)) {
                    $relqKeyPart->setDataAddressProperty(self::DAP_SQL_SELECT, '*');
            }

            if (! $prefix_rel_path->isEmpty()) {
                // FIXME add support for related_object_special_key_alias
                $prefix_rel_str = RelationPath::join($prefix_rel_path->toString(), $this->getMainObject()->getRelatedObject($prefix_rel_path->toString())->getUidAttributeAlias());
                $prefix_rel_qpart = new QueryPartSelect($prefix_rel_str, $this, null, DataColumn::sanitizeColumnName($prefix_rel_str));
                $junction = $this->buildSqlSelect($prefix_rel_qpart, null, null, '');
            } else {
                $junctionTableAlias = $this->getShortAlias($start_rel->getLeftObject()->getAlias() . $this->getQueryId());
                $junctionDataAddress = $this->buildSqlDataAddress($start_rel->getLeftKeyAttribute());
                if ($this->isSqlStatement($junctionDataAddress) === true) {
                    $junction = $this->replacePlaceholdersInSqlAddress($junctionDataAddress, null, null, $junctionTableAlias);
                } else {
                    $junction = $junctionTableAlias . $this->getAliasDelim() . $junctionDataAddress;
                }
            }
                
            // Add the filter relative to the first reverse relation with the same $value and $comparator
            switch (true) {
                // If using `EXISTS` or `NOT EXISTS` we do not need `column IN` in front of the subquery,
                // but rather ` = column` inside the subquery as filter, so we use it (= $junction)
                // as filter value instead of its real value `NULL` and empty the $junction afterward.
                case stripos($junctionOp, 'EXISTS') !== false:
                    $relq->addFilterWithCustomSql($rel_filter_alias, $junction, $relqFilterComp);
                    $junction = '';
                    break;
                // If the data address is a custom sql, make sure it still remains a custom sql no matter what
                case $qpart->isValueDataAddress():
                    $relq->addFilterWithCustomSql($rel_filter_alias, $qpart->getCompareValue(), $relqFilterComp);
                    break;
                // Otherwise just add a regular filter
                default:
                    $relFilter = $relq->addFilterFromString($rel_filter_alias, $qpart->getCompareValue(), $relqFilterComp);
                    $relFilter->getCondition()->setApplyToAggregates($qpart->getCondition()->appliesToAggregatedValues());
                    break;
            }

            // SQL engines (at least MS SQL and MySQL) have a strange side-effect on NOT IN, where the value list
            // includes NULLs. E.g. `SELECT IF(5 NOT IN (2, NULL, 4), 1, 0)` will return `0` because NULL is concidered
            // to be an unknown value, that eventually could be a 5 too. This is a side-effect of the IN-operator just
            // being a shortcut for a bunch of ORs. On the other hand, `SELECT IF(5 IN (2, NULL, 4), 1, 0)` works fine.
            // Perhaps this bears some sacred truth, but in our case 5 does not equal NULL, so here is a workaround,
            // That will add a `AND xxx NOT NULL` at the end of the subquery, where `xxx` is the attribute, that we are
            // actually selecting.
            if ($junctionOp === 'NOT IN' && ! $relqKeyPart->getAttribute()->isRequired()) {
                $relq->addFilterFromString($relqKeyPart->getAttribute()->getAliasWithRelationPath(), EXF_LOGICAL_NULL, ComparatorDataType::EQUALS_NOT);
            }

            // Handle SQL_JOIN_ON if it is defined for the right attribute (i.e. if we would join our left table to our right table,
            // the JOIN would use this custom ON statement). Here we build the custom ON statement and use it as a WHERE clause in
            // the subselect.
            if ($customJoinOn = $start_rel->getRightKeyAttribute()->getDataAddressProperty(self::DAP_SQL_JOIN_ON)) {
                $customJoinOn = $this->replacePlaceholdersInSqlJoin(
                    $customJoinOn,
                    $relq->getMainTableAlias(),
                    $this->getMainTableAlias(), 
                    $start_rel,
                    $relq
                );
                $joinFilterQpart = $relq->addFilterFromString($start_rel->getRightKeyAttribute()->getAlias(), $qpart->getCompareValue(), $qpart->getComparator());
                $joinFilterQpart->setDataAddressProperty(self::DAP_SQL_WHERE, $customJoinOn);
                $sql = (stripos($junctionOp, 'EXISTS') !== false ? $junctionOp : 'EXISTS') . ' (' . $relq->buildSqlQuerySelect() . ')';
                return $sql;
            }
                
        } else {
            // If we are dealing with a regular relation, build a subquery to select primary keys from joined tables and match them to the foreign key of the main table
            $relq->addFilter($qpart->rebase($relq, $start_rel->getAliasWithModifier()));
            $relq->addAttribute($start_rel->getRightKeyAttribute()->getAlias());
            $junction_qpart = new QueryPartSelect($start_rel->getLeftKeyAttribute()->getAlias(), $this, null, $start_rel->getLeftKeyAttribute()->getAliasWithRelationPath());
            $junction = $this->buildSqlSelect($junction_qpart, null, null, '');
        }

        // Do not use a column with `EXISTS` and `NOT EXISTS`!
        if (stripos($junctionOp, 'EXISTS') !== false) {
            $junction = '';
        }
        
        $output = "$junction $junctionOp ({$relq->buildSqlQuerySelect()})";
        
        return $output;
    }
    
    /**
     * Builds the contents of an ORDER BY statement for one column. 
     * 
     * E.g. `APP DESC` to sort via the attribute alias `APP` of the meta object
     * `exface.Core.OBJECT`). By default the ORDER clause will contain column
     * aliases (i.e. `APP` and not `app_oid` in the example above). Override
     * this method in a specific query builder to change this.
     * 
     * The result does not contain the words "ORDER BY", the
     * results of multiple calls to this method with different attributes can
     * be concatennated into a comple ORDER BY clause.
     *
     * @param QueryPartSorter $qpart
     * @param string|NULL $select_from
     * @return string
     */
    protected function buildSqlOrderBy(QueryPartSorter $qpart, $select_from = '') : string
    {
        switch ($select_from) {
            case '':
                $select_from = '';
                break;
            case null:
                $select_from = $this->getShortAlias($this->getMainObject()->getAlias());
                break;
        }
        
        switch (true) {
            case $customOrderBy = $qpart->getDataAddressProperty(self::DAP_SQL_ORDER_BY):
                $phs = StringDataType::findPlaceholders($customOrderBy);
                if (empty($phs)) {
                    // Fallback to older code in case the SQL_ORDER_BY has no placeholders
                    return $this->getShortAlias($this->getMainObject()->getAlias()) . $this->getAliasDelim() . $customOrderBy . ' ' . $qpart->getOrder();
                } else {
                    return StringDataType::replacePlaceholders($customOrderBy, [
                        '~alias' => $select_from,
                        '~order' => $qpart->getOrder()
                    ]);
                }
            case $qpart->isCompound():
                $sorters = [];
                foreach ($qpart->getCompoundChildren() as $child) {
                    $sorters[] = $this->buildSqlOrderBy($child, $select_from);
                }
                return implode(', ', $sorters);
            default:
                $sort_by = $this->getShortAlias($qpart->getColumnKey());
        }
        
        return ($select_from === '' ? '' : $select_from . $this->getAliasDelim()) . $sort_by . ' ' . $qpart->getOrder();
    }
    
    /**
     * Builds the contents of an GROUP BY statement for one column (e.g.
     * "ATTRIBUTE.ALIAS" to group by the
     * the column ALIAS of the table ATTRIBUTE). The result does not contain the words "GROUP BY", thus
     * the results of multiple calls to this method with different attributes can be concatennated into
     * a comple GROUP BY clause.
     * 
     * @param QueryPartSorter $qpart
     * @param string $select_from
     * @return string
     */
    protected function buildSqlGroupBy(QueryPart $qpart, $select_from = null)
    {
        $output = '';
        if ($this->isSqlSelectStatement($this->buildSqlDataAddress($qpart->getAttribute())) === true) {
            // Seems like SQL statements are not supported in the GROUP BY clause in general
            throw new QueryBuilderException('Cannot use the attribute "' . $qpart->getAttribute()->getAliasWithRelationPath() . '" for aggregation in an SQL data source, because it\'s data address is defined via custom SQL statement');
        } else {
            // If it's not a custom SQL statement, it must be a column, so we need to prefix it with the table alias
            if ($select_from === null) {
                $select_from = $qpart->getAttribute()->getRelationPath()->toString() ? $qpart->getAttribute()->getRelationPath()->toString() : $this->getMainObject()->getAlias();
            }
            if ($select_from) {
                $select_from = $this->getShortAlias($select_from . $this->getQueryId());
            }
            $output = $this->buildSqlSelect($qpart, $select_from, null, '', false, false);
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
        } elseif (strlen($full_alias) <= $this->getShortAliasMaxLength() && $this->getCleanAlias($full_alias) == $full_alias && false === in_array(mb_strtoupper($full_alias), $this->getReservedWords())) {
            $short_alias = $full_alias;
        } else {
            $this->short_alias_index ++;
            $short_alias = $this->short_alias_prefix . str_pad($this->short_alias_index, 3, '0', STR_PAD_LEFT) . $this->short_alias_replacer . substr($this->getCleanAlias($full_alias), - 1 * ($this->getShortAliasMaxLength() - 3 - 1 - 1));
            $this->short_aliases[$full_alias] = $short_alias;
        }
        
        return $short_alias;
    }
    
    /**
     * 
     * @param string $alias
     * @return string
     */
    protected function getCleanAlias(string $alias) : string
    {
        $output = '';
        $output = str_replace($this->getShortAliasForbiddenChars(), '_', $alias);
        return $output;
    }
    
    /**
     * 
     * @param string $short_alias
     * @return string
     */
    protected function getFullAlias(string $short_alias) : string
    {
        $full_alias = array_search($short_alias, $this->short_aliases, true);
        if ($full_alias === false) {
            $full_alias = $short_alias;
        }
        return $full_alias;
    }
    
    /**
     * Returns TRUE if the given string is complex SQL-statement (= not a simple column references)
     * and FALSE otherwise.
     *
     * It is important to know this, because you cannot write to statements etc.
     *
     * @param string|null $string
     * @return boolean
     */
    protected function isSqlStatement($string) : bool
    {
        if ($string === null || $string === '') {
            return false;
        }
        return mb_strpos($string, '(') !== false && mb_strpos($string, ')') !== false;
    }
    
    /**
     * Returns TRUE if the give string is a SELECT statement and FALSE otherwise.
     *
     * This does NOT check, if it's a valid select - but merely looks for the SELECT
     * keyword.
     *
     * @param string $string
     * @return bool
     */
    protected function isSqlSelectStatement(string $string) : bool
    {
        return stripos($string, 'SELECT ') !== false;
    }
    
    /**
     * 
     * @param string|NULL
     * @return string|NULL
     */
    protected function decodeBinary($value) : ?string
    {
        if ($value === null) {
            return null;
        }
        $hex_value = bin2hex($value);
        return ($hex_value ? '0x' : '') . $hex_value;
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
        $table_alias = $table_alias ?? $this->getShortAlias($this->getMainObject()->getAlias() . $this->getQueryId());
        $customWhere = $this->replacePlaceholdersInSqlAddress($custom_statement, null, ['~alias' => $table_alias]);
        return $original_where_statement . ($original_where_statement ? ' ' . $operator . ' ' : '') . $customWhere;
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
    
    /**
     * 
     * @param string $data_address
     * @param RelationPath $relation_path
     * @param array $static_placeholders
     * @param string $select_from
     * @throws QueryBuilderException
     * @return mixed
     */
    protected function replacePlaceholdersInSqlAddress($data_address, RelationPath $relation_path = null, array $static_placeholders = null, $select_from = null)
    {
        $data_address = $this->findSqlDialect($data_address);
        $original_data_address = $data_address;
        
        if (! empty($static_placeholders)){
            $static_phs = array_map(function($ph){return '[#' . $ph . '#]';}, array_keys($static_placeholders));
            $static_values = array_values($static_placeholders);
            $data_address = str_replace($static_phs, $static_values, $data_address);
        }
        
        if ($relation_path){
            $prefix = $relation_path->toString();
        }
        
        $baseObj = $relation_path !== null ? $relation_path->getEndObject() : $this->getMainObject();
        foreach (StringDataType::findPlaceholders($data_address) as $ph) {
            if (StringDataType::startsWith($ph, '=')) {
                $formula = FormulaFactory::createFromString($this->getWorkbench(), $ph);
                if ($formula->isStatic() === false) {
                    throw new QueryBuilderException('Cannot use placeholder [#' . $ph . '#] in data address "' . $original_data_address . '": the used formula is not static! Only static formulas are supported in data address placeholders!');
                }
                $data_address = StringDataType::replacePlaceholder($data_address, $ph, $formula->evaluate());
                continue;
            }
            $ph_has_relation = $baseObj->hasAttribute($ph) && ! $baseObj->getAttribute($ph)->getRelationPath()->isEmpty() ? true : false;                
            $ph_attribute_alias = RelationPath::join($prefix, $ph);
            
            // If the placeholder is not part of the query already, create a new query part.
            if (null !== $qpart = $this->getAttribute($ph_attribute_alias)) {
                $qpart->setUsedInPlaceholders(true);
            } else {
                // Throw an error if the attribute cannot be resolved relative to the main object of the query
                try {
                    $qpart = new QueryPartSelect($ph_attribute_alias, $this, null, null);
                } catch (MetaAttributeNotFoundError $e){
                    throw new QueryBuilderException('Cannot use placeholder [#' . $ph . '#] in data address "' . $original_data_address . '": no attribute "' . $ph_attribute_alias . '" found for query base object ' . $this->getMainObject()->getAliasWithNamespace() . '!', null, $e);
                }
                // If the new query part includes relations (= requires joins), add it to the query and mark the query
                // as dirty to force recalculation of the SQL. In the next SQL build attempt, the placeholder will already
                // be part of the query and thus will also make sure, that all JOINs are there.
                if ($ph_has_relation){
                    $this->setDirty(true);
                    $qpart->excludeFromResult(true);
                    $qpart->setUsedInPlaceholders(true);
                    $this->addQueryPart($qpart);
                }
            }

            // Generate a SELECT clause for the placeholder
            if ($ph_has_relation) {
                $ph_select = $this->buildSqlSelect($qpart, null, null, false);
            } else {
                $ph_select = $this->buildSqlSelect($qpart, $select_from, null, false);
            }

            // Make binary columns string-compatible by convirting them to HEX strings. This allows
            // to use UUIDs in most custom SQL SELECTs very easily. On the other hand, it will probably
            // break SQLs where the value is supposed to be raw binary. Not sure, if this is
            // always a good idea, but it certainly helps in most cases!
            if($this->isBinaryColumn($qpart)) {
                $ph_select = $this->buildSqlSelectBinaryAsHEX($ph_select);
            }

            // Replace the placeholder with the generated SQL statement
            $data_address = str_replace('[#' . $ph . '#]', $ph_select, $data_address);
        }
        return $data_address;
    }

    /**
     * @param string $sqlJoin
     * @param string $leftTableAlias
     * @param string $rightTableAlias
     * @param MetaObjectInterface $leftObj
     * @param MetaObjectInterface $rigthObj
     * @param AbstractSqlBuilder|null $leftSubquery
     * @param AbstractSqlBuilder|null $rightSubquery
     * @param string|null $joinedTableSQL
     * @return string
     */
    protected function replacePlaceholdersInSqlJoin(
        string $sqlJoin, 
        string $leftTableAlias, 
        string $rightTableAlias, 
        MetaRelationInterface $relation, 
        ?self $leftSubquery = null, 
        ?self $rightSubquery = null, 
        ?string &$joinedTableSQL = null
    ) : string
    {
        $leftQuery = $leftSubquery ?? $this;
        $rightQuery = $rightSubquery ?? $this;
        $leftTableAlias = $leftTableAlias ?? $leftQuery->getShortAlias($relation->getLeftObject()->getAlias() . $leftQuery->getQueryId());
        $rightTableAlias = $rightTableAlias ?? $rightQuery->getShortAlias($relation->getRightObject()->getAlias() . $rightQuery->getQueryId());
        
        $phs = StringDataType::findPlaceholders($sqlJoin);
        $phVals = [];
        foreach ($phs as $ph) {
            switch (true) {
                case StringDataType::startsWith($ph, '~left:'):
                    $phVals[$ph] = $leftQuery->replacePlaceholdersInSqlAddress(
                        '[#' . StringDataType::substringAfter($ph, '~left:') . '#]', 
                        null, 
                        null, 
                        $leftTableAlias
                    );
                    break;
                case StringDataType::startsWith($ph, '~right:'):
                    $attrAlias = StringDataType::substringAfter($ph, '~right:');
                    $relPath = $leftQuery !== $rightQuery ? null : RelationPathFactory::createForObject($relation->getLeftObject())->appendRelation($relation);
                    // If the placeholder is a RELATED attribute of the right object, we will need to JOIN all tables
                    // along the relation path. For example `[#~right:rel1__rel2__name#]` should produce this SQL:
                    // ```
                    // JOIN (
                    //      table_to_join 
                    //      JOIN rel1 ON ...
                    //      JOIN rel2
                    // ) ON ... AND ... = rel2.name
                    // ```
                    // We use a trick here: the related attribute is put into a temporary subquery, which is used
                    // to render the JOINs only - these JOINs are then put into braces together with right table
                    // alias
                    if ($relPath !== null && $relPath->getEndObject()->hasAttribute($attrAlias) && $relPath->getEndObject()->getAttribute($attrAlias)->isRelated()) {
                        /* @var $joinSubquery self */
                        $joinSubquery = QueryBuilderFactory::createFromSelector($this->getSelector());
                        $joinSubquery->setMainObject($relPath->getEndObject());
                        // Make sure, the subquery uses its own table aliases - just in case the same tables are used
                        // in the main query.
                        $joinSubquery->setQueryId($this->getNextSubqueryId());
                        $phVals[$ph] = $joinSubquery->replacePlaceholdersInSqlAddress(
                            '[#' . $attrAlias . '#]',
                            null,
                            null,
                            $rightTableAlias
                        );
                        $phJoins = [];
                        // Since we did not add any query parts to the subquery, all its query parts should be required
                        // for the placeholder. Actually, it probably always be exactly one query part.
                        foreach ($joinSubquery->getAttributes() as $qpart) {
                            if ($qpart->isUsedInPlaceholders() && $qpart->getAttribute()->getObject() !== $joinSubquery->getMainObject()) {
                                $phJoins = array_merge($phJoins, $joinSubquery->buildSqlJoins($qpart, $rightTableAlias));
                            }
                        }
                        $joinedTableSQL = '(' . trim(trim($joinedTableSQL), "()") . implode("\n", $phJoins) . ')';
                    } else {
                        // If the right placeholder does not need relations, we do not need a subquery and can just
                        // use the regular placeholder replacer
                        $attrAlias = StringDataType::substringAfter($ph, '~right:');
                        $phVals[$ph] = $rightQuery->replacePlaceholdersInSqlAddress(
                            '[#' . $attrAlias . '#]',
                            $relPath,
                            null,
                            $rightTableAlias
                        );
                    }
                    break;
                case $ph === '~left_alias':
                    $phVals[$ph] = $leftTableAlias;
                    break;
                case $ph === '~right_alias':
                    $phVals[$ph] = $rightTableAlias;
                    break;
                default:
                    throw new PlaceholderNotFoundError('Invalid placeholder "' . $ph . '" in custom SQL_JOIN_ON of object ' . $leftQuery->getMainObject()->__toString() . ': ' . $sqlJoin);
            }
        }
        
        return StringDataType::replacePlaceholders($sqlJoin, $phVals);
    }
    
    /**
     * The SQL builder can join of related objects as long as they are located in the same database.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        // TODO Check if all objects along the relation path also can be read
        $attrObj = $attribute->getObject();
        $queryObj = $this->getMainObject();
        if ($attrObj->getDataSource()->getId() === $queryObj->getDataSource()->getId()) {
            return true;
        }
        $attrConn = $attrObj->getDataConnection();
        $queryConn = $queryObj->getDataConnection();
        if ($attrConn === $queryConn) {
            return true;
        }
        if ($queryConn instanceof SqlDataConnectorInterface) {
            if ($queryConn->canJoin($attrConn)) {
                return true;
            }
        }
        return false;
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
     * @return QueryPartFilter
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
    
    
    
    /**
     * Returns TRUE if the resulting data can be assumed to contain only a single UID
     * of the provided object per row.
     *
     * In other words, attributes of this object are group-safe - i.e. can be selected
     * without a grouping function.
     *
     * The optional parameters $filterGroup and $aggregations allow to do custom checks
     * against specific filters and aggregations. If not set, filters and aggregations
     * of the query will be used automatically.
     *
     * Technically, this method checks for the following conditions:
     * 
     * 1. Is the query aggregated by an attribute based on the UID column of the given object
     * 2. Is the query aggregated by an attribute, that is part of the relation path to the object 
     * (if that is known). That is, if we have ORDERS aggregated by CUSTOMER the attribute
     * CUSTOMER__ADDRESS__COUNTRY is group safe as all orders in an aggregated row have the
     * same customer and thus the same address and country. 
     * 3. Is there an equals-filter, over the UID of the given object or anohter attribute with the same data address
     * 4. Is there an equals-filter over a forward-relation to the given object
     *
     * @param MetaObjectInterface $object
     * @param QueryPartFilterGroup $filterGroup
     * @param QueryPartAttribute[] $aggregations
     *
     * @return array
     */
    protected function isObjectGroupSafe(MetaObjectInterface $object, QueryPartFilterGroup $filterGroup = null, array $aggregations = null, MetaRelationPathInterface $relPathFromQuery = null) : bool
    {
        if ($filterGroup === null) {
            $filterGroup = $this->getFilters();
        }
        
        // The whole logic only works if multiple conditions or condition groups are combined via AND!
        if ($filterGroup->getOperator() !== EXF_LOGICAL_AND) {
            // If it's not AND expclicitly, it's AND-equivalent if the operator is OR or XOR and there
            // is only one operand, so exclude this case
            if (! (($filterGroup->getOperator() === EXF_LOGICAL_OR || $filterGroup->getOperator() === EXF_LOGICAL_XOR) && (count($filterGroup->getFilters()) + count($filterGroup->getNestedGroups())) === 1)) {
                return false;
            }
        }
        
        if ($aggregations === null) {
            $aggregations = $this->getAggregations();
        }
        
        // Condition (1) - see method doc
        if ($object->hasUidAttribute()) {
            $uidDataAddress = $this->buildSqlDataAddress($object->getUidAttribute());
            foreach ($aggregations as $qpart) {
                if ($qpart->getAttribute()->getObject()->isExactly($object) && $this->buildSqlDataAddress($qpart) === $uidDataAddress) {
                    return true;
                }
            }
        }
        
        // Condition (2) - see method doc
        if ($relPathFromQuery !== null) {
            foreach ($aggregations as $qpart) {
                $relPathStr = $relPathFromQuery->toString();
                $qpartAlias = $qpart->getAlias();
                // FIXME #sql-is-group-safe if the relaton path matches the aggregator, the
                // object is group safe too. However, currenly this situation is handled in
                // each query builder separately (see hashtag). Need merge both logics!
                // if ($relPathStr === $qpartAlias || StringDataType::startsWith($relPathStr, $qpartAlias . RelationPath::getRelationSeparator())) {
                if (StringDataType::startsWith($relPathStr, $qpartAlias . RelationPath::getRelationSeparator())) {
                    return true;
                }
            }
        }
        
        foreach ($filterGroup->getFilters() as $qpart) {
            
            // TODO The current checks do not really ensure, that the object is unique. Need a better idea!
            $isFilterEquals = false;
            if ($qpart->getComparator() == EXF_COMPARATOR_IS || $qpart->getComparator() == EXF_COMPARATOR_EQUALS) {
                $isFilterEquals = true;
            } elseif ($qpart->getComparator() === EXF_COMPARATOR_IN) {
                $values = is_array($qpart->getCompareValue()) ? $qpart->getCompareValue() : explode($qpart->getValueListDelimiter(), $qpart->getCompareValue());
                if (count($values) === 1) {
                    $isFilterEquals = true;
                }
            }
            
            if ($isFilterEquals) {
                $filterAttr = $qpart->getAttribute();
                // Condition (3) - see method doc
                if ($filterAttr->getObject()->isExactly($object) && ($filterAttr->isExactly($object->getUidAttribute()) || $this->buildSqlDataAddress($filterAttr) && $this->buildSqlDataAddress($filterAttr) === $this->buildSqlDataAddress($object->getUidAttribute()))) {
                    return true;
                }
                // Condition (4) - see method doc
                if ($filterAttr->isRelation() === true && $filterAttr->getRelation()->isForwardRelation() === true) {
                    if ($this->getMainObject()->getRelatedObject($qpart->getAlias())->isExactly($object)) {
                        return true;
                    }
                }
            }
        }
        
        foreach ($filterGroup->getNestedGroups() as $qpart) {
            if ($qpart->getOperator() === EXF_LOGICAL_AND && $this->isObjectGroupSafe($object, $qpart) === true) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Returns an SQL commentary containing the given text.
     *
     * @param string $text
     * @return string
     */
    protected function buildSqlComment(string $text) : string
    {
        return '/* ' . str_replace(['/*', '*/'], '', $text) . ' */';
    }
    
    /**
     * Returns TRUE if the query will only return a single line because of aggregation:
     * i.d. all SELECTs have group functions and there is no explicit GROUB BY.
     *
     * @return bool
     */
    protected function isAggregatedToSingleRow() : bool
    {
        if (empty($this->getAggregations()) === false) {
            return false;
        }
        
        foreach ($this->getAttributes() as $qpart) {
            if (! $qpart->getAggregator()) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 
     * @return bool
     */
    public function isAggregated() : bool
    {
        return ! empty($this->getAggregations());
    }
    
    /**
     * 
     * @return bool
     */
    protected function isSubquery() : bool
    {
        return $this->query_id > 0;
    }
    
    /**
     * Returns the SQL data address for the given query part, meta object or attribute.
     * 
     * Detects multi-dialect data addresses and extracts the dialect, that fits best
     * according to getSqlDialects(). If multiple tags fit, the first tag in getSqlDialects()
     * wins - that way query builders can define the priority of dialect tags.
     * 
     * @param object $qpartOrModelElement
     * 
     * @throws QueryBuilderException
     * 
     * @return string
     */
    protected function buildSqlDataAddress(object $qpartOrModelElement, string $operation = self::OPERATION_READ) : string
    {
        switch (true) {
            case $qpartOrModelElement instanceof QueryPartAttribute:
            case $qpartOrModelElement instanceof MetaAttributeInterface:
                $addr = $qpartOrModelElement->getDataAddress();
                break;
            case $qpartOrModelElement instanceof MetaObjectInterface:
                $addr = $qpartOrModelElement->getDataAddress();
                if ($operation === self::OPERATION_READ && $customFrom = $qpartOrModelElement->getDataAddressProperty(static::DAP_SQL_READ_FROM)) {
                    $addr = $customFrom;   
                }
                break;
            default:
                throw new QueryBuilderException('Cannot get data address from ' . get_class($qpartOrModelElement) . ': expecting query part, meta object or attribute!');
        }
        
        if ($addr === '' || $addr === null) {
            return $addr ?? '';
        }
        
        return $this->findSqlDialect($addr);
    }

    /**
     * Retruns the current SQL dialect from a multi-dialect statement
     * 
     * See class-level comment on "Multi-dialect data addresses". 
     * 
     * If the given SQL is a multi-dialect statement, this method will strip all non-applicable dialects and return
     * only the SQL for the dialect of this particular query builder.
     * 
     * ```
     *  |@MySQL: JSON_UNQUOTE(JSON_EXTRACT([#~alias#].task_uxon, '$.action'))
     *  |@T-SQL: JSON_VALUE([#~alias#].task_uxon, '$.action')
     *  |
     * ```
     * 
     * In the above example, this method would return the first life in the `MySqlBuilder` and the second one for `MsSqlBuilder`.
     * 
     * @param string $sql
     * @throws \exface\Core\Exceptions\QueryBuilderException
     * @return string
     */
    protected function findSqlDialect(string $sql) : string
    {
        if (StringDataType::startsWith($sql, '@')) {
            $stmts = preg_split('/(^|\R)@/', $sql);
            $tags = $this->getSqlDialects();
            // Start with the first supported tag and see if it matches any statement. If not,
            // proceed with the next tag, etc.
                foreach ($tags as $tag) {
                    $tag = $tag . ':';
                    foreach ($stmts as $stmt) {
                    if (StringDataType::startsWith($stmt, $tag, false)) {
                        return trim(StringDataType::substringAfter($stmt, $tag));
                    }
                }
            }
            // If no tag matched, throw an error!
            throw new QueryBuilderException('Multi-dialect SQL data address "' . StringDataType::truncate($sql, 50, false, true, true) . '" does not contain a statement for with any of the supported dialect-tags: `@' . implode(':`, `@', $this->getSqlDialects()) . ':`', '7DGRY8R');
        }
        return $sql;
    }
    
    /**
     * Returns the names of SQL dialect-tags supported by this query builder in multi-dialect statements.
     * 
     * Override this method to add new dialects. Keep in mind, that 
     * 
     * @return string[]
     */
    protected function getSqlDialects() : array
    {
        return ['OTHER'];
    }
    
    protected function setDirty(bool $trueOrFalse) : AbstractSqlBuilder
    {
        $this->dirtyFlag = $trueOrFalse;
        return $this;
    }
    
    protected function isDirty() : bool
    {
        return $this->dirtyFlag;
    }

    /**
     * Returns TRUE if the given data address points to data inside a JSON value and FALSE otherwise
     * 
     * TODO this method should go into an ISupportJSONQueries interface for query builders in order
     * to allow other code to understand, if structured data can be handled. The exact syntax of
     * a JSON address is up to the query builder, but we will probably need to distinguish between
     * those, that can handle JSON and others.
     * 
     * @param string $str
     * @return bool
     */
    public function isJsonDataAddress(string $str) : bool
    {
        return mb_strpos($str, '::$');
    }

    /**
     * Splits the given JSON data address into a SQL column and a JSON path.
     * 
     * Use like this:
     * 
     * ```
     * list($column, $jsonPath) = $this->parseJsonDataAddress('myCol::$.prop1')
     * // $column = "myCol"
     * // $jsonPath = "$.prop1"
     * ```
     * 
     * @param string $str
     * @return string[] containing $column and $jsonPath
     */
    protected function parseJsonDataAddress(string $str) : array
    {
        list($column, $jsonPath) = explode('::$', $str, 2);
        return [$column, '$' . $jsonPath];
    }

    /**
     * Override this method to implement a DB specific JSON reader
     * 
     * @param string $address
     * @param string $jsonPath
     * @return string
     */
    protected function buildSqlJsonRead(string $address, string $jsonPath) : string
    {
        
        if ($this->isAggregated()) {
            // If aggregating, we cannot do any prechecks because they do not contain any aggregator,
            // so we really need to use the data address as-is here.
            $jsonAddress = $address;
        } else {
            // TODO when exactly do we need to distinguish between NULL and {} when reading?
            $jsonAddress = $this->buildSqlJsonInitial($address);
        }
        return "JSON_VALUE({$jsonAddress}, '{$jsonPath}')";
    }

    /**
     * Builds an inline SQL-Snippet that encodes the provided key value pairs as JSON, using
     * only native SQL functions of the corresponding dialect.
     *
     * - Keys must be strings, matching the following pattern: `$.key`.
     * - This function can only generate a JSON that is exactly 1 level deep. Keys that try to access
     * deeper levels (e.g. `$.allowed.forbidden`) will not function properly.
     *
     * NOTE: JSON manipulation may not be fully supported by all dialects. This functions returns MySQL
     * syntax by default.
     *
     * TODO geb 2025-01-21: We might need a better default implementation.
     *
     * @param array  $keyValuePairs
     * @param string $initialJson
     * @return string
     */
    protected function buildSqlJsonEncodeAsFlat(array $keyValuePairs, string $initialJson = "'{}'") : string
    {
        $resultJson = $initialJson;

        foreach ($keyValuePairs as $attributePath => $attributeValue) {
            $resultJson = "JSON_SET(" . $resultJson . ", '" . $attributePath . "', " . $attributeValue . ")";
        }

        return $resultJson;
    }

    /**
     * Build an inline SQL-Snippet that generates an initial JSON value for native JSON-Functions.
     *
     * NOTE: JSON manipulation may not be fully supported by all dialects. This functions returns MySQL
     *  syntax by default.
     *
     * @param string $columnName
     * @return string
     */
    protected function buildSqlJsonInitial(string $columnName) : string
    {
        return <<<SQL

CASE 
    WHEN {$columnName} IS NOT NULL AND JSON_VALID({$columnName})
    THEN {$columnName}
    ELSE '{}'
END 
SQL;
    }

    /**
     * Override this method to implement a DB specific JSON writer
     * 
     * @param string $address
     * @param string $jsonPath
     * @param string $escapedValue
     * @return string
     */
    protected function buildSqlJsonWrite(string $address, string $jsonPath, string $escapedValue) : string
    {
        return "JSON_SET({$address}, '{$jsonPath}', $escapedValue)";
    }

    /**
     * Returns TRUE if the given query part or attribute reference a binary column in the DB and FALSE otherwise
     *
     * @param MetaAttributeInterface|QueryPartAttribute $qpartOrAttr
     * @return bool
     */
    protected function isBinaryColumn(MetaAttributeInterface|QueryPartAttribute $qpartOrAttr) : bool
    {
        $sqlType = ($qpartOrAttr->getDataAddressProperty(static::DAP_SQL_DATA_TYPE) ?? '');
        return strcasecmp($sqlType, self::DAP_SQL_DATA_TYPE_BINARY) === 0;
    }
}