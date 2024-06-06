<?php
namespace exface\Core\Exceptions\DataSources;


/**
 * Exception thrown if a query fails due to multiple results for a single row.
 * 
 * In SQL databases this woul correspond to something like "Subquery returns more than 1 row".
 * This type of error typically indicates, that a proper aggregation should be used
 * for the affected relation.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataQueryRelationCardinalityError extends DataQueryFailedError
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7W2J960';
    }
}