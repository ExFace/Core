<?php
namespace exface\Core\Exceptions\DataSources;

/**
 * Exception thrown if the internal cross-datasource transaction fails to rollback.
 * This normally indicates,
 * that a rollback in one of the affected data sources failed. The respective DataConnectionRollbackError will
 * then be attached as the previous exception.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTransactionRollbackError extends DataTransactionError
{

    public static function getDefaultAlias()
    {
        return '6T5VJD3';
    }
}
?>