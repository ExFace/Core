<?php

namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataColumnTotalInterface;
use exface\Core\CommonLogic\DataSheets\DataColumnTotal;

abstract class DataColumnTotalsFactory extends AbstractFactory
{

    /**
     *
     * @param DataColumnInterface $data_column            
     * @return DataColumnTotalInterface
     */
    public static function createEmpty(DataColumnInterface $data_column)
    {
        $result = new DataColumnTotal($data_column);
        return $result;
    }

    /**
     *
     * @param DataColumnInterface $data_column            
     * @param string $function_name            
     * @return DataColumnTotalInterface
     */
    public static function createFromString(DataColumnInterface $data_column, $function_name)
    {
        $result = static::createEmpty($data_column);
        $result->setFunction($function_name);
        return $result;
    }

    /**
     *
     * @param DataColumnInterface $data_column            
     * @param UxonObject $uxon            
     * @return DataColumnTotalInterface
     */
    public static function createFromUxon(DataColumnInterface $data_column, UxonObject $uxon)
    {
        $result = static::createEmpty($data_column);
        $result->importUxonObject($uxon);
        return $result;
    }
}
?>