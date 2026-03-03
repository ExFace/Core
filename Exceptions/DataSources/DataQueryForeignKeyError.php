<?php
namespace exface\Core\Exceptions\DataSources;

/**
 * Exception thrown if a query fails due to a foreign key violation within the data source.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataQueryForeignKeyError extends DataQueryConstraintError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '8557RS3';
    }
}