<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetSubsheet;

abstract class DataSheetSubsheetFactory
{

    /**
     * Returns a new subsheet based on the specified object for the give data parent data sheet
     *
     * @param MetaObjectInterface $meta_object            
     * @param DataSheet $parent_sheet            
     * @return \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface
     */
    public static function createForObject(MetaObjectInterface $meta_object, DataSheetInterface $parent_sheet)
    {
        $result = new DataSheetSubsheet($meta_object);
        $result->setParentSheet($parent_sheet);
        return $result;
    }

    /**
     *
     * @param DataSheet $data_sheet            
     * @param DataSheet $parent_sheet            
     * @return \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface
     */
    public static function createFromDataSheet(DataSheetInterface $data_sheet, DataSheetInterface $parent_sheet)
    {
        $meta_object = $data_sheet->getMetaObject();
        $result = self::createForObject($meta_object, $parent_sheet);
        $result->importUxonObject($data_sheet->exportUxonObject());
        return $result;
    }
}
?>