<?php
namespace exface\Core\Interfaces\DataSheets;

interface DataSheetSubsheetInterface extends DataSheetInterface
{

    /**
     *
     * @return DataSheetInterface
     */
    public function getParentSheet();

    /**
     *
     * @param DataSheetInterface $value            
     */
    public function setParentSheet($value)
    {
        $this->parent_sheet = $value;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getJoinParentOnColumnId()
    {
        return $this->join_parent_on_column_id;
    }

    /**
     *
     * @param string $value            
     * @return DataSheetSubsheetInterface
     */
    public function setJoinParentOnColumnId($value);
}

?>