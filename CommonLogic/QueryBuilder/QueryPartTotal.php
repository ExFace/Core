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