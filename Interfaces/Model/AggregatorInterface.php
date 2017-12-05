<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Interfaces\iCanBeConvertedToString;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;

interface AggregatorInterface extends ExfaceClassInterface, iCanBeConvertedToString
{
    public function __construct(Workbench $workbench, $aggregator_string);
    
    /**
     * @return AggregatorFunctionsDataType
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