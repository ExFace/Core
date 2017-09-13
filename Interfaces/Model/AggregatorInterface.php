<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\Constants\AggregatorFunctions;
use exface\Core\Interfaces\iCanBeConvertedToString;

interface AggregatorInterface extends iCanBeConvertedToString
{
    public function __construct($aggregator_string);
    
    /**
     * @return AggregatorFunctions
     */
    public function getFunction();
    
    /**
     * @return array
     */
    public function getArguments();
    
    /**
     * @return boolean
     */
    public function hasArguments();
}