<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Interfaces\iCanBeConvertedToString;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

interface AggregatorInterface extends WorkbenchDependantInterface, iCanBeConvertedToString
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
    
    /**
     * Returns the data type resulting after aggregating the given data type.
     * 
     * @param DataTypeInterface $aggregatedType
     * @return DataTypeInterface
     */
    public function getResultDataType(DataTypeInterface $aggregatedType);
}