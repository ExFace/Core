<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\DataSheets\DataColumn;

class QueryPartSelect extends QueryPartAttribute
{

    private $column_key = null;
    
    public function __construct($alias, AbstractQueryBuilder $query, string $column_name) {
        parent::__construct($alias, $query);
        $this->column_key = $column_name;
    }
    
    public function isValid()
    {
        if ($this->getAttribute()->getDataAddress() != '')
            return true;
        return false;
    }
    
    public function getColumnKey() : string
    {
        return $this->column_key;
    }
}
?>