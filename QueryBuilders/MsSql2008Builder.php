<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;

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
     * @see \exface\Core\QueryBuilders\MsSqlBuilder::getSqlServerVersion()
     */
    protected function getSqlServerVersion() : string
    {
        return 2008;
    }
    
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
                $order_by = 'ORDER BY ';
                foreach ($this->getSorters() as $qpart) {
                    $order_by .= $this->buildSqlOrderBy($qpart, 'EXFCOREQ');
                }
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
                $order_by = 'ORDER BY ';
                foreach ($this->getSorters() as $qpart) {
                    $order_by .= $this->buildSqlOrderBy($qpart, $this->getShortAlias($this->getMainObject()->getAlias()));
                }
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
            $limitPlusOne = $this->getLimit() + 1;
            $query = <<<SQL
SELECT *
    FROM ({$query}) EXFUNPAGED
    WHERE EXFROWNUM BETWEEN {$this->getOffset()} AND {$limitPlusOne}
    
SQL;
        }
        return $query;
    }
}