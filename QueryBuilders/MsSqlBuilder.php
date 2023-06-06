<?php
namespace exface\Core\QueryBuilders;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\QueryBuilder\QueryPart;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * A query builder for Microsoft SQL Server 2012+ (T-SQL).
 * 
 * Supported dialect tags in multi-dialect statements (in order of priority): `@T-SQL:`, `@MSSQL:`, `@OTHER:`.
 *
 * ## Data addresses and options
 * 
 * See `AbstractSqlBuilder` for available data address options!
 * 
 * @author Andrej Kabachnik
 *        
 */
class MsSqlBuilder extends AbstractSqlBuilder
{
    /**
     * Set to TRUE to add `WITH (NOLOCK)` in SELECT queries based on this object (also affects all JOINs!)
     * 
     * Be careful to only use `WITH (NOLOCK)` in SELECT statements on tables that have a clustered index.
     * `WITH(NOLOCK)` is often exploited as a magic way to speed up database read transactions. The result 
     * set can contain rows that have not yet been committed, that are often later rolled back. If `WITH(NOLOCK)` 
     * is applied to a table that has a non-clustered index, then row-indexes can be changed by other transactions 
     * as the row data is being streamed into the result-table. This means that the result-set can be missing rows 
     * or display the same row multiple times.
     * 
     * If transaction isolation level is set to `READ COMMITTED`, it adds an additional issue where data is 
     * corrupted within a single column where multiple users change the same cell simultaneously.
     *
     * @uxon-property SQL_SELECT_WITH_NOLOCK
     * @uxon-target object
     * @uxon-type bool
     * @uxon-default false
     */
    const DAP_SQL_SELECT_WITH_NOLOCK = 'SQL_SELECT_WITH_NOLOCK';
    
    const MAX_BUILD_RUNS = 5;
    
    private $selectWithNoLock = null;
    
    /**
     *
     * @param QueryBuilderSelectorInterface $selector
     */
    public function __construct(QueryBuilderSelectorInterface $selector)
    {
        parent::__construct($selector);
        // @see https://learn.microsoft.com/en-us/sql/t-sql/language-elements/reserved-keywords-transact-sql?view=sql-server-ver16
        $this->setReservedWords(['ABSOLUTE', 'EXEC', 'OVERLAPS', 'ACTION', 'EXECUTE', 'PAD', 'ADA', 'EXISTS', 'PARTIAL', 'ADD', 'EXTERNAL', 'PASCAL', 'ALL', 'EXTRACT', 'POSITION', 'ALLOCATE', 'FALSE', 'PRECISION', 'ALTER', 'FETCH', 'PREPARE', 'AND', 'FIRST', 'PRESERVE', 'ANY', 'FLOAT', 'PRIMARY', 'ARE', 'FOR', 'PRIOR', 'AS', 'FOREIGN', 'PRIVILEGES', 'ASC', 'FORTRAN', 'PROCEDURE', 'ASSERTION', 'FOUND', 'PUBLIC', 'AT', 'FROM', 'READ', 'AUTHORIZATION', 'FULL', 'REAL', 'AVG', 'GET', 'REFERENCES', 'BEGIN', 'GLOBAL', 'RELATIVE', 'BETWEEN', 'GO', 'RESTRICT', 'BIT', 'GOTO', 'REVOKE', 'BIT_LENGTH', 'GRANT', 'RIGHT', 'BOTH', 'GROUP', 'ROLLBACK', 'BY', 'HAVING', 'ROWS', 'CASCADE', 'HOUR', 'SCHEMA', 'CASCADED', 'IDENTITY', 'SCROLL', 'CASE', 'IMMEDIATE', 'SECOND', 'CAST', 'IN', 'SECTION', 'CATALOG', 'INCLUDE', 'SELECT', 'CHAR', 'INDEX', 'SESSION', 'CHAR_LENGTH', 'INDICATOR', 'SESSION_USER', 'CHARACTER', 'INITIALLY', 'SET', 'CHARACTER_LENGTH', 'INNER', 'SIZE', 'CHECK', 'INPUT', 'SMALLINT', 'CLOSE', 'INSENSITIVE', 'SOME', 'COALESCE', 'INSERT', 'SPACE', 'COLLATE', 'INT', 'SQL', 'COLLATION', 'INTEGER', 'SQLCA', 'COLUMN', 'INTERSECT', 'SQLCODE', 'COMMIT', 'INTERVAL', 'SQLERROR', 'CONNECT', 'INTO', 'SQLSTATE', 'CONNECTION', 'IS', 'SQLWARNING', 'CONSTRAINT', 'ISOLATION', 'SUBSTRING', 'CONSTRAINTS', 'JOIN', 'SUM', 'CONTINUE', 'KEY', 'SYSTEM_USER', 'CONVERT', 'LANGUAGE', 'TABLE', 'CORRESPONDING', 'LAST', 'TEMPORARY', 'COUNT', 'LEADING', 'THEN', 'CREATE', 'LEFT', 'TIME', 'CROSS', 'LEVEL', 'TIMESTAMP', 'CURRENT', 'LIKE', 'TIMEZONE_HOUR', 'CURRENT_DATE', 'LOCAL', 'TIMEZONE_MINUTE', 'CURRENT_TIME', 'LOWER', 'TO', 'CURRENT_TIMESTAMP', 'MATCH', 'TRAILING', 'CURRENT_USER', 'MAX', 'TRANSACTION', 'CURSOR', 'MIN', 'TRANSLATE', 'DATE', 'MINUTE', 'TRANSLATION', 'DAY', 'MODULE', 'TRIM', 'DEALLOCATE', 'MONTH', 'TRUE', 'DEC', 'NAMES', 'UNION', 'DECIMAL', 'NATIONAL', 'UNIQUE', 'DECLARE', 'NATURAL', 'UNKNOWN', 'DEFAULT', 'NCHAR', 'UPDATE', 'DEFERRABLE', 'NEXT', 'UPPER', 'DEFERRED', 'NO', 'USAGE', 'DELETE', 'NONE', 'USER', 'DESC', 'NOT', 'USING', 'DESCRIBE', 'NULL', 'VALUE', 'DESCRIPTOR', 'NULLIF', 'VALUES', 'DIAGNOSTICS', 'NUMERIC', 'VARCHAR', 'DISCONNECT', 'OCTET_LENGTH', 'VARYING', 'DISTINCT', 'OF', 'VIEW', 'DOMAIN', 'ON', 'WHEN', 'DOUBLE', 'ONLY', 'WHENEVER', 'DROP', 'OPEN', 'WHERE', 'ELSE', 'OPTION', 'WITH', 'END', 'OR', 'WORK', 'END-EXEC', 'ORDER', 'WRITE', 'ESCAPE', 'OUTER', 'YEAR', 'EXCEPT', 'OUTPUT', 'ZONE', 'EXCEPTION']);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge(['T-SQL', 'MSSQL'], parent::getSqlDialects());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getShortAliasMaxLength()
     */
    protected function getShortAliasMaxLength() : int
    {
        return 64;
    }
    
    /**
     * SELECT queries in MS SQL 
     * 
     * Differences between MS SQL and MySQL SELECTs:
     * 
     * - In MS SQL queryies with GROUP BY every select-clause MUST either have a group-function
     * or be part of the GROUP BY itself.
     * - SELECT * cannot be used on an inner query if the outer query has a GROUP BY (consequence
     * of the above)
     * - A sorter can only be used, if there is no GROUP BY, or the sorted attribute has unique values within the group
     * - A paged query requires an ORDER BY in any case
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlQuerySelect()
     */
    public function buildSqlQuerySelect(int $buildRun = 0)
    {
        $this->setDirty(false);
        
        $where = '';
        $having = '';
        $group_by = '';
        $group_safe_attribute_aliases = array();
        $order_by = '';
        $selects = array();
        $select = '';
        $select_comment = '';
        $joins = array();
        $join = '';
        $enrichment_selects = [];
        $enrichment_select = '';
        $enrichment_joins = array();
        $enrichment_join = '';
        $limit = '';
        
        // WHERE
        $where = $this->buildSqlWhere($this->getFilters());
        $having = $this->buildSqlHaving($this->getFilters());
        $joins = $this->buildSqlJoins($this->getFilters());
        // Object data source property SQL_SELECT_WHERE -> WHERE
        if ($custom_where = $this->getMainObject()->getDataAddressProperty(static::DAP_SQL_SELECT_WHERE)) {
            $where = $this->appendCustomWhere($where, $custom_where);
        }
        $where = $where ? "\n WHERE " . $where : '';
        $having = $having ? "\n HAVING " . $having : '';
        
        // GROUP BY
        foreach ($this->getAggregations() as $qpart) {
            $group_by .= ', ' . $this->buildSqlGroupBy($qpart);
        }
        $group_by = $group_by ? ' GROUP BY ' . substr($group_by, 2) : '';
        
        // SELECT
        /*	@var $qpart \exface\Core\CommonLogic\QueryBuilder\QueryPartSelect */
        foreach ($this->getAttributes() as $qpart) {
            $qpartAttr = $qpart->getAttribute();
            
            // First see, if the attribute has some kind of special data type (e.g. binary)
            if ($qpartAttr->getDataAddressProperty(static::DAP_SQL_DATA_TYPE) == 'binary') {
                $this->addBinaryColumn($qpart->getAlias());
            }
            
            switch (true) {
                // Put the UID-Attribute in the core query as well as in the enrichment select if the query has a GROUP BY.
                // Otherwise the enrichment joins won't work! Be carefull to apply this rule only to the plain UID column, not to columns
                // using the UID with aggregate functions
                case $group_by && $qpartAttr->getObject()->hasUidAttribute() && $qpartAttr->isExactly($qpartAttr->getObject()->getUidAttribute()) && ! $qpart->getAggregator():
                    $selects[] = $this->buildSqlSelect($qpart, null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
                    // In contrast to other SQL builders, we MUST NOT add the UID attribute to the 
                    // enrichment query as this will lead to an error due to the column being
                    // ambiguosly defined. This will happen in particular when filtering with at
                    // least on aggregate_by_attribute.
                    // $enrichment_selects[] = $this->buildSqlSelect($qpart, 'EXFCOREQ', $qpartAttr->getObject()->getUidAttributeAlias());
                    $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
                    break;
                // Add to core query and mark as group-safe
                // if we are not aggregating
                case ! $group_by:
                // or the attribute has an aggregator
                case $qpart->getAggregator():
                // or we aggregate over that attribute
                case $this->isAggregatedBy($qpart):
                    $selects[] = $this->buildSqlSelect($qpart);
                    $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                    $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
                    break;
                // Now we know, we have a GROUP BY
                // Add to enrichment group-safe attributes (those, that do not need group-functions)
                // FIXME allways putting selects for attributes of related group-safe object in the enrichment select will
                // probably break sorting over these attributes because sorting is done in the core query too...
                case $this->isObjectGroupSafe($qpartAttr->getObject(), null, null, $qpartAttr->getRelationPath()) === true:
                    $rels = $qpart->getUsedRelations();
                    $first_rel = false;
                    if (! empty($rels)) {
                        $first_rel = reset($rels);
                        $first_rel_qpart = $this->addAttribute($first_rel->getAliasWithModifier())->excludeFromResult(true);
                        // IDEA this does not support relations based on custom sql. Perhaps this needs to change
                        $selects[] = $this->buildSqlSelect($first_rel_qpart, null, null, $this->buildSqlDataAddress($first_rel_qpart->getAttribute()), ($group_by ? new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX) : null));
                    }
                    $enrichment_selects[] = $this->buildSqlSelect($qpart);
                    $enrichment_joins = array_merge($enrichment_joins, $this->buildSqlJoins($qpart, 'exfcoreq'));
                    $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                    $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
                    break;
                // Add to core query those attributes, that belong directly to objects, we are aggregating
                // over (they can be assumed unique too, since their object is unique per row)
                // FIXME #sql-is-group-safe it should be possible to integrate this into the if-branch with isObjectGroupSafe())
                case $group_by && $this->getAggregation($qpartAttr->getRelationPath()->toString()):
                    $selects[] = $this->buildSqlSelect($qpart, null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
                    $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                    $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
                    break;
                // Skip all non-group-safe attributes when aggregating
                default:
                    $select_comment .= '-- ' . $qpart->getAlias() . ' is ignored because it is not group-safe or ambiguously defined' . "\n";
                    break;
            }
        }
        // Core SELECT
        $select = implode(', ', array_unique(array_filter($selects)));
        $select_comment = $select_comment ? "\n" . $select_comment : '';
        
        // Enrichment SELECT
        $enrichment_select = implode(', ', array_unique(array_filter($enrichment_selects)));
        $enrichment_select = 'EXFCOREQ' . $this->getAliasDelim() . '*' . ($enrichment_select ? ', ' . $enrichment_select : '');
        
        // FROM
        $from = $this->buildSqlFrom();
        
        // JOINs
        $join = implode(' ', $joins);
        $enrichment_join = implode(' ', $enrichment_joins);
        
        $useEnrichment = ($group_by && $where) || $this->getSelectDistinct();
        
        // ORDER BY
        // If there is a limit in the query, ensure there is an ORDER BY even if no sorters given.
        if (empty($this->getSorters()) === true && $this->getLimit() > 0 && $this->isAggregatedToSingleRow() === false) {
            $order_by .= ', ' . $this->buildSqlOrderByDefault($useEnrichment);
        }
        foreach ($this->getSorters() as $qpart) {
            // In MS SQL a sorter can only be used, if there is no GROUP BY, or the sorted attribute has unique values within the group
            if (! $this->getAggregations() || in_array($qpart->getAttribute()->getAliasWithRelationPath(), $group_safe_attribute_aliases)) {
                $order_by .= ', ' . $this->buildSqlOrderBy($qpart);
            } else {
                throw new QueryBuilderException('Cannot sort over "' . $qpart->getAttribute()->getName() . '" (' . $qpart->getAttribute()->getAliasWithRelationPath() . '): it is not aggregated over and SQL Server requires all sorting columns to be used in aggregation when grouping - concider adding the attribute to aggregate_by_attribute_alias!');
            }
        }
        $order_by = $order_by ? ' ORDER BY ' . substr($order_by, 2) : '';
        
        $distinct = $this->getSelectDistinct() ? 'DISTINCT ' : '';
        
        if ($this->getLimit() > 0 && $this->isAggregatedToSingleRow() === false) {
            // Increase limit by one to check if there are more rows (see AbstractSqlBuilder::read())
            $limit = ' OFFSET ' . $this->getOffset() . ' ROWS FETCH NEXT ' . ($this->getLimit()+1) . ' ROWS ONLY';
        }

        if ($useEnrichment) {
            $query = $this->buildSqlQuerySelectWithEnrichment($select, $enrichment_select, $select_comment, $from, $join, $enrichment_join, $where, $group_by, $having, $order_by, $limit, $distinct);
        } else {
            $query = $this->buildSqlQuerySelectWithoutEnrichment($select, $select_comment, $from, $join, $where, $group_by, $having, $order_by, $limit, $distinct);
        }
        
        // See if changes to the query occur while the query was built (e.g. query parts are 
        // added for placeholders, etc.) and rerun the query builder if required.
        // However, do not run it more than X times to avoid infinite recursion.
        if ($this->isDirty() && $buildRun < self::MAX_BUILD_RUNS) {
            return $this->buildSqlQuerySelect($buildRun+1);
        }
        
        return $query;
    }
    
    /**
     * Generates an ORDER BY clause if no sorters are provided (required for paging in MS SQL)
     * 
     * Orders by UID descending if a UID attribute exists or by the first column that is not
     * a custom SQL statement - also descending.
     * 
     * @param bool $useEnrichment
     * @param string $select_from
     * @return string
     */
    protected function buildSqlOrderByDefault(bool $useEnrichment, $select_from = '') : string
    {
        $obj = $this->getMainObject();
        // If no order is specified, sort sort over the UID of the meta object if possible
        if ($obj->hasUidAttribute() && $obj->getUidAttribute()->isSortable() && ! ($obj->getUidAttribute() instanceof CompoundAttributeInterface)) {
            $orderByUidCol = ($useEnrichment ? 'EXFCOREQ' . $this->getAliasDelim() : '') . $obj->getAlias() . '.' . $this->buildSqlDataAddress($obj->getUidAttribute());
            foreach ($this->getAttributes() as $qpart) {
                if ($qpart->getAttribute()->isExactly($obj->getUidAttribute())) {
                    $orderByUidCol = $this->getShortAlias($qpart->getColumnKey());
                }
            }
            $order_by = $orderByUidCol . ' DESC';
        } else {
            // If the object has no UID, sort over the first column in the query, which is simple to sort (i.g. is neither an SQL statement itself nor a compound)
            foreach ($this->getAttributes() as $qpart) {
                if (! $this->checkForSqlStatement($this->buildSqlDataAddress($qpart)) && $qpart->getAttribute()->isSortable() && ! $qpart->isCompound()) {
                    $order_by = $qpart->getColumnKey() . ' DESC';
                    break;
                }
            }
        }
        if (! $order_by) {
            throw new QueryBuilderException('Cannot determine a default ORDER BY clause for a paged MS SQL query: please add some explicit sorters!');
        }
        return ($select_from === '' ? '' : $select_from . $this->getAliasDelim()) . $order_by;
    }
    
    /**
     * 
     * @param string $select
     * @param string $enrichment_select
     * @param string $select_comment
     * @param string $from
     * @param string $join
     * @param string $enrichment_join
     * @param string $where
     * @param string $group_by
     * @param string $having
     * @param string $order_by
     * @param string $limit
     * @param string $distinct
     * @return string
     */
    protected function buildSqlQuerySelectWithEnrichment(string $select, string $enrichment_select, string $select_comment, string $from, string $join, string $enrichment_join, string $where, string $group_by, string $having, string $order_by, string $limit, string $distinct = '') : string
    {
        return "\n SELECT " . $distinct . $enrichment_select . $select_comment . " FROM (SELECT " . $select . " FROM " . $from . $join . $where . $group_by . $having . ") EXFCOREQ " . $enrichment_join . $order_by . $limit;
    }
    
    /**
     * 
     * @param string $select
     * @param string $select_comment
     * @param string $from
     * @param string $join
     * @param string $where
     * @param string $group_by
     * @param string $having
     * @param string $order_by
     * @param string $limit
     * @param string $distinct
     * @return string
     */
    protected function buildSqlQuerySelectWithoutEnrichment(string $select, string $select_comment, string $from, string $join, string $where, string $group_by, string $having, string $order_by, string $limit, string $distinct = '') : string
    {
        return "\n SELECT " . $distinct . $select . $select_comment . " FROM " . $from . $join . $where . $group_by . $having . $order_by . $limit;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlQueryTotals()
     */
    public function buildSqlQueryTotals(int $buildRun = 0)
    {
        $this->setDirty(false);
        
        $group_by = '';
        $totals_joins = array();
        $totals_core_selects = array();
        $totals_selects = array();
        if (count($this->getTotals()) > 0) {
            // determine all joins, needed to perform the totals functions
            foreach ($this->getTotals() as $qpart) {
                $totals_selects[] = $this->buildSqlSelect($qpart, 'EXFCOREQ', $this->getShortAlias($qpart->getColumnKey()), null, $qpart->getTotalAggregator());
                $totals_core_selects[] = $this->buildSqlSelect($qpart);
                $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($qpart));
            }
        }
        
        if ($group_by) {
            $totals_core_selects[] = $this->buildSqlSelect($this->getAttribute($this->getMainObject()->getUidAttributeAlias()), null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
        }
        
        // filters -> WHERE
        $totals_where = $this->buildSqlWhere($this->getFilters());
        $totals_having = $this->buildSqlHaving($this->getFilters());
        $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($this->getFilters()));
        
        // Object data source property SQL_SELECT_WHERE -> WHERE
        if ($custom_where = $this->getMainObject()->getDataAddressProperty(static::DAP_SQL_SELECT_WHERE)) {
            $totals_where = $this->appendCustomWhere($totals_where, $custom_where);
        }
        
        // GROUP BY
        foreach ($this->getAggregations() as $qpart) {
            $group_by .= ', ' . $this->buildSqlGroupBy($qpart);
            $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($qpart));
        }
        
        $totals_select = count($totals_selects) ? ', ' . implode(",\n", $totals_selects) : '';
        $totals_core_select = implode(",\n", $totals_core_selects);
        $totals_from = $this->buildSqlFrom();
        $totals_join = implode("\n ", $totals_joins);
        $totals_where = $totals_where ? "\n WHERE " . $totals_where : '';
        $totals_having = $totals_having ? "\n WHERE " . $totals_having : '';
        $totals_group_by = $group_by ? "\n GROUP BY " . substr($group_by, 2) : '';
        
        // This is a bit of a dirty hack to get the COUNT(*) right if there is a GROUP BY. Just enforce the use of a query with enrichment
        if ($group_by && ! $totals_core_select) {
            $aggregators = $this->getAggregations();
            $totals_core_select = $this->buildSqlSelect(reset($aggregators));
        }
        
        if ($totals_core_select) {
            $totals_query = "\n SELECT COUNT(*) AS EXFCNT " . $totals_select . " FROM (SELECT " . $totals_core_select . ' FROM ' . $totals_from . $totals_join . $totals_where . $totals_group_by . ") EXFCOREQ";
        } else {
            $totals_query = "\n SELECT COUNT(*) AS EXFCNT FROM " . $totals_from . $totals_join . $totals_where . $totals_group_by;
        }
        
        if ($this->isDirty() && $buildRun < self::MAX_BUILD_RUNS) {
            return $this->buildSqlQueryTotals($buildRun+1);
        }
        
        return $totals_query;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelect()
     */
    protected function buildSqlSelect(QueryPartAttribute $qpart, $select_from = null, $select_column = null, $select_as = null, $aggregator = null, bool $make_groupable = null)
    {        
        $sql = parent::buildSqlSelect($qpart, $select_from, $select_column, $select_as, $aggregator, $make_groupable);
        $aggr = $aggregator ?? $qpart->getAggregator();
        if ($qpart->getQuery()->isSubquery() && $qpart->getQuery()->isAggregatedBy($qpart) && $aggr && ($aggr->getFunction()->getValue() === AggregatorFunctionsDataType::LIST_DISTINCT || $aggr->getFunction()->getValue() === AggregatorFunctionsDataType::LIST_ALL)) {
            $sql = StringDataType::substringBefore($sql, ' AS ', $sql, false, true);
        }
        return $sql;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelectNullCheckFunctionName()
     */
    protected function buildSqlSelectNullCheckFunctionName()
    {
        return 'ISNULL';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getReadResultRows()
     */
    protected function getReadResultRows(SqlDataQuery $query) : array
    {
        $rows = parent::getReadResultRows($query);
        foreach ($this->getAttributes() as $qpart) {
            $colKey = $qpart->getColumnKey();
            $type = $qpart->getDataType();
            switch (true) {
                case $type instanceof DateTimeDataType:
                case $type instanceof DateDataType:
                    foreach ($rows as $nr => $row) {
                        $val = $row[$colKey];
                        if ($val instanceof \DateTime) {
                            $val = $type::formatDateNormalized($val);
                            $rows[$nr][$qpart->getColumnKey()] = $val;
                        }
                    }
                    break;
            }
        }
        return $rows;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlGroupByExpression()
     */
    protected function buildSqlGroupByExpression(QueryPartAttribute $qpart, $sql, AggregatorInterface $aggregator){
        $args = $aggregator->getArguments();
        $function_name = $aggregator->getFunction()->getValue();
        
        switch ($function_name) {
            case AggregatorFunctionsDataType::LIST_DISTINCT:
            case AggregatorFunctionsDataType::LIST_ALL:
                // This is a VERY strange way to concatennate row values, but it seems to be the only
                // one available in SQL Server: STUFF(CAST(( SELECT ... FOR XML PATH(''), TYPE) AS VARCHAR(max)), 1, {LengthOfDelimiter}, '')
                // Since in case of subselects the `...` needs to be replaced by the whole subselect,
                // we need to split the logic in two: `STUFF...` goes here and `FOR XML...` goes in
                // buildSqlSelectSubselect() or buildSqlSelectGrouped() for subselects and regular
                // columns a bit differently.
                
                // Make sure to cast any non-string things to nvarchar BEFORE they are concatennated
                if (! ($qpart->getAttribute()->getDataType() instanceof StringDataType)) {
                    $sql = 'CAST(' . $sql . ' AS nvarchar(max))';
                }
                $delim = $args[0] ?? $this->buildSqlGroupByListDelimiter($qpart);
                if ($qpart->getQuery()->isSubquery()) {
                    $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                }
                return "STUFF(CAST(( SELECT " . ($function_name == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . "[text()] = '{$this->escapeString($delim)}' + {$sql}";
            default:
                return parent::buildSqlGroupByExpression($qpart, $sql, $aggregator);
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelectGrouped()
     */
    protected function buildSqlSelectGrouped(QueryPart $qpart, $select_from = null, $select_column = null, $select_as = null, AggregatorInterface $aggregator = null)
    {
        $sql = parent::buildSqlSelectGrouped($qpart, $select_from, $select_column, $select_as, $aggregator);
        $aggregator = $aggregator ?? $qpart->getAggregator();
        $function_name = $aggregator->getFunction()->getValue();
        $args = $aggregator->getArguments();
        switch ($function_name) {
            // See buildSqlGroupByExpression() for details
            case AggregatorFunctionsDataType::LIST_DISTINCT:
            case AggregatorFunctionsDataType::LIST_ALL:
                // Only do this special treatment if it is not a subquery - otherwise the buildSqlSelectSubselect()
                // will add its own `FOR XML...` because that would not need an inner query since it is based on a 
                // subquery already
                $delim = $args[0] ?? $this->buildSqlGroupByListDelimiter($qpart);
                $delimLength = strlen($delim);
                if (! $qpart->getQuery()->isSubquery()) {
                    // If the aggregating via GROUP BY (i.e. not in a subquery), we need to build a subquery here
                    // connecting it to the main query by matching all the group-by-columns.
                    //
                    // SELECT
                    //  STUFF((
                    //    SELECT [text()] = ', ' + ttLIST.aggregatedCol
                    //    FROM targetTable ttLIST
                    //    WHERE ttLIST.groupByCol = tt.groupByCol)
                    //    FOR XML PATH(''),TYPE)
                    //  ,1,2,'') AS someAlias
                    //  FROM targetTable tt
                    //  GROUP BY groupByCol
                    //
                    // Build a subquery selecting the aggregated attribute, add required filters and render
                    // the SQL. Then replace the entire SELECT clause.
                    $subq = new self($this->getSelector());
                    $subq->setQueryId($this->getNextSubqueryId());
                    $subq->setMainObject($this->getMainObject());
                    $subqSelectPart = $subq->addAttribute($qpart->getAttribute()->getAliasWithRelationPath());
                    foreach ($this->getAggregations() as $aggrPart) {
                        if ($aggrPart->getAttribute()->isExactly($qpart->getAttribute())) {
                            continue;
                        }
                        $subq->addFilterWithCustomSql($aggrPart->getAttribute()->getAlias(), $this->buildSqlSelect($aggrPart, null, null, false, false, false), ComparatorDataType::EQUALS);
                    }
                    $subSql = $subq->buildSqlQuerySelect();
                    $subSqlFrom = 'FROM ' . $subq->buildSqlFrom();
                    $subSql = $subSqlFrom . StringDataType::substringAfter($subSql, $subSqlFrom);
                    // At this point, $sql is the result of buildSqlGroupByExpression for THIS query. Since we are going
                    // to use it in the subquery, we need to replace the table alias in the select clause - i.e. 
                    // "tt.aggregatedCol" -> "ttLIST.aggregatedCol" in the above example.
                    $thisSqlSelect = $this->buildSqlSelect($qpart, $select_from, $select_column, false, false, false);
                    $subSqlSelect = $subq->buildSqlSelect($subqSelectPart, null, null, false, false, false);
                    $sql = str_replace($thisSqlSelect, $subSqlSelect, $sql) . " $subSql FOR XML PATH(''), TYPE) AS VARCHAR(max)), 1, $delimLength, '')";
                }
                break;
        }
        return $sql;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelectSubselect()
     */
    protected function buildSqlSelectSubselect(\exface\Core\CommonLogic\QueryBuilder\QueryPart $qpart, $select_from = null)
    {
        $subselect = parent::buildSqlSelectSubselect($qpart, $select_from);
        
        if ($qpart->hasAggregator()) {
            $aggregator = $qpart->getAggregator();
            $function_name = $aggregator->getFunction()->getValue();
            switch ($function_name) {
                // See buildSqlGroupByExpression() for details
                case AggregatorFunctionsDataType::LIST_DISTINCT:
                case AggregatorFunctionsDataType::LIST_ALL:
                    $args = $aggregator->getArguments();
                    $delim = $args[0] ?? $this->buildSqlGroupByListDelimiter($qpart);
                    $delimLength = strlen($delim);
                    $subselect = substr($subselect, 0, -1) . " FOR XML PATH(''), TYPE) AS VARCHAR(max)), 1, $delimLength, ''))";
                    break;
            }
        }
        return $subselect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlQueryUpdate($sqlSet, $sqlWhere)
     */
    public function buildSqlQueryUpdate(string $sqlSet, string $sqlWhere) : string
    {
        $table_alias = $this->getShortAlias($this->getMainObject()->getAlias());
        return 'UPDATE ' . $table_alias  . $sqlSet . ' FROM ' . $this->buildSqlFrom(AbstractSqlBuilder::OPERATION_WRITE) . $sqlWhere;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlQueryDelete()
     */
    public function buildSqlQueryDelete(string $sqlWhere) : string
    {
        $table_alias = $this->getShortAlias($this->getMainObject()->getAlias());
        return 'DELETE ' . $table_alias . '  FROM ' . $this->buildSqlFrom(AbstractSqlBuilder::OPERATION_WRITE) . $sqlWhere;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::prepareInputValue()
     */
    protected function prepareInputValue($value, DataTypeInterface $data_type, array $dataAddressProps = [])
    {
        switch (true) {
            case $data_type instanceof StringDataType:
                $value = $data_type->parse($value);
                // JSON values are strings too, but their columns should be null even if the value is an
                // empty object or empty array (otherwise the cells would never be null)
                if (($data_type instanceof JsonDataType) && $data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = $value === null ? 'NULL' : "'" . $this->escapeString($value) . "'";
                }
                break;
            default:
                $value = parent::prepareInputValue($value, $data_type, $dataAddressProps);
        }
        return $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlAsForTables()
     */
    protected function buildSqlAsForTables(string $alias) : string
    {
        if ($this->getMainObject()->getDataAddressProperty('SQL_SELECT_WITH_NOLOCK') === true) {
            return ' (NOLOCK) AS ' . $alias;
        }
        return ' AS ' . $alias;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::create()
     */
    public function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        // SQL Server does not accept NULL as value for an IDENTITY column, so we remove 
        // UID columns here entirely if they
        // - are not required in the metamodel
        // - do not have non-empty values
        // - do not use the optimized UUID generator
        // - do not have a custom SQL generator
        if ($this->getMainObject()->hasUidAttribute()) {
            $uidAttr = $this->getMainObject()->getUidAttribute();
            $uidQpart = $this->getValue($uidAttr->getAlias());
            if ($uidQpart && $uidAttr->isRequired() === false && $uidQpart->hasValues() === false && ! $uidAttr->getDataAddressProperty(static::DAP_SQL_INSERT) && ! $uidAttr->getDataAddressProperty(static::DAP_SQL_INSERT_DATA_ADDRESS) && ! $uidAttr->getDataAddressProperty(static::DAP_SQL_INSERT_UUID_OPTIMIZED)) {
                $this->removeQueryPart($uidQpart);
            }
        }
        return parent::create($data_connection);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelectBinaryAsHEX()
     */
    protected function buildSqlSelectBinaryAsHEX(string $select_from) : string
    {
        return "LOWER(CONVERT(VARCHAR(34), {$select_from}, 1))";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelectSubselectJunctionWhere($qpart, $junctionAttribute, $select_from)
     */
    protected function buildSqlSelectSubselectJunctionWhere(QueryPart $qpart, MetaAttributeInterface $junctionAttribute, string $select_from) : string
    {
        // If aggregating, the UID is automatically MAXed (see buildSqlQuerySelect()), so we
        // need to MAX it here too because MS SQL cannot compare to SELECT aliases in WHEREs.
        // For example, in the following statement the WHERE of the subselect MUST be `MAX()`
        // in MS SQL because simply `oid` cannot be resolved.
        // ```
        //  SELECT 
        //      u.username, 
        //      MAX(u.oid) as oid, 
        //      (SELECT ... WHERE ... = MAX(u.oid)) AS subselect
        //  FROM exf_user u 
        //  GROUPY BY u.username
        // ```
        // TODO not sure, if something similar needs to be done if the junction attribute
        // is included in the SELECT query, but is explicitly aggregated
        if ($this->isAggregated() && $junctionAttribute->getObject()->hasUidAttribute() && $junctionAttribute->isExactly($junctionAttribute->getObject()->getUidAttribute()) && (! $this->getAttribute($junctionAttribute->getAliasWithRelationPath()) || ! $this->getAttribute($junctionAttribute->getAliasWithRelationPath())->getAggregator())) {
            return '(MAX(' . $select_from . $this->getAliasDelim() . $this->buildSqlDataAddress($junctionAttribute) . '))';
        } 
        return parent::buildSqlSelectSubselectJunctionWhere($qpart, $junctionAttribute, $select_from);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::escapeString()
     */
    protected function escapeString($string)
    {
        /* This did not work because it escaped `'` and `\` with backslashes.
         * It is also not clear if excaping \x0A (= \n) and \x0D (= \r) is needed.
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[\x00\x0A\x0D\x1A\x27\x5C]', '\\\0', $string);
        } else {
            return preg_replace('~[\x00\x0A\x0D\x1A\x27\x5C]~u', '\\\$0', $string);
        }*/
        
        
        if ($string === null || $string === ''){
            return '';
        }
        if (is_numeric($string)) return $string;
        
        // Remove invisible ASCII control chars like \x00 (NUL), etc.
        $toRemove = [
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        ];
        foreach ($toRemove as $regex) {
            $string = preg_replace($regex, '', $string );
        }
        // Escape single quotes with another single quote
        $string = str_replace("'", "''", $string );
        
        return $string;
    }
}