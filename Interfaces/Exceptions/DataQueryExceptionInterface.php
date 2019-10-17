<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

interface DataQueryExceptionInterface
{

    /**
     *
     * @param DataQueryInterface $query            
     * @param string $message            
     * @param string $code            
     * @param string $previous            
     */
    public function __construct(DataQueryInterface $query, $message, $code = null, $previous = null);

    /**
     *
     * @return DataQueryInterface
     */
    public function getQuery();

    /**
     *
     * @param DataQueryInterface $query            
     */
    public function setQuery(DataQueryInterface $query);
}
