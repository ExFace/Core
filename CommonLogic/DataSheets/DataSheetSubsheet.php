<?php
namespace exface\Core\CommonLogic\DataSheets;

class DataSheetSubsheet extends DataSheet
{

    private $parent_sheet = null;

    private $join_parent_on_column_id = null;

    public function getParentSheet()
    {
        return $this->parent_sheet;
    }

    public function setParentSheet($value)
    {
        $this->parent_sheet = $value;
        return $this;
    }

    public function getJoinParentOnColumnId()
    {
        return $this->join_parent_on_column_id;
    }

    public function setJoinParentOnColumnId($value)
    {
        $this->join_parent_on_column_id = $value;
        return $this;
    }
}

?>