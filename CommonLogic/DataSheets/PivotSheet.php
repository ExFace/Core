<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\PivotSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface;

/**
 * A special data sheet with transposed columns
 * 
 * @author Andrej Kabachnik
 *
 */
class PivotSheet extends DataSheet implements PivotSheetInterface
{
    const COLUMN_PREFIX = '_pvt_';
    
    const COLUMN_SUBROW_IDX = self::COLUMN_PREFIX . 'subRowIndex';
    
    const COLUMN_SUBROW_CAPTION = self::COLUMN_PREFIX . 'subtitle_';
    
    private $pivotResultSheet = null;
    
    private $pivotColGroups = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::addColumnToTranspose()
     */
    public function addColumnToTranspose(DataColumnInterface $valuesColumn, DataColumnInterface $headersColumn) : PivotSheetInterface
    {
        foreach ($this->pivotColGroups as $grp) {
            if ($grp->getColumnWithHeaders() === $headersColumn) {
                $grp->addColumnWithValues($valuesColumn);
                return $this;
            }
        }
        $this->pivotColGroups[] = (new PivotColumnGroup($headersColumn))->addColumnWithValues($valuesColumn);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::getPivotColumnGroups()
     */
    public function getPivotColumnGroups() : array
    {
        return $this->pivotColGroups;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::getPivotResultDataSheet()
     */
    public function getPivotResultDataSheet() : DataSheetInterface
    {
        if ($this->pivotResultSheet === null) {
            $this->pivotResultSheet = $this->pivot();
        }
        return $this->pivotResultSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::getRowsUnpivoted()
     */
    public function getRowsUnpivoted() : array
    {
        return parent::getRows();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::getRowsPivoted()
     */
    public function getRowsPivoted() : array
    {
        return $this->getPivotResultDataSheet()->getRows();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::isColumnWithPivotValues()
     */
    public function isColumnWithPivotValues(DataColumnInterface $col) : bool
    {
        foreach ($this->getPivotColumnGroups() as $grp) {
            if ($grp->hasColumnWithValues($col)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::isColumnWithPivotHeaders()
     */
    public function isColumnWithPivotHeaders(DataColumnInterface $col) : bool
    {
        foreach ($this->getPivotColumnGroups() as $grp) {
            if ($grp->getColumnWithHeaders() === $col) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotSheetInterface::getPivotColumnGroup()
     */
    public function getPivotColumnGroup(DataColumnInterface $col) : ?PivotColumnGroupInterface
    {
        foreach ($this->getPivotColumnGroups() as $grp) {
            if ($grp->getColumnWithHeaders() === $col) {
                return $grp;
            }
            if ($grp->hasColumnWithValues($col)) {
                return $grp;
            }
        }
        return null;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function pivot() : DataSheetInterface
    {
        $resultSheet = DataSheetFactory::createFromObject($this->getMetaObject());
        
        $replacements = [];
        $pivotHeadersColNames = [];
        $pivotValuesColNames = [];
        $visibleColNames = [];
        $stringDataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
        $colGroups = [];
        $newRowsByCompoundKey = [];
        foreach ($this->getPivotColumnGroups() as $colGrp) {
            $colGroups[$colGrp->getColumnWithHeaders()->getName()] = $colGrp;
            foreach ($colGrp->getColumnsWithValues() as $col) {
                $colGroups[$col->getName()] = $colGrp;
            }
        }
        foreach ($this->getColumns() as $colName => $col) {
            switch (true) {
                // If this column is to be transposed
                case $this->isColumnWithPivotValues($col):
                    $pivotValuesColNames[] = $col->getName();
                    break;
                // If it is one of the label columns
                case $this->isColumnWithPivotHeaders($col):
                    $pivotHeadersColNames[] = $col->getName();
                    $colGrp = $this->getPivotColumnGroup($col);
                    // Add a subtitle column to show a caption for each subrow if there are multiple
                    if ($colGrp->countColumnsWithValues() > 1){
                        $newCol = $resultSheet->getColumns()->addFromExpression(self::COLUMN_SUBROW_IDX, self::COLUMN_SUBROW_IDX, true);
                        $replacements[$colGrp->getColumnWithHeaders()->getName()][] = $newCol;
                        $newCol = $resultSheet->getColumns()->addFromExpression(self::COLUMN_SUBROW_CAPTION . $colName, self::COLUMN_SUBROW_CAPTION . $colName, false);
                        $replacements[$colGrp->getColumnWithHeaders()->getName()][] = $newCol;

                    }
                    // Create a column for each value if it is the label column
                    $labels = array_filter(array_unique($col->getValues()));
                    foreach ($labels as $label) {
                        $newColName = $this->getPivotColumnName($label);
                        $newCol = new PivotColumn($newColName, $resultSheet, $newColName, $colGrp);
                        $resultSheet->getColumns()->add($newCol);
                        $newCol->setTitle($label);
                        $newCol->setDataType($stringDataType);
                        $replacements[$colGrp->getColumnWithHeaders()->getName()][] = $newCol;
                    }
                    // FIXME
                    // Create a totals column if there are totals
                    // The footer of the totals column will contain the overall total provided by the server
                    /*if ($col->hasAggregator()){
                        var totals = [];
                        for (var t$colName in oDataColsTotals){
                            var tfunc = oDataColsTotals[t$colName];
                            if (totals.indexOf(tfunc) === -1){
                                totals.push(tfunc);
                                oData.footer[0][$col.sDataColumnName] = oData.footer[0][t$colName];
                                
                                oResult.$colModelsTransposed[$colName+'_'+tfunc] = $.extend(true, {}, $col, {
                                    bTransposedColumn: true,
                                    sTransposedColumnRole: 'subRowTotal',
                                    sDataColumnName: $colName+'_'+tfunc,
                                    sCaption: oAggrLabels[tfunc],
                                    sAlign: 'right',
                                });
                                    $col.aReplacedWithColumnKeys.push($colName+'_'+tfunc);
                            }
                        }
                    }
                    oResult.$colModelsTransposed[$col.sDataColumnName] = $col;
                    */
                    break;
                // Regular columns
                default:
                    if ($col->getHidden() === false) {
                        $visibleColNames[] = $col->getName();
                        $resultSheet->getColumns()->add($col->copy());
                    }
            }
        } // for ($colName in $colModels)
        
        foreach ($this->getRows() as $row){
            $newRowId = '';
            $newRow = [];
            $newColVals = [];
            $newColId = '';
            foreach ($row as $colName => $val){
                switch (true) {
                    case in_array($colName, $pivotHeadersColNames):
                        $newColId = DataColumn::sanitizeColumnName($this->getPivotColumnName($val));
                        break;
                    case in_array($colName, $pivotValuesColNames):
                        $newColVals[$colName] = $val;
                        break;
                    case in_array($colName, $visibleColNames):
                        $newRowId .= $val;
                        $newRow[$colName] = $val;
                        break;
                        
                        // TODO save UID and other system attributes to some invisible data structure
                }
            }
            
            foreach ($newColVals as $colName => $val){
                $colGrp = $colGroups[$colName];
                $colOrig = $this->getColumns()->get($colName);
                $compoundKey = $newRowId . $colName;
                if ($newRowsByCompoundKey[$compoundKey] === null){
                    $newRowsByCompoundKey[$compoundKey] = array_merge($row, [self::COLUMN_SUBROW_IDX => $colGrp->getColumnIndex($colOrig)]);
                }
                $newRowsByCompoundKey[$compoundKey][$newColId] = $colOrig->getDataType()->format($val);
                // FIXME $newRowsByCompoundKey[$compoundKey][self::COLUMN_SUBROW_CAPTION . $newColGroup] = oResult.$colModelsOriginal[$colName].sCaption;
                /*if (oDataColsTotals[$colName] != undefined){
                    var newVal = parseFloat(newColVals[$colName]);
                    var oldVal = newRowsByCompoundKey[newRowId+$colName][newColGroup+'_'+oDataColsTotals[$colName]];
                    var oldTotal = (oData.footer[0][newColId] || 0);
                    oldVal = oldVal ? oldVal : 0;
                    switch (oDataColsTotals[$colName]){
                        case 'SUM':
                            newRowsByCompoundKey[newRowId+$colName][newColGroup+'_'+oDataColsTotals[$colName]] = oldVal + newVal;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal + newVal;
                            }
                            break;
                        case 'MAX':
                            newRowsByCompoundKey[newRowId+$colName][newColGroup+'_'+oDataColsTotals[$colName]] = oldVal < newVal ? newVal : oldVal;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal < newVal ? newVal : oldTotal;
                            }
                            break;
                        case 'MIN':
                            newRowsByCompoundKey[newRowId+$colName][newColGroup+'_'+oDataColsTotals[$colName]] = oldVal > newVal ? newVal : oldVal;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal > newVal ? newVal : oldTotal;
                            }
                            break;
                        case 'COUNT':
                            newRowsByCompoundKey[newRowId+$colName][newColGroup+'_'+oDataColsTotals[$colName]] = oldVal + 1;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal + 1;
                            }
                            break;
                            // TODO add more totals
                    }
                }*/
            }
        }
        foreach ($newRowsByCompoundKey as $row){
            $rowsNew[] = $row;
        }
        
        if (!empty($rowsNew)) {
            $resultSheet->addRows($rowsNew, false, false);
        }        
        return $resultSheet;
    }
    
    /**
     * 
     * @param string $value
     * @return string
     */
    protected function getPivotColumnName(string $value) : string
    {
        $labelColName = DataColumn::sanitizeColumnName($value);
        return self::COLUMN_PREFIX . $labelColName;
    }
}