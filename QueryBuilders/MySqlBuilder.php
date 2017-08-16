<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\Model\RelationPath;

/**
 * A query builder for MySQL.
 *
 * Data address properties for objects:
 * - SQL_SELECT_WHERE - custom where statement automatically appended to direct selects for this object (not if the object's table
 * is joined!). Usefull for generic tables, where different meta objects are stored and distinguished by specific keys in a
 * special column. The value of SQL_SELECT_WHERE should contain the [#alias#] placeholder: e.g. [#alias#].mycolumn = 'myvalue'.
 *
 *
 * @author Andrej Kabachnik
 *        
 */
class MySqlBuilder extends AbstractSqlBuilder
{

    // CONFIG
    protected $short_alias_max_length = 64;

    // maximum length of SELECT AS aliases
    
    /**
     * In MySQL the select query is pretty straight-forward: there is no need to create nested queries,
     * since MySQL natively supports selecting pages (LIMIT).
     * However, if aggregators (GROUP BY) are used, we still need
     * to distinguish between core and enrichment elements in order to join enrchichment stuff after all
     * the aggregating had been done.
     *
     * @see \exface\DataSources\QueryBuilders\sql_abstractSQL::buildSqlQuerySelect()
     */
    public function buildSqlQuerySelect()
    {
        $filter_object_ids = array();
        $where = '';
        $having = '';
        $group_by = '';
        $group_safe_attribute_aliases = array();
        $order_by = '';
        $selects = array();
        $select = '';
        $joins = array();
        $join = '';
        $enrichment_select = '';
        $enrichment_joins = array();
        $enrichment_join = '';
        $limit = '';
        
        // WHERE & HAVING
        $where = $this->buildSqlWhere($this->getFilters());
        $having = $this->buildSqlHaving($this->getFilters());
        $joins = $this->buildSqlJoins($this->getFilters());
        $filter_object_ids = $this->getFilters()->getObjectIdsSafeForAggregation();
        
        // Object data source property SQL_SELECT_WHERE -> WHERE
        if ($custom_where = $this->getMainObject()->getDataAddressProperty('SQL_SELECT_WHERE')) {
            $where = $this->appendCustomWhere($where, $custom_where);
        }
        $where = $where ? "\n WHERE " . $where : '';
        $having = $having ? "\n HAVING " . $having : '';
        
        // GROUP BY
        $group_uid_alias = '';
        foreach ($this->getAggregations() as $qpart) {
            $group_by .= ', ' . $this->buildSqlGroupBy($qpart);
            if (! $group_uid_alias) {
                if ($rel_path = $qpart->getAttribute()->getRelationPath()->toString()) {
                    $group_uid_alias = RelationPath::relationPathAdd($rel_path, $this->getMainObject()->getRelatedObject($rel_path)->getUidAlias());
                }
            }
        }
        $group_by = $group_by ? ' GROUP BY ' . substr($group_by, 2) : '';
        if ($group_uid_alias) {
            // $this->addAttribute($group_uid_alias);
        }
        
        // SELECT
        /* @var $qpart \exface\Core\CommonLogic\QueryBuilder\QueryPartSelect */
        foreach ($this->getAttributes() as $qpart) {
            // First see, if the attribute has some kind of special data type (e.g. binary)
            if ($qpart->getAttribute()->getDataAddressProperty('SQL_DATA_TYPE') == 'binary') {
                $this->addBinaryColumn($qpart->getAlias());
            }
            
            if ($group_by && $qpart->getAttribute()->getAlias() === $qpart->getAttribute()->getObject()->getUidAlias() && ! $qpart->getAggregateFunction()) {
                // If the query has a GROUP BY, we need to put the UID-Attribute in the core select as well as in the enrichment select
                // otherwise the enrichment joins won't work! Be carefull to apply this rule only to the plain UID column, not to columns
                // using the UID with aggregate functions
                $selects[] = $this->buildSqlSelect($qpart, null, null, null, 'MAX');
                $enrichment_select .= ', ' . $this->buildSqlSelect($qpart, 'EXFCOREQ', $this->getShortAlias($qpart->getAlias()));
            } elseif (! $group_by || $qpart->getAggregateFunction() || $this->getAggregation($qpart->getAlias())) {
                // If we are not aggregating or the attribute has a group function, add it regulary
                $selects[] = $this->buildSqlSelect($qpart);
                $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                $group_safe_attribute_aliases[] = $qpart->getAttribute()->getAliasWithRelationPath();
            } elseif (in_array($qpart->getAttribute()->getObject()->getId(), $filter_object_ids) !== false) {
                // If aggregating, also add attributes, that are aggregated over or can be assumed unique due to set filters
                $rels = $qpart->getUsedRelations();
                $first_rel = false;
                if (! empty($rels)) {
                    $first_rel = reset($rels);
                    $first_rel_qpart = $this->addAttribute($first_rel->getAlias());
                    // IDEA this does not support relations based on custom sql. Perhaps this needs to change
                    $selects[] = $this->buildSqlSelect($first_rel_qpart, null, null, $first_rel_qpart->getAttribute()->getDataAddress(), ($group_by ? 'MAX' : null));
                }
                $enrichment_select .= ', ' . $this->buildSqlSelect($qpart);
                $enrichment_joins = array_merge($enrichment_joins, $this->buildSqlJoins($qpart, 'exfcoreq'));
                $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                $group_safe_attribute_aliases[] = $qpart->getAttribute()->getAliasWithRelationPath();
            } elseif ($group_by && $this->getAggregation($qpart->getAttribute()->getRelationPath()->toString())) {
                // If aggregating, also add attributes, that belong directly to objects, we are aggregating 
                // over (they can be assumed unique too, since their object is unique per row)
                $selects[] = $this->buildSqlSelect($qpart, null, null, null, 'MAX');
                $joins = array_merge($joins, $this->buildSqlJoins($qpart));
                $group_safe_attribute_aliases[] = $qpart->getAttribute()->getAliasWithRelationPath();
            }
        }
        $select = implode(', ', array_unique($selects));
        $enrichment_select = 'EXFCOREQ.*' . ($enrichment_select ? ', ' . substr($enrichment_select, 2) : '');
        // FROM
        $from = $this->buildSqlFrom();
        // JOINs
        $join = implode(' ', $joins);
        $enrichment_join = implode(' ', $enrichment_joins);
        // ORDER BY
        foreach ($this->getSorters() as $qpart) {
            // A sorter can only be used, if there is no GROUP BY, or the sorted attribute has unique values within the group
            /*
             * if (!$this->getAggregations() || in_array($qpart->getAttribute()->getAliasWithRelationPath(), $group_safe_attribute_aliases)){
             * $order_by .= ', ' . $this->buildSqlOrderBy($qpart);
             * }
             */
            $order_by .= ', ' . $this->buildSqlOrderBy($qpart);
        }
        $order_by = $order_by ? ' ORDER BY ' . substr($order_by, 2) : '';
        
        $distinct = $this->getSelectDistinct() ? 'DISTINCT ' : '';
        
        if ($this->getLimit()) {
            $limit = ' LIMIT ' . $this->getLimit() . ' OFFSET ' . $this->getOffset();
        }
        
        if (($group_by && $where) || $this->getSelectDistinct()) {
            $query = "\n SELECT " . $distinct . $enrichment_select . " FROM (SELECT " . $select . " FROM " . $from . $join . $where . $group_by . $having . $order_by . ") EXFCOREQ " . $enrichment_join . $order_by . $limit;
        } else {
            $query = "\n SELECT " . $distinct . $select . " FROM " . $from . $join . $where . $group_by . $order_by . $having . $limit;
        }
        
        return $query;
    }

    public function buildSqlQueryTotals()
    {
        $totals_joins = array();
        $totals_core_selects = array();
        $totals_selects = array();
        if (count($this->getTotals()) > 0) {
            // determine all joins, needed to perform the totals functions
            foreach ($this->getTotals() as $qpart) {
                $totals_selects[] = $this->buildSqlSelect($qpart, 'EXFCOREQ', $this->getShortAlias($qpart->getAlias()), null, $qpart->getFunction());
                $totals_core_selects[] = $this->buildSqlSelect($qpart);
                $totals_joins = array_merge($totals_joins, $this->buildSqlJoins($qpart));
            }
        }
        
        if ($group_by) {
            $totals_core_selects[] = $this->buildSqlSelect($this->getAttribute($this->getMainObject()->getUidAlias()), null, null, null, 'MAX');
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
            $totals_core_select = '1';
        }
        
        if ($totals_core_select) {
            $totals_query = "\n SELECT COUNT(*) AS EXFCNT " . $totals_select . " FROM (SELECT " . $totals_core_select . ' FROM ' . $totals_from . $totals_join . $totals_where . $totals_group_by . $totals_having . ") EXFCOREQ";
        } else {
            $totals_query = "\n SELECT COUNT(*) AS EXFCNT FROM " . $totals_from . $totals_join . $totals_where . $totals_group_by . $totals_having;
        }
        
        return $totals_query;
    }

    protected function prepareWhereValue($value, AbstractDataType $data_type, $sql_data_type = NULL)
    {
        if ($data_type->is(EXF_DATA_TYPE_DATE)) {
            $output = "{ts '" . $value . "'}";
        } else {
            $output = parent::prepareWhereValue($value, $data_type, $sql_data_type);
        }
        return $output;
    }

    protected function buildSqlSelectNullCheck($select_statement, $value_if_null)
    {
        return 'IFNULL(' . $select_statement . ', ' . (is_numeric($value_if_null) ? $value_if_null : '"' . $value_if_null . '"') . ')';
    }

    /**
     *
     * @see \exface\DataSources\QueryBuilders\sql_abstractSQL
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
            case 'LIST_DISTINCT':
            case 'LIST':
                $output = "GROUP_CONCAT(" . ($func == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . $select . " SEPARATOR " . ($args[0] ? $args[0] : "', '") . ")";
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

    /**
     * Special DELETE builder for MySQL because MySQL does not support table aliases in the DELETE query.
     * Thus, they must be removed from all the generated filters and other parts of the query.
     *
     * @see \exface\DataSources\QueryBuilders\sql_abstractSQL::delete()
     */
    function delete(AbstractDataConnector $data_connection = null)
    {
        // filters -> WHERE
        // Relations (joins) are not supported in delete clauses, so check for them first!
        if (count($this->getFilters()->getUsedRelations()) > 0) {
            throw new QueryBuilderException('Filters over attributes of related objects are not supported in DELETE queries!');
        }
        $where = $this->buildSqlWhere($this->getFilters());
        $where = $where ? "\n WHERE " . $where : '';
        if (! $where)
            throw new QueryBuilderException('Cannot perform update on all objects "' . $this->main_object->getAlias() . '"! Forbidden operation!');
        
        $sql = 'DELETE FROM ' . $this->getMainObject()->getDataAddress() . str_replace($this->getMainObject()->getAlias() . '.', '', $where);
        $query = $data_connection->runSql($sql);
        return $query->countAffectedRows();
    }
}
?>