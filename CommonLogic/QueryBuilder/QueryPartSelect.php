<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class QueryPartSelect extends QueryPartAttribute
{

    public function isValid()
    {
        if ($this->getAttribute()->getDataAddress() != '')
            return true;
        return false;
    }
}
?>