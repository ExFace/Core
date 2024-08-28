<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\ArrayDataType;

class RowDataArrayAggregator
{
    /**
     * 
     * @param array $arrayOfRows
     * @param string $colName
     * @param AggregatorInterface $aggregator
     * @return string|float|int
     */
    public static function aggregate(array $arrayOfRows, string $colName, AggregatorInterface $aggregator)
    {
        $values = array_column($arrayOfRows, $colName);
        return ArrayDataType::aggregateValues($values, $aggregator);
    }
}