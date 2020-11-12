<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Interfaces\iCanBeConvertedToString;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Aggregators are special expressions to define data aggregation like `SUM`, `AVG`, but also `COUNT_IF(condition)`.
 * 
 * Aggregators consist of a function name and (sometimes) optional parameters in parentheses. 
 * 
 * @author Andrej Kabachnik
 *
 */
interface AggregatorInterface extends WorkbenchDependantInterface, iCanBeConvertedToString
{
    /**
     * Instantiates an aggregator from it's string representation - e.g. `COUNT_IF(condition)`.
     * 
     * The $arguments are treated as defaults: if the aggregator string has it's own arguments,
     * they will be used instead of $arguments.
     * 
     * @param Workbench $workbench
     * @param string|AggregatorFunctionsDataType $aggregator_string
     * @param array $arguments
     */
    public function __construct(Workbench $workbench, $aggregator_string, array $arguments = null);
    
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
     * Returns TRUE if this aggregator uses the same aggregate function as the given one.
     * 
     * @param AggregatorInterface|string $stringOrAggregator
     * @return bool
     */
    public function is($stringOrAggregator) : bool;
    
    /**
     * Returns the data type resulting after aggregating the given data type.
     * 
     * @param DataTypeInterface $aggregatedType
     * @return DataTypeInterface
     */
    public function getResultDataType(DataTypeInterface $aggregatedType);
}