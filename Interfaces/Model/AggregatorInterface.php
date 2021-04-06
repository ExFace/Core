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
    
    /**
     * Returns the aggregator to use on values this aggregator was already applied to.
     * 
     * This is important for some data sources (like SQL with subqueries) as well as 
     * for aggregating in-memory - whenever data is aggregate iteratively in steps. 
     * In this case, aggregators, that cannot change the data type cannot be applied 
     * multiple itemes (because of the possible incompatible data type after the first) 
     * application. 
     * 
     * For example, lets count delivered order positions on the `CUSTOMER` level. Assume
     * the following relations: `CUSTOMER <- ORDER <- ORDER_POS`. We only need those 
     * `ORDER_POS` with `DELIVERED_FLAG = 1`. One of the approaches (depending on the query 
     * builder) would be counting delivered positions per `ORDER` and summing them up.
     * That "summing up" is exactly what this method is for!
     * 
     * @return AggregatorInterface
     */
    public function getNextLevelAggregator() : AggregatorInterface;
}