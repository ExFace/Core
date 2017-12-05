<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\Interfaces\Model\AggregatorInterface;

class QueryPartTotal extends QueryPartAttribute
{

    private $row = 0;

    private $function = null;

    /**
     * 
     * @return integer
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * 
     * @param integer $value
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartTotal
     */
    public function setRow($value)
    {
        $this->row = $value;
        return $this;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\Model\AggregatorInterface
     */
    public function getTotalAggregator()
    {
        return $this->function;
    }

    /**
     * Returns the aggregator used to calculate a total for the query part (not to be 
     * confused with the one in the actual expression!).
     * 
     * Assuming the results of the query to be a table, this aggregator aggregates values 
     * from the cells of the column represented by this query part, while the one from 
     * getAggregator() is used to calculate each cell's value.
     * 
     * @param AggregatorInterface $value
     * @return QueryPartTotal
     */
    public function setTotalAggregator(AggregatorInterface $value)
    {
        $this->function = $value;
        return $this;
    }
}
?>