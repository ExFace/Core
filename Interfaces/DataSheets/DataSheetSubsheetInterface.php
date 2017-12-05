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
    public function setParentSheet($value);

    /**
     *
     * @return string
     */
    public function getJoinParentOnColumnId();

    /**
     *
     * @param string $value            
     * @return DataSheetSubsheetInterface
     */
    public function setJoinParentOnColumnId($value);
}

?>