<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a data source query fails.
 * It will produce usefull debug information about the query (e.g.
 * a nicely formatted SQL statement for SQL data queries).
 *
 * It is advisable to wrap this exception around any data source specific exceptions to enable the plattform, to
 * understand what's going without having to deal with data source specific exception types.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataQueryFailedError extends RuntimeException implements DataQueryExceptionInterface
{
    use DataQueryExceptionTrait;

    public function __construct(DataQueryInterface $query, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setQuery($query);
    }
    
    public function getDefaultLogLevel(){
        return LoggerInterface::CRITICAL;
    }
}