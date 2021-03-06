<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;

abstract class DataColumnFactory extends AbstractStaticFactory
{

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param string|ExpressionInterface $expression_or_string            
     * @param string $name            
     * @return DataColumn
     */
    public static function createFromString(DataSheetInterface $data_sheet, $expression_or_string, $name = null)
    {
        if ($expression_or_string instanceof ExpressionInterface) {
            return self::createFromExpression($data_sheet, $expression_or_string, $name);
        }
        if ($expression_or_string === null || $expression_or_string === '') {
            throw new DataSheetRuntimeError($data_sheet, 'Cannot create data sheet column from empty string!');
        }
        return new DataColumn($expression_or_string, $name, $data_sheet);
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param ExpressionInterface $expression            
     * @param string $name            
     * @return DataColumn
     */
    public static function createFromExpression(DataSheetInterface $data_sheet, ExpressionInterface $expression, $name = null)
    {
        return new DataColumn($expression, $name, $data_sheet);
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param UxonObject $uxon            
     * @return DataColumn
     */
    public static function createFromUxon(DataSheetInterface $data_sheet, UxonObject $uxon)
    {
        $result = self::createFromString($data_sheet, ($uxon->hasProperty('expression') ? $uxon->getProperty('expression') : $uxon->getProperty('attribute_alias')), $uxon->getProperty('name'));
        $result->importUxonObject($uxon);
        return $result;
    }
}
?>