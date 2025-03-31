<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Puts values of a column in the from-sheet into a JSON inside a column of the to-sheet.
 * 
 * @author Andrej Kabachnik
 */
class DataColumnToJsonMapping extends DataColumnMapping 
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        
        $log = "Column `{$fromExpr->__toString()}` -> JSON in `{$toExpr->__toString()}`";
        
        $jsonCol = $toSheet->getColumns()->getByExpression($toExpr);
        if (! $jsonCol) {
            $jsonCol = $toSheet->getColumns()->addFromExpression($toExpr);
        }
        switch (true) {
            // Constants and static formulas
            case $fromExpr->isStatic():
            // Formulas with data
            case $fromExpr->isFormula():
                $vals = $fromExpr->evaluate($fromSheet);
                $jsonKey = $fromExpr->__toString();
                break;
            // Data column references
            case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                $vals = $fromCol->getValues(false);
                if ($fromExpr->isMetaAttribute()) {
                    $jsonKey = $fromExpr->getAttribute()->getAliasWithRelationPath();
                } else {
                    $jsonKey = $fromExpr->__toString();
                }
                break;
            // Data column references should not result in errors if the data sheet is completely empty
            // Otherwise input-mappers would always produce errors on empty input data!
            case $fromSheet->getColumns()->isEmpty() && ! $fromExpr->isReference():
                if ($logbook !== null) $logbook->addLine($log . ' Not required because from-sheet is empty.');
                return $toSheet;
            default:
                if ($fromExpr->isMetaAttribute()) {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map from attribute "' . $fromExpr->toString() . '" in a column-to-column mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!', '7H6M243');
                }
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $fromExpr->toString() . '" as from-expression in a column-to-column mapping: only data column names, constants and formulas allowed!', '7H6M243');
        }

        $log .= ' with key `' . $jsonKey . '`.';

        foreach ($vals as $rowIdx => $val) {
            $json = $jsonCol->getValue($rowIdx);
            if ($json === null || $json === '') {
                $array = [];
            } else {
                $array = JsonDataType::decodeJson($json);
            }
            $array[$jsonKey] = $val;
            $jsonCol->setValue($rowIdx, JsonDataType::encodeJson($array));
        }
        
        if ($logbook !== null) $logbook->addLine($log);

        return $toSheet;
    }
}