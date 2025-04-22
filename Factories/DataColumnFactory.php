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
    public static function createFromString(DataSheetInterface $data_sheet, $expression_or_string, string $name = null)
    {
        if ($expression_or_string === null || $expression_or_string === '') {
            throw new DataSheetRuntimeError($data_sheet, 'Cannot create data sheet column from empty string!');
        }
        return new DataColumn($expression_or_string, $data_sheet, $name);
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param ExpressionInterface $expression            
     * @param string $name            
     * @return DataColumn
     */
    public static function createFromExpression(DataSheetInterface $data_sheet, ExpressionInterface $expression, string $name = null)
    {
        return new DataColumn($expression, $data_sheet, $name);
    }

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @param UxonObject $uxon            
     * @return DataColumn
     */
    public static function createFromUxon(DataSheetInterface $data_sheet, UxonObject $uxon)
    {
        switch (true) {
            case null !== $exprString = $uxon->getProperty('expression'):
                break;
            case null !== $exprString = $uxon->getProperty('attribute_alias'):
                break;
            case null !== $exprString = $uxon->getProperty('formula');
                break;
        }
        if (null === $name = $uxon->getProperty('name')) {
            $name = DataColumn::sanitizeColumnName($exprString);
        }
        $result = self::createFromString($data_sheet, $exprString, $name);
        $result->importUxonObject($uxon);
        return $result;
    }
}