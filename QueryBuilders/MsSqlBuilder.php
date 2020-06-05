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

/**
 * A query builder for Microsoft SQL.
 *
 * ## Data source options
 * 
 * ### On object level
 * 
 * - **SQL_SELECT_WHERE** - custom where statement automatically appended to direct selects for this object (not if the object's table
 * is joined!). Usefull for generic tables, where different meta objects are stored and distinguished by specific keys in a
 * special column. The value of SQL_SELECT_WHERE should contain the [#~alias#] placeholder: e.g. [#~alias#].mycolumn = 'myvalue'.
 *
 * @author Andrej Kabachnik
 *        
 */
class MsSqlBuilder extends AbstractSqlBuilder
{
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
     * In MySQL the select query is pretty straight-forward: there is no need to create nested queries,
     * since MySQL natively supports selecting pages (LIMIT).
     * However, if aggregators (GROUP BY) are used, we still need
     * to distinguish between core and enrichment elements in order to join enrchichment stuff after all
     * the aggregating had been done.
     *
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlQuerySelect()
     */
    public function buildSqlQuerySelect()
    {
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
        if ($custom_where = $this->getMainObject()->getDataAddressProperty('SQL_SELECT_WHERE')) {
            $where = $this->appendCustomWhere($where, $custom_where);
        }
        $where = $where ? "\n WHERE " . $where : '';
        $having = $having ? "\n WHERE " . $having : '';
        
        $has_attributes_with_reverse_relations = count($this->getAttributesWithReverseRelations());
        
        // GROUP BY
        foreach ($this->getAggregations() as $qpart) {
            $group_by .= ', ' . $this->buildSqlGroupBy($qpart, ($has_attributes_with_reverse_relations ? 'EXFCOREQ' : null));
        }
        $group_by = $group_by ? ' GROUP BY ' . substr($group_by, 2) : '';
        
        // If there is a limit in the query, ensure there is an ORDER BY even if no sorters given.
        if (empty($this->getSorters()) === true && $this->getLimit() > 0 && $this->isAggregatedToSingleRow() === false) {
            if ($this->getMainObject()->hasUidAttribute()) {
                // If no order is specified, sort sort over the UID of the meta object
                $order_by .= ', ' . ($group_by ? 'EXFCOREQ' . $this->getAliasDelim() : '') . $this->getMainObject()->getAlias() . '.' . $this->getMainObject()->getUidAttribute()->getDataAddress() . ' DESC';
            } else {
                // If the object has no UID, sort over the first column in the query, which is not an SQL statement itself
                foreach ($this->getAttributes() as $qpart) {
                    if (! $this->checkForSqlStatement($qpart->getDataAddress())) {
                        $order_by .= ', ' . $qpart->getColumnKey() . ' DESC';
                        break;
                    }
                }
            }
        }
        
        // SELECT
        /*	@var $qpart \exface\Core\CommonLogic\QueryBuilder\QueryPartSelect */
        foreach ($this->getAttributes() as $qpart) {
            $qpartAttr = $qpart->getAttribute();
            $skipped = false;
            
            // First see, if the attribute has some kind of special data type (e.g. binary)
            if ($qpartAttr->getDataAddressProperty('SQL_DATA_TYPE') == 'binary') {
                $this->addBinaryColumn($qpart->getAlias());
            }
            // if the query has a GROUP BY, we need to put the UID-Attribute in the core select as well as in the enrichment select
            // otherwise the enrichment joins won't work!
            if ($group_by && $qpartAttr->getObject()->hasUidAttribute() && $qpartAttr->isExactly($qpartAttr->getObject()->getUidAttribute()) && ! $has_attributes_with_reverse_relations && ! $qpart->getAggregator()) {
                $selects[] = $this->buildSqlSelect($qpart, null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
                $enrichment_selects[] = $this->buildSqlSelect($qpart, 'EXFCOREQ', $qpartAttr->getObject()->getUidAttributeAlias());
                $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
            } elseif (! $group_by || $qpart->getAggregator() || $this->isAggregatedBy($qpart)) {
                // If we are not aggregating or the attribute has a group function, add it regulary
                $selects[] = $this->buildSqlSelect($qpart);
                $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
                // If aggregating, also add attributes, that are aggregated over or can be assumed unique due to set filters
            } elseif ($this->isObjectGroupSafe($qpartAttr->getObject()) === true) {
                $rels = $qpart->getUsedRelations();
                $first_rel = false;
                if (! empty($rels)) {
                    $first_rel = reset($rels);
                    $first_rel_qpart = $this->addAttribute($first_rel->getAliasWithModifier());
                    // IDEA this does not support relations based on custom sql. Perhaps this needs to change
                    $selects[] = $this->buildSqlSelect($first_rel_qpart, null, null, $first_rel_qpart->getAttribute()->getDataAddress(), ($group_by ? new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX) : null));
                }
                $enrichment_selects[] = $this->buildSqlSelect($qpart);
                $enrichment_joins = array_merge($enrichment_joins, $this->buildSqlJoins($qpart, 'exfcoreq'));
                $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
                // If aggregating, also add attributes, that belong directly to objects, we are aggregating over (they can be assumed unique too, since their object is unique per row)
            } elseif ($group_by && $this->getAggregation($qpartAttr->getRelationPath()->toString())) {
                $selects[] = $this->buildSqlSelect($qpart, null, null, null, new Aggregator($this->getWorkbench(), AggregatorFunctionsDataType::MAX));
                $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                $group_safe_attribute_aliases[] = $qpartAttr->getAliasWithRelationPath();
            } else {
                $skipped = true;
                $select_comment .= '-- ' . $qpart->getAlias() . ' is ignored because it is not group-safe or ambiguously defined' . "\n";
            }
            
            // If we have attributes, that need reverse relations, we must move the group by to the outer (enrichment) query, because
            // the subselects of the subqueries will reference UIDs of the core rows, thus making grouping in the core query impossible
            if (! $skipped && $group_by && $has_attributes_with_reverse_relations) {
                $enrichment_selects[] = $this->buildSqlSelect($qpart, 'EXFCOREQ', $this->getShortAlias($qpart->getColumnKey()));
            }
        }
        // Core SELECT
        $select = implode(', ', array_unique(array_filter($selects)));
        $select_comment = $select_comment ? "\n" . $select_comment : '';
        
        // Enrichment SELECT
        $enrichment_select = implode(', ', array_unique(array_filter($enrichment_selects)));
        if (! ($group_by && $has_attributes_with_reverse_relations)) {
            $enrichment_select = 'EXFCOREQ' . $this->getAliasDelim() . '*' . ($enrichment_select ? ', ' . $enrichment_select : '');
        }
        
        // FROM
        $from = $this->buildSqlFrom();
        
        // JOINs
        $join = implode(' ', $joins);
        $enrichment_join = implode(' ', $enrichment_joins);
        
        // ORDER BY
        foreach ($this->getSorters() as $qpart) {
            // A sorter can only be used, if there is no GROUP BY, or the sorted attribute has unique values within the group
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
        
        if (($group_by && ($where || $has_attributes_with_reverse_relations)) || $this->getSelectDistinct()) {
            if (count($this->getAttributesWithReverseRelations()) > 0) {
                $query = "\n SELECT " . $distinct . $enrichment_select . $select_comment . " FROM (SELECT " . $select . " FROM " . $from . $join . $where . ") EXFCOREQ " . $enrichment_join . $group_by . $having . $order_by . $limit;
            } else {
                $query = "\n SELECT " . $distinct . $enrichment_select . $select_comment . " FROM (SELECT " . $select . " FROM " . $from . $join . $where . $group_by . $having . ") EXFCOREQ " . $enrichment_join . $order_by . $limit;
            }
        } else {
            $query = "\n SELECT " . $distinct . $select . $select_comment . " FROM " . $from . $join . $where . $group_by . $having . $order_by . $limit;
        }
        
        return $query;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlQueryTotals()
     */
    public function buildSqlQueryTotals()
    {
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
        if ($custom_where = $this->getMainObject()->getDataAddressProperty('SQL_SELECT_WHERE')) {
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
        return $totals_query;
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
            $shortAlias = $this->getShortAlias($qpart->getColumnKey());
            $type = $qpart->getDataType();
            switch (true) {
                case $type instanceof DateTimeDataType:
                case $type instanceof DateDataType:
                    foreach ($rows as $nr => $row) {
                        $val = $row[$shortAlias];
                        if ($val instanceof \DateTime) {
                            $val = $type::formatDateNormalized($val);
                        }
                        $rows[$nr][$qpart->getColumnKey()] = $val;
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
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());                
                return "STUFF(CAST(( SELECT " . ($function_name == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . "[text()] = " . ($args[0] ? $args[0] : "', '") . " + {$sql}";                
            default:
                return parent::buildSqlGroupByExpression($qpart, $sql, $aggregator);
        }
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
            if ($function_name === AggregatorFunctionsDataType::LIST_DISTINCT) {
                $subselect = substr($subselect, 0, -1) .  "FOR XML PATH(''), TYPE) AS VARCHAR(1000)), 1, 2, ''))";
            }
        }
        return $subselect;
    }
}
?>