<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * A query builder for Oracle SQL. 
 * 
 * Supported dialect tags in multi-dialect statements (in order of priority): `@PL/SQL:`, `@Oracle:`, `@OTHER:`.
 * 
 * See `AbstractSqlBuilder` for available data address options!
 * 
 * @see AbstractSqlBuilder
 *
 * @author Andrej Kabachnik
 *        
 */
class OracleSqlBuilder extends AbstractSqlBuilder
{
    const MAX_BUILD_RUNS = 5;
    
    /**
     * 
     * @param QueryBuilderSelectorInterface $selector
     */
    public function __construct(QueryBuilderSelectorInterface $selector)
    {
        parent::__construct($selector);
        $this->setReservedWords(['ACCESS', 'ADD', 'ALL', 'ALTER', 'AND', 'ANY', 'AS', 'ASC', 'AUDIT', 'BETWEEN', 'BY', 'CHAR', 'CHECK', 'CLUSTER', 'COLUMN', 'COLUMN_VALUE', 'COMMENT', 'COMPRESS', 'CONNECT', 'CREATE', 'CURRENT', 'DATE', 'DECIMAL', 'DEFAULT', 'DELETE', 'DESC', 'DISTINCT', 'DROP', 'ELSE', 'EXCLUSIVE', 'EXISTS', 'FILE', 'FLOAT', 'FOR', 'FROM', 'GRANT', 'GROUP', 'HAVING', 'IDENTIFIED', 'IMMEDIATE', 'IN', 'INCREMENT', 'INDEX', 'INITIAL', 'INSERT', 'INTEGER', 'INTERSECT', 'INTO', 'IS', 'LEVEL', 'LIKE', 'LOCK', 'LONG', 'MAXEXTENTS', 'MINUS', 'MLSLABEL', 'MODE', 'MODIFY', 'NESTED_TABLE_ID (See Note 1 at the end of this list)', 'NOAUDIT', 'NOCOMPRESS', 'NOT', 'NOWAIT', 'NULL', 'NUMBER', 'OF', 'OFFLINE', 'ON', 'ONLINE', 'OPTION', 'OR', 'ORDER', 'PCTFREE', 'PRIOR', 'PUBLIC', 'RAW', 'RENAME', 'RESOURCE', 'REVOKE', 'ROW', 'ROWID', 'ROWNUM', 'ROWS', 'SELECT', 'SESSION', 'SET', 'SHARE', 'SIZE', 'SMALLINT', 'START', 'SUCCESSFUL', 'SYNONYM', 'SYSDATE', 'TABLE', 'THEN', 'TO', 'TRIGGER', 'UID', 'UNION', 'UNIQUE', 'UPDATE', 'USER', 'VALIDATE', 'VALUES', 'VARCHAR', 'VARCHAR2', 'VIEW', 'WHENEVER', 'WHERE', 'WITH']);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge(['PL/SQL', 'Oracle'], parent::getSqlDialects());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getShortAliasMaxLength()
     */
    protected function getShortAliasMaxLength() : int
    {
        return 28;
    }
    
    public function buildSqlQuerySelect(int $buildRun = 0)
    {
        $this->setDirty(false);
        
        $where = '';
        $having = '';
        $group_by = '';
        $order_by = '';
        
        if ($this->getLimit() > 0) {
            // if the query is limited (pagination), run a core query for the filtering and pagination
            // and perform as many joins as possible afterwords only for the result of the core query
            
            $core_joins = array();
            $core_relations = array();
            $enrichment_parts = $this->getAttributes();
            $enrichment_select = '';
            $enrichment_joins = array();
            $enrichment_join = '';
            $enrichment_order_by = '';
            $core_selects = array();
            $core_select = '';
            $select_comment = '';
            
            // Build core query: join tables needed for filtering, grouping and sorting and select desired attributes from these tables
            // determine the needed JOINS
            // aggregations -> GROUP BY
            foreach ($this->getAggregations() as $qpart) {
                foreach ($qpart->getUsedRelations() as $rel_alias => $rel) {
                    $core_relations[] = $rel_alias;
                }
                $core_joins = array_merge($core_joins, $this->buildSqlJoins($qpart));
                $group_by .= ', ' . $this->buildSqlGroupBy($qpart);
            }
            
            // filters -> WHERE
            $where = $this->buildSqlWhere($this->getFilters());
            $having = $this->buildSqlHaving($this->getFilters());
            $core_joins = array_merge($core_joins, $this->buildSqlJoins($this->getFilters()));
            foreach ($this->getFilters()->getUsedRelations() as $rel_alias => $rel) {
                $core_relations[] = $rel_alias;
            }
            
            // Object data source property SQL_SELECT_WHERE -> WHERE
            if ($custom_where = $this->getMainObject()->getDataAddressProperty(static::DAP_SQL_SELECT_WHERE)) {
                $where = $this->appendCustomWhere($where, $custom_where);
            }
            
            // sorters -> ORDER BY
            foreach ($this->getSorters() as $qpart) {
                if ($group_by) {
                   // TODO if we sort over an attribute that is not groupable, there will be an SQL error.
                   // Without the sorting, there will be no error. So it makes sense to prevent this
                   // error somehow...
                } 
                
                foreach (array_keys($qpart->getUsedRelations()) as $rel_alias) {
                    $core_relations[] = $rel_alias;
                }
                $core_selects[$qpart->getAlias()] = $this->buildSqlSelect($qpart);
                $core_joins = array_merge($core_joins, $this->buildSqlJoins($qpart));
                $order_by .= ', ' . $this->buildSqlOrderBy($qpart);
                
                $enrichment_order_by .= ', ' . $this->buildSqlOrderBy($qpart, 'EXFCOREQ');
            }
            
            array_unique($core_relations);
            
            // separate core SELECTs from enrichment SELECTs
            foreach ($enrichment_parts as $nr => $qpart) {
                $qpartAttr = $qpart->getAttribute();
                if (in_array($qpartAttr->getRelationPath()->toString(), $core_relations) || $qpartAttr->getRelationPath()->isEmpty()) {
                    // Workaround to ensure, the UID is always in the query!
                    // If we are grouping, we will not select any fields, that could be ambigous, thus
                    // we can use MAX(UID), since all other values are the same within the group.
                    if ($group_by && $qpart->getAttribute()->isExactly($qpart->getAttribute()->getObject()->getUidAttribute()) && ! $qpart->getAggregator()) {
                        $qpart->setAggregator(new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
                    }
                    // If we are grouping, we can only select valid GROUP BY expressions from the core table.
                    // These are either the ones with an aggregate function or thouse we are grouping by
                    if ($group_by && ! $qpart->getAggregator() && ! $this->isAggregatedBy($qpart)) {
                        // IDEA at this point, we could use the default aggregate function of the attributes. However it is probably a good
                        // idea to set the default aggregator somewhere in the qpart code, not in the query builders. If we set the aggregator
                        // to the default, this place will pass without a problem.
                        $select_comment .= $this->buildSqlComment($qpart->getAlias() . ' is ignored because it is not group-safe or ambiguously defined') . "\n";
                        continue;
                    }
                    // also skip selects based on custom sql substatements if not being grouped over
                    // they should be done after pagination as they are potentially very time consuming
                    if ($this->isSqlStatement($this->buildSqlDataAddress($qpartAttr)) && (! $group_by || ! $qpart->getAggregator())) {
                        continue;
                    } elseif ($qpart->getUsedRelations(RelationTypeDataType::REVERSE) && ! $this->getAggregation($qpart->getAlias()) && $this->isQpartRelatedToAggregator($qpart)) {
                        // Also skip selects with reverse relations that can be joined later in the enrichment.                      
                        // Selecting them in the core query would only slow it down. The filtering is done explicitly in build_sql_where_condition()
                        // The trick is, that we need to check, if the reverse relation can be joined onto something coming out of the GROUP BY:
                        // this is done via $this->isQpartRelatedToAggregator($qpart).
                        continue;
                    } else {
                        // Add all remainig attributes of the core objects to the core query and select them 1-to-1 in the enrichment query
                        if ($group_by){
                            // When grouping, force the select expression to be compatible with GROUP BY!
                            $core_selects[$qpart->getAlias()] = $this->buildSqlSelect($qpart, null, null, null, null, true);
                        } else {
                            $core_selects[$qpart->getAlias()] = $this->buildSqlSelect($qpart);
                        }
                        $enrichment_select .= ', ' . $this->buildSqlSelect($qpart, 'EXFCOREQ', '"' . $this->getShortAlias($qpart->getColumnKey()) . '"', null, false);
                    }
                    unset($enrichment_parts[$nr]);
                }
            }
            
            foreach ($enrichment_parts as $qpart) {
                // If we are grouping, we can only join attributes of object instances, that are unique in the query
                // This is the case, if we filtered for exactly one instance of an object before, or if we aggregate
                // over this object or a relation to it.
                // TODO actually we need to make sure, the filter returns exactly one object, which probably means,
                // that the filter should be layed over an attribute, which uniquely identifies the object (e.g.
                // its UID column).
                if ($group_by && $this->isObjectGroupSafe($qpart->getAttribute()->getObject()) === false) {
                    if (! $this->isQpartRelatedToAggregator($qpart)) {
                        $select_comment .= $this->buildSqlComment($qpart->getAlias() . ' is ignored because it is not group-safe or ambiguously defined') . "\n";
                        continue;
                    }
                }
                // Check if we need some UIDs from the core tables to join the enrichments afterwards
                if ($first_rel = $qpart->getFirstRelation()) {
                    if ($first_rel->isForwardRelation()) {
                        $first_rel_qpart = $this->addAttribute($first_rel->getAlias())->excludeFromResult(true);
                        // IDEA this does not support relations based on custom sql. Perhaps this needs to change
                        $core_selects[$this->buildSqlDataAddress($first_rel_qpart->getAttribute())] = $this->buildSqlSelect($first_rel_qpart, null, null, $this->buildSqlDataAddress($first_rel_qpart->getAttribute()), ($group_by ? new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX) : null));
                    }
                }
                
                // build the enrichment select.
                if ($qpart->getFirstRelation() && $qpart->getFirstRelation()->isReverseRelation()) {
                    // If the first relation needed for the select is a reverse one, make sure, the subselect will reference the core query directly
                    $enrichment_select .= ', ' . $this->buildSqlSelect($qpart, 'EXFCOREQ');
                } elseif ($group_by && ! $qpart->getFirstRelation() && ! $qpart->getAggregator()) {
                    // If in a GROUP BY the attribute belongs to the main object and does not have an aggregate function, skip it - oracle cannot deal with it
                    $select_comment .= $this->buildSqlComment($qpart->getAlias() . ' is ignored because the attribute belongs to the main object and does not have an aggregate function - oracle does not support this!') . "\n";
                    continue;
                } else {
                    // Otherwise the selects can rely on the joins
                    $enrichment_select .= ', ' . $this->buildSqlSelect($qpart);
                }
                $enrichment_joins = array_merge($enrichment_joins, $this->buildSqlJoins($qpart, 'exfcoreq'));
            }
            
            $core_select = str_replace(',,', ',', implode(',', array_filter($core_selects)));
            $core_from = $this->buildSqlFrom();
            $core_join = implode(' ', $core_joins);
            $where = $where ? "\n WHERE " . $where : '';
            $having = $having ? "\n HAVING " . $having : '';
            $group_by = $group_by ? ' GROUP BY ' . substr($group_by, 2) : '';
            $order_by = $order_by ? ' ORDER BY ' . substr($order_by, 2) : '';
            
            $enrichment_select = $enrichment_select ? str_replace(',,', ',', $enrichment_select) : '';
            $enrichment_select = substr($enrichment_select, 2);
            $enrichment_join = implode(' ', $enrichment_joins);
            $enrichment_order_by = $enrichment_order_by ? ' ORDER BY ' . substr($enrichment_order_by, 2) : '';
            $distinct = $this->getSelectDistinct() ? 'DISTINCT ' : '';
            $select_comment = $select_comment ? "\n" . $select_comment : '';
            
            // build the query itself
            $core_query = "
								SELECT " . $distinct . $core_select . $select_comment . " FROM " . $core_from . $core_join . $where . $group_by . $having . $order_by;
            
            $limitRows = $this->getLimit();
            // Increase limit by one to check if there are more rows (see AbstractSqlBuilder::read())
            if ($this->isSubquery() === false) {
                $limitRows += 1;
            }
            $query = "\n SELECT " . $distinct . $enrichment_select . $select_comment . " FROM
				(SELECT *
					FROM
						(SELECT exftbl.*, ROWNUM EXFRN
							FROM (" . $core_query . ") exftbl
		         			WHERE ROWNUM <= " . ($limitRows + $this->getOffset()) . "
						)
         			WHERE EXFRN > " . $this->getOffset() . "
         		) exfcoreq " . $enrichment_join . $enrichment_order_by;
        } else {
            // if there is no limit (no pagination), we just make a simple query
            $select = '';
            $joins = array();
            $join = '';
            $enrichment_select = '';
            $enrichment_joins = array();
            $enrichment_join = '';
            
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
            foreach ($this->getAttributes() as $qpart) {
                $qpartAttr = $qpart->getAttribute();
                // if the query has a GROUP BY, we need to put the UID-Attribute in the core select as well as in the enrichment select
                // otherwise the enrichment joins won't work!
                if ($group_by && $qpartAttr->getObject()->hasUidAttribute() && $qpartAttr->isExactly($qpartAttr->getObject()->getUidAttribute())) {
                    $select .= ', ' . $this->buildSqlSelect($qpart, null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
                    $enrichment_select .= ', ' . $this->buildSqlSelect($qpart, 'EXFCOREQ');
                } // if we are aggregating, leave only attributes, that have an aggregate function,
                  // and ones, that are aggregated over or can be assumed unique due to set filters
                elseif (! $group_by || $qpart->getAggregator() || $this->isAggregatedBy($qpart)) {
                    $select .= ', ' . $this->buildSqlSelect($qpart);
                    $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                } elseif ($this->isObjectGroupSafe($qpartAttr->getObject(), null, null, $qpartAttr->getRelationPath()) === true) {
                    $rels = $qpart->getUsedRelations();
                    $first_rel = false;
                    if (! empty($rels)) {
                        $first_rel = reset($rels);
                        $first_rel_qpart = $this->addAttribute($first_rel->getAliasWithModifier());
                        // IDEA this does not support relations based on custom sql. Perhaps this needs to change
                        $select .= ', ' . $this->buildSqlSelect($first_rel_qpart, null, null, $this->buildSqlDataAddress($first_rel_qpart->getAttribute()), ($group_by ? new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX) : null));
                    }
                    $enrichment_select .= ', ' . $this->buildSqlSelect($qpart);
                    $enrichment_joins = array_merge($enrichment_joins, $this->buildSqlJoins($qpart, 'exfcoreq'));
                    $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                } else {
                    $select_comment .= $this->buildSqlComment($qpart->getAlias() . ' is ignored because it is not group-safe or ambiguously defined') . "\n";
                }
            }
            $select = substr($select, 2);
            $select_comment = $select_comment ? "\n" . $select_comment : '';
            $enrichment_select = 'EXFCOREQ' . $this->getAliasDelim() . '*' . ($enrichment_select ? ', ' . substr($enrichment_select, 2) : '');
            // FROM
            $from = $this->buildSqlFrom();
            // JOINs
            $join = implode(' ', $joins);
            $enrichment_join = implode(' ', $enrichment_joins);
            // ORDER BY
            foreach ($this->getSorters() as $qpart) {
                $order_by .= ', ' . $this->buildSqlOrderBy($qpart);
            }
            $order_by = $order_by ? ' ORDER BY ' . substr($order_by, 2) : '';
            
            $distinct = $this->getSelectDistinct() ? 'DISTINCT ' : '';
            
            if (($group_by && $where) || $this->getSelectDistinct()) {
                $query = "\n SELECT " . $distinct . $enrichment_select . $select_comment . " FROM (SELECT " . $select . " FROM " . $from . $join . $where . $group_by . $having . $order_by . ") EXFCOREQ " . $enrichment_join . $order_by;
            } else {
                $query = "\n SELECT " . $distinct . $select . $select_comment . " FROM " . $from . $join . $where . $group_by . $having . $order_by;
            }
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
                $totals_selects[] = $this->buildSqlSelect($qpart, 'EXFCOREQ', '"' . $this->getShortAlias($qpart->getColumnKey()) . '"', null, $qpart->getTotalAggregator());
                $totals_core_selects[] = $this->buildSqlSelect($qpart);
                $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($qpart));
            }
        }
        // Make sure all JOINs required for data address placeholders are there
        foreach ($this->getAttributes() as $qpart) {
            if ($qpart->isUsedInPlaceholders() === true) {
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
            $totals_core_select = '1';
        }
        
        if ($totals_core_select) {
            $totals_query = "\n SELECT COUNT(*) AS EXFCNT " . $totals_select . " FROM (SELECT " . $totals_core_select . ' FROM ' . $totals_from . $totals_join . $totals_where . $totals_group_by . $totals_having . ") EXFCOREQ";
        } else {
            $totals_query = "\n SELECT COUNT(*) AS EXFCNT FROM " . $totals_from . $totals_join . $totals_where . $totals_group_by . $totals_having;
        }
        
        // See if changes to the query occur while the query was built (e.g. query parts are
        // added for placeholders, etc.) and rerun the query builder if required.
        // However, do not run it more than X times to avoid infinite recursion.
        if ($this->isDirty() && $buildRun < self::MAX_BUILD_RUNS) {
            return $this->buildSqlQueryTotals($buildRun+1);
        }
        
        return $totals_query;
    }

    protected function buildSqlSelectNullCheckFunctionName()
    {
        return 'NVL';
    }

    /**
     * In oracle it seems, that the alias of the sort column should be in double quotes, whereas in other
     * dialects (at least in MySQL), the quotes prevent the sorting.
     * FIXME Does not work with custom order by of attributes of related objects - only if sorting over a direct attribute of the main object. Autogenerated order by works fine.
     *
     * @param \exface\Core\CommonLogic\QueryBuilder\QueryPartSorter $qpart            
     * @return string
     */
    protected function buildSqlOrderBy(QueryPartSorter $qpart, $select_from = null) : string
    {
        if ($qpart->getDataAddressProperty(static::DAP_SQL_ORDER_BY)) {
            $output = ($select_from ? $select_from : $this->getShortAlias($qpart->getAttribute()->getRelationPath()->toString())) . $this->getAliasDelim() . $qpart->getDataAddressProperty(static::DAP_SQL_ORDER_BY);
        } else {
            $output = '"' . $this->getShortAlias($qpart->getColumnKey()) . '"';
            
            // Make sure, NULLs are treated as 0 in numeric columns. Otherwise
            // they will be put at the beginning or the end of the result making
            // sorting loose it's value.
            if ($qpart->getAttribute()->getDataType() instanceof NumberDataType){
                $output = 'NVL(' . $output . ',0)';
            }
        }
        $output .= ' ' . $qpart->getOrder();
        return $output;
    }
    
    protected function buildSqlGroupByExpression(QueryPartAttribute $qpart, $sql, AggregatorInterface $aggregator){
        $output = '';
        $function_arguments = $aggregator->getArguments();
        
        switch ($aggregator->getFunction()->getValue()) {
            case AggregatorFunctionsDataType::LIST_ALL:
                $output = "ListAgg(" . $sql . ", " . ($function_arguments[0] ?? $this->buildSqlGroupByListDelimiter($qpart)) . ") WITHIN GROUP (order by " . $sql . ")";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            case AggregatorFunctionsDataType::LIST_DISTINCT:
                $output = "ListAggDistinct(" . $sql . ")";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            default:
                $output = parent::buildSqlGroupByExpression($qpart, $sql, $aggregator);
        }
        
        return $output;
    }

    protected function getPrimaryKeySequence()
    {
        // If there is no primary key sequence defined, try adding '_SEQ' to the table name. This seems to be a wide spread approach.
        // If this does not work, we will get an SQL error
        if (! $sequence = $this->getMainObject()->getDataAddressProperty('PKEY_SEQUENCE')) {
            $sequence = $this->buildSqlDataAddress($this->getMainObject()) . '_SEQ';
        }
        return $sequence;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::prepareInputValue()
     */
    protected function prepareInputValue($value, DataTypeInterface $data_type, array $dataAddressProps = [], bool $parse = true)
    {
        switch (true) {
            case $data_type instanceof DateTimeDataType:
            case $data_type instanceof TimestampDataType:
                if ($data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    if (null !== $tz = $this->getTimeZoneInSQL($data_type::getTimeZoneDefault($this->getWorkbench()), $this->getTimeZone(), $dataAddressProps[static::DAP_SQL_TIME_ZONE] ?? null)) {
                        $value = $data_type::convertTimeZone($value, $data_type::getTimeZoneDefault($this->getWorkbench()), $tz);
                    }
                    $value = "TO_DATE('" . $this->escapeString($value) . "', 'yyyy-mm-dd hh24:mi:ss')";
                }
                break;
            case $data_type instanceof DateDataType:
                if ($data_type::isValueEmpty($value) === true) {
                    $value = 'NULL';
                } else {
                    $value = "TO_DATE('" . $this->escapeString($value) . "', 'yyyy-mm-dd')";
                }
                break;
            default: 
                $value = parent::prepareInputValue($value, $data_type, $dataAddressProps, $parse);
        }
        return $value;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::escapeString()
     */
    protected function escapeString($string)
    {
        return str_replace("'", "''", $string);
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::create()
     */
    function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        if (! $this->isWritable()) {
            return new DataQueryResultData([], 0);
        }
        
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
            $qpartAddress = $this->buildSqlDataAddress($qpart);
            if (! $qpart->getDataAddressProperty(static::DAP_SQL_INSERT) && (! $qpartAddress || $this->isSqlStatement($qpartAddress))) {
                continue;
            }
            // Save the query part for later processing if it is the object's UID
            if ($attr->isUidForObject()) {
                $uid_qpart = $qpart;
            }
            $column = $qpart->getDataAddressProperty(static::DAP_SQL_INSERT_DATA_ADDRESS) ? $qpart->getDataAddressProperty(static::DAP_SQL_INSERT_DATA_ADDRESS) : $qpartAddress;
            $columns[$column] = $column;
            $custom_insert_sql = $qpart->getDataAddressProperty(static::DAP_SQL_INSERT);
            foreach ($qpart->getValues() as $row => $value) {
                $value = $this->prepareInputValue($value, $attr->getDataType(), $attr->getDataAddressProperties()->toArray());
                if ($custom_insert_sql) {
                    // If there is a custom insert SQL for the attribute, use it
                    $values[$row][$column] = str_replace(array(
                        '[#~alias#]',
                        '[#~value#]'
                    ), array(
                        $this->getMainObject()->getAlias(),
                        $value
                    ), $custom_insert_sql);
                } else {
                    $values[$row][$qpartAddress] = $value;
                }
            }
        }
        
        if (is_null($uid_qpart)) {
            // If there is no UID column, but the UID attribute has a custom insert statement, add it at this point manually
            // This is important because the UID will mostly not be marked as a mandatory attribute in order to preserve the
            // possibility of mixed creates and updates among multiple rows. But an empty non-required attribute will never
            // show up as a value here. Still that value is required!
            if ($uid_generator = $this->getMainObject()->getUidAttribute()->getDataAddressProperty(static::DAP_SQL_INSERT)) {
                $last_uid_sql_var = '@last_uid';
                $columns[] = $this->buildSqlDataAddress($this->getMainObject()->getUidAttribute());
                foreach ($values as $nr => $row) {
                    $values[$nr][] = $last_uid_sql_var . ' := ' . $uid_generator;
                }
            } else {
                $columns[] = $this->buildSqlDataAddress($this->getMainObject()->getUidAttribute());
                foreach ($values as $nr => $row) {
                    $values[$nr][$this->buildSqlDataAddress($this->getMainObject()->getUidAttribute())] = $this->getPrimaryKeySequence() . $this->getAliasDelim() . 'NEXTVAL';
                }
            }
        }
        
        foreach ($values as $nr => $row) {
            foreach ($row as $val) {
                $values[$nr] = implode(',', $row);
            }
        }
        
        $insertedCounter = 0;
        $insertedIds = [];
        $uidAlias = $this->getMainObject()->getUidAttribute()->getAlias();
        if (count($values) > 1) {
            foreach ($values as $nr => $vals) {
                $sql = 'INSERT INTO ' . $this->buildSqlDataAddress($this->getMainObject(), static::OPERATION_WRITE) . ' (' . implode(', ', $columns) . ') VALUES (' . $vals . ')' . "\n";
                $query = $data_connection->runSql($sql);
                
                // Now get the primary key of the last insert.
                if ($last_uid_sql_var) {
                    // If the primary key was a custom generated one, it was saved to the corresponding SQL variable.
                    // Fetch it from the data base
                    $last_id = reset($data_connection->runSql('SELECT CONCAT(\'0x\', LOWER(HEX(' . $last_uid_sql_var . ')))')->getResultArray()[0]);
                } else {
                    // If the primary key was autogenerated, fetch it via built-in function
                    $last_id = $query->getLastInsertId();
                }
                
                $affected_rows = $query->countAffectedRows();
                // TODO How to get multipla inserted ids???
                if ($affected_rows > 0) {
                    $insertedCounter += $affected_rows;
                    $insertedIds[] = [$uidAlias => $last_id];
                }
            }
        } else {
            $sql = 'INSERT INTO ' . $this->buildSqlDataAddress($this->getMainObject(), static::OPERATION_WRITE) . ' (' . implode(', ', $columns) . ') VALUES (' . implode('), (', $values) . ')';
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
                $insertedCounter += $affected_rows;
                $insertedIds[] = [$uidAlias => $last_id];
            }
        }
        
        return new DataQueryResultData($insertedIds, $insertedCounter);
    }
    
    protected function escapeColumnName(string $name) : string
    {
        return '"' . $name . '"';
    }
}
?>