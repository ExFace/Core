<?php
namespace exface\Core\Interfaces\DataSources;

/**
 * 
 * @author Andrej Kabachnik
 *        
 */
interface DataQueryResultDataInterface
{
    /**
     * 
     * @return array
     */
    public function getResultRows() : array;
    
    /**
     * 
     * @return bool
     */
    public function hasMoreRows() : bool;
    
    /**
     * 
     * @return array
     */
    public function getAggregationRows() : array;
    
    /**
     * 
     * @return int|NULL
     */
    public function getTotalRowCounter() : ?int;
    
    /**
     * 
     * @return int
     */
    public function countResultRows() : int;
    
    /**
     * 
     * @return bool
     */
    public function hasResultRows() : bool;
    
    /**
     * 
     * @return int|NULL
     */
    public function getAffectedRowCounter() : ?int;
}
?>