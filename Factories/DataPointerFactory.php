<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataPointerInterface;
use exface\Core\CommonLogic\DataSheets\DataPointer;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

/**
 * This factory produces data pointers
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class DataPointerFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param string $columnName
     * @param int $rowNumber
     * @return DataPointerInterface
     */
    public static function createFromCoordinates(DataSheetInterface $dataSheet, string $columnName, int $rowNumber) : DataPointerInterface
    {
        return new DataPointer($dataSheet, $columnName, $rowNumber);
    }
    
    /**
     * 
     * @param DataColumnInterface $column
     * @param int $rowNumber
     * @return DataPointerInterface
     */
    public static function createFromColumn(DataColumnInterface $column, int $rowNumber = null) : DataPointerInterface
    {
        return new DataPointer($column->getDataSheet(), $column->getName(), $rowNumber);
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param int $rowNumber
     * @return DataPointerInterface
     */
    public static function createFromRow(DataSheetInterface $dataSheet, int $rowNumber) : DataPointerInterface
    {
        return new DataPointer($dataSheet, null, $rowNumber);
    }
}
?>