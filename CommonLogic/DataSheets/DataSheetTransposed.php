<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnToFilterMappingInterface;
use exface\Core\Uxon\DataSheetMapperSchema;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Factories\DataSheetFactory;

/**
 * Maps on data sheet $column to a filter expression in another data sheet.
 * 
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetTransposed extends DataSheet 
{
    private $rawSheet = null;
    
    public function __construct(\exface\Core\Interfaces\Model\MetaObjectInterface $meta_object)
    {
        parent::__construct($meta_object);
        $this->rawSheet = DataSheetFactory::createFromObject($meta_object);
    }
    
    protected function getRawSheet() : DataSheetInterface
    {
        return $this->rawSheet;
    }
    
    protected function transpose(DataSheetInterface $fromSheet, array $dataCols, DataColumnInterface $labelCol, DataSheetInterface $toSheet)
    {        
        $labelDelim = '~';
        $labelColName = $labelCol->getName();
        
        
        if (count($dataCols) > 1) {
            // Add subtitle column
        }
        
        $otherColNames = [];
        $dataColNames = [];
        foreach ($fromSheet->getColumns() as $fromCol) {
            switch (true) {
                case in_array($fromCol, $dataCols):
                    $dataColNames[] = $fromCol->getName();
                    break;
                case $fromCol === $labelCol:
                    $labels = array_unique($labelCol->getValues());
                    // TODO sort labels
                    foreach($labels as $labelVal) {
                        $toSheet->getColumns()->addFromExpression($fromCol->getExpressionObj(), $labelColName . $labelDelim . DataColumn::sanitizeColumnName($labelVal));
                    }
                    break;
                default: 
                    $otherColNames[] = $fromCol->getName();
                    $toSheet->getColumns()->addFromExpression($fromCol->getExpressionObj(), $fromCol->getName(), $fromCol->getHidden());
            }
        }
        
        $fromRows = $fromSheet->getRows();
        $toRowKeys = [];
        foreach ($fromRows as $fromRowIdx => $fromRow) {
            $toRowKey = '';
            foreach ($otherColNames as $name) {
                $toRowKey .= '--' . $name;
            }
            $toRowKeys[$fromRowIdx] = $toRowKey;
        }
        
        $toRowsWithKeys = [];
        foreach ($fromRows as $fromRowIdx => $fromRow) {
            $toRowKey = $toRowKeys[$fromRowIdx];
            foreach ($fromRow as $fromName => $fromVal) {
                switch (true) {
                    case in_array($fromName, $dataColNames):
                        $toRowsWithKeys[$toRowsWithKeys][$labelColName . $labelDelim . DataColumn::sanitizeColumnName($labelVal)] = $fromVal;
                        break;
                    case $fromName === $labelColName:
                        
                        break;
                    default:
                        $toRowsWithKeys[$toRowKey][$fromName] = $fromVal;
                }
            }
        }
        
        $toRows = array_values($toRowsWithKeys);
        $toSheet->addRows($toRows);

    }
}