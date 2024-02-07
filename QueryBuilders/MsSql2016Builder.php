<?php
namespace exface\Core\QueryBuilders;

/**
 * A query builder for Microsoft SQL 2016.
 * 
 * Supported dialect tags in multi-dialect statements (in order of priority): 
 * - `@T-SQL2016:`, 
 * - `@MSSQL2016:`, 
 * - `@T-SQL:`, 
 * - `@MSSQL`, 
 * - `@OTHER`.
 * 
 * See `MsSqlBuilder` for more information.
 * 
 * Differences compared to the generic `MsSqlBuilder`:
 * 
 * - Added dialect `@T-SQL2016` in order to be able to write specific data addresses for 
 * SQL SERVER 2016 - e.g. without `TRIM()` function, etc.
 * 
 * @author Andrej Kabachnik
 *        
 */
class MsSql2016Builder extends MsSqlBuilder
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge(['T-SQL2016', 'MSSQL2016'], parent::getSqlDialects());
    }
}