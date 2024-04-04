<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;

/**
 * A query builder for Microsoft SQL 2008.
 * 
 * Supported dialect tags in multi-dialect statements (in order of priority): `@T-SQL2008:`, `@MSSQL2008:`, `@T-SQL:`, `@MSSQL`, `@OTHER`.
 * 
 * See `MsSqlBuilder` for more information.
 * 
 * Differences compared to the generic `MsSqlBuilder`:
 * 
 * - Pagination is done via `ROW_NUMBER() OVER(...)` instead of `OFFSET x ROWS FETCH NEXT y ROWS ONLY`
 * 
 * @author Andrej Kabachnik
 *        
 */
class MsSql2008Builder extends MsSqlBuilder
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge(['T-SQL2008', 'MSSQL2008'], parent::getSqlDialects());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MsSqlBuilder::buildSqlQuerySelectWithEnrichment()
     */
    protected function buildSqlQuerySelectWithEnrichment(string $select, string $enrichment_select, string $select_comment, string $from, string $join, string $enrichment_join, string $where, string $group_by, string $having, string $order_by, string $limit, string $distinct = '') : string
    {
        if ($this->getLimit() > 0) {
            if ($order_by == '') {
                if ($this->getMainObject()->hasUidAttribute()) {
                    $order_by = 'ORDER BY ' . $this->getMainObject()->getUidAttribute()->getDataAddress() . ' ASC';
                } else {
                    throw new QueryBuilderException('At least one sorter is required to use SQL paging with MS SQL 2008 on object without UIDs (primary keys)!');
                }
            } else {
                $order_by = '';
                foreach ($this->getSorters() as $qpart) {
                    $order_by .= ', ' . $this->buildSqlOrderBy($qpart, 'EXFCOREQ');
                }
                if ($order_by === '') {
                    $order_by = ', ' . $this->buildSqlOrderByDefault(true, 'EXFCOREQ');
                }
                $order_by = 'ORDER BY ' . substr($order_by, 2);
            }
            $enrichment_select .= ($enrichment_select ? ', ' : '') . "ROW_NUMBER() OVER ({$order_by}) AS EXFROWNUM";
            $limit = '';
            $order_by = '';
        }
        
        $query = parent::buildSqlQuerySelectWithEnrichment($select, $enrichment_select, $select_comment, $from, $join, $enrichment_join, $where, $group_by, $having, $order_by, $limit, $distinct);
        
        return $this->buildSqlQuerySelectRownumWrapper($query);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MsSqlBuilder::buildSqlQuerySelectWithoutEnrichment()
     */
    protected function buildSqlQuerySelectWithoutEnrichment(string $select, string $select_comment, string $from, string $join, string $where, string $group_by, string $having, string $order_by, string $limit, string $distinct = '') : string
    {        
        if ($this->getLimit() > 0) {
            if ($order_by == '') {
                if ($this->getMainObject()->hasUidAttribute()) {
                    $order_by = 'ORDER BY ' . $this->getMainObject()->getUidAttribute()->getDataAddress() . ' ASC';
                } else {
                    throw new QueryBuilderException('At least one sorter is required to use SQL paging with MS SQL 2008 on object without UIDs (primary keys)!');
                }
            } else {
                $order_by = '';
                $mainTableAlias = $this->getShortAlias($this->getMainObject()->getAlias());
                foreach ($this->getSorters() as $qpart) {
                    $order_by .= ', ' . $this->buildSqlOrderBy($qpart, $mainTableAlias);
                }
                if ($order_by === '') {
                    $order_by = ', ' . $this->buildSqlOrderByDefault(true, $mainTableAlias);
                }
                $order_by = 'ORDER BY ' . substr($order_by, 2);
            }
            $select .= ($select ? ', ' : '') . "ROW_NUMBER() OVER ({$order_by}) AS EXFROWNUM";
            $limit = '';
            $order_by = '';
        }
        
        $query = parent::buildSqlQuerySelectWithoutEnrichment($select, $select_comment, $from, $join, $where, $group_by, $having, $order_by, $limit, $distinct);
        
        return $this->buildSqlQuerySelectRownumWrapper($query);
    }
    
    /**
     * 
     * @param string $query
     * @return string
     */
    protected function buildSqlQuerySelectRownumWrapper(string $query) : string
    {
        if ($this->getLimit() > 0) {
            $from = $this->getOffset() + 1; // ROW_NUMBER() OVER starts at 1!!! 
            $to = $this->getOffset() + $this->getLimit() + 1;
            $query = <<<SQL
SELECT *
    FROM ({$query}) EXFUNPAGED
    WHERE EXFROWNUM BETWEEN {$from} AND {$to}
    
SQL;
        }
        return $query;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlOrderBy()
     */
    protected function buildSqlOrderBy(QueryPartSorter $qpart, $select_from = '') : string
    {
        // SQL Server 2008 cannot sort over an alias from the SELECT - need to place the entire data address 
        // in the ORDER BY in case the attribute is a custom SQL statement.
        if ($this->checkForSqlStatement($qpart->getDataAddress()) 
            && ! $qpart->getDataAddressProperty(static::DAP_SQL_ORDER_BY) 
            && $select_from === $this->getShortAlias($this->getMainObject()->getAlias())
        ) {
            return $this->buildSqlSelect($qpart, null, null, '') . ' ' . $qpart->getOrder();
        }
        
        // Similarly, we must use the original select clause if we are trying to sort over a joined
        // or subselected column
        if (! empty($qpart->getUsedRelations()) && ! $qpart->getDataAddressProperty(static::DAP_SQL_ORDER_BY)) {
            return $this->buildSqlSelect($qpart, null, null, '') . ' ' . $qpart->getOrder();
        }
        
        return parent::buildSqlOrderBy($qpart, $select_from);
    }
}