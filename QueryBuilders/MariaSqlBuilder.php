<?php
namespace exface\Core\QueryBuilders;

/**
 * A query builder for MariaDB.
 *
 * Supported dialect tags in multi-dialect statements (in order of priority): `@MariaDB:`, `@MySQL:`, `@OTHER:`.
 * 
 * See `AbstractSqlBuilder` and `MySqlBuilder` for available data address options!
 * 
 * @see AbstractSqlBuilder
 * @see MySqlBuilder
 *
 * @author Andrej Kabachnik
 *        
 */
class MariaSqlBuilder extends MySqlBuilder
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\MySqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge(['MariaDB'], parent::getSqlDialects());
    }
}