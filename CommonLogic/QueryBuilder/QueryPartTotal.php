<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class QueryPartTotal extends QueryPartAttribute
{

    private $row = 0;

    private $function = null;

    public function getRow()
    {
        return $this->row;
    }

    public function setRow($value)
    {
        $this->row = $value;
    }

    public function getFunction()
    {
        return $this->function;
    }

    public function setFunction($value)
    {
        $this->function = $value;
    }
}
?>