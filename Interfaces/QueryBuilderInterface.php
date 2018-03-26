<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;

/**
 * Common interface for query builders.
 * 
 * @author Andrej Kabachnik
 *
 */
interface QueryBuilderInterface extends ExfaceClassInterface
{  
    /**
     * 
     * @return QueryBuilderSelectorInterface
     */
    public function getSelector() : QueryBuilderSelectorInterface;
    
    // TODO
}