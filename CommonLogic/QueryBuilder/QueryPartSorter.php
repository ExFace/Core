<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class QueryPartSorter extends QueryPartAttribute
{

    private $order;

    private $apply_after_reading = false;

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($value)
    {
        if (! $value)
            $value = 'ASC';
        $this->order = $value;
    }

    /**
     *
     * @return boolean
     */
    public function getApplyAfterReading()
    {
        return $this->apply_after_reading;
    }

    /**
     *
     * @param boolean $value            
     * @return QueryPartSorter
     */
    public function setApplyAfterReading($value)
    {
        $this->apply_after_reading = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
}

?>