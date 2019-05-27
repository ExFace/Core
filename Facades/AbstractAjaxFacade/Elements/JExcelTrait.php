<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataImporter;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\InputSelect;
use exface\Core\Widgets\InputCombo;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\StringDataType;

/**
 * Common methods for facade elements based on the jExcel library.
 * 
 * Make sure to include jExcel in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "paulhodel/jexcel" : "^2.1.0"
 *  }
 * }
 * 
 * ```
 * 
 * @method DataImporter getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JExcelTrait 
{
    
    /**
     * 
     * @return string[]
     */
    protected function buildHtmlHeadTagsForJExcel() : array
    {
        $facade = $this->getFacade();
        return [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEXCEL.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEXCEL.JS_DROPDOWN') . '"></script>',
            '<link href="' . $facade->buildUrlToSource('LIBS.JEXCEL.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.JEXCEL.CSS_DROPDOWN') . '" rel="stylesheet" media="screen">'
        ];
        
    }
    
    
    protected function buildHtmlJExcel() : string
    {
        return <<<JS

<div id="{$this->getId()}"></div>

JS;
    }
      
    /**
     * 
     * @return string
     */
    protected function buildJsJExcelInit() : string
    {
        return <<<JS

    $('#{$this->getId()}').jexcel({
        data: [],
        {$this->buildJsJExcelColumns()}
        {$this->buildJsJExcelMinSpareRows()}
    });

JS;
    }
     
    /**
     * 
     * @return string
     */
    protected function buildJsJExcelColumns() : string
    {
        $columns = [];
        $colHeaders = [];
        $colWidths = [];
        foreach ($this->getWidget()->getColumns() as $col) {
            $colHeaders[] = $col->getDataColumnName();
            $columns[] = $this->buildJsJExcelColumn($col);
            $width = $col->getWidth();
            if ($width->isFacadeSpecific() === true) {
                if (StringDataType::endsWith($width->getValue(), 'px') === true) {
                    $colWidths[] = str_replace('px', '', $width->getValue());
                } else {
                    $colWidths[] = '80';
                }
            } else {
                $colWidths[] = '80';
            }
        }
            
        return "colHeaders: " . json_encode($colHeaders) . ",
        columns: [ " . implode(',', $columns) . " ],
        colWidths: " . json_encode($colWidths) . ",";
    }
    
    /**
     * 
     * @param DataColumn $col
     * @return string
     */
    protected function buildJsJExcelColumn(DataColumn $col) : string
    {
        if ($col->isHidden() === true) {
            return '';
        }
        $cellWidget = $col->getCellWidget();
        switch (true) {
            case ($cellWidget instanceof InputCombo):
                return $this->buildJsJExcelColumnAutocomplete($cellWidget);
        }
        return <<<JS

            { type: 'text' }

JS;
    }
        
    protected function buildJsJExcelColumnAutocomplete(InputSelect $cellWidget) : string
    {
        if ($cellWidget->isBoundToAttribute() === false) {
            throw new FacadeLogicError('TODO');
        }
        
        if ($cellWidget->getLazyLoading() === false) {
            if ($cellWidget->getAttribute()->isRelation()) {
                $rel = $cellWidget->getAttribute()->getRelation();
                
                $srcSheet = DataSheetFactory::createFromObject($rel->getRightObject());
                $srcLabelAttr = $srcSheet->getMetaObject()->getLabelAttribute();
                $srcIdAttr = $srcSheet->getMetaObject()->getUidAttribute();
                $srcLabelCol = $srcSheet->getColumns()->addFromAttribute($srcLabelAttr);
                $srcIdCol = $srcSheet->getColumns()->addFromAttribute($srcIdAttr);
                $srcLabelName = $srcLabelCol->getName();
                $srcIdName = $srcIdCol->getName();
                
                $srcSheet->dataRead();
                $srcData = [];
                foreach ($srcSheet->getRows() as $row) {
                    $srcData[] = ['id' => $row[$srcIdName], 'name' => $row[$srcLabelName]];
                }
                $srcJson = json_encode($srcData);
            } else {
                // TODO non-lazy distinct value list
                $srcJson = '[]';
            }
        } else {
            // TODO lazy loading
            $srcJson = '[]';
        }
        
        
        return <<<JS

            { 
                type: 'autocomplete',
                source: {$srcJson}
            }

JS;
    }
        
    protected function getMinSpareRows() : int
    {
        return 1;
    }
     
    /**
     * 
     * @return string
     */
    protected function buildJsJExcelMinSpareRows() : string
    {
        return 'minSpareRows: ' .  $this->getMinSpareRows() . ',';
    }
        
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $data = "$('#{$this->getId()}').jexcel('getData', false)";
        return <<<JS
        
    {
        oId: '{$this->getWidget()->getMetaObject()->getId()}',
        rows: {$this->buildJsDataFromArray($data)}
    }
    
JS;
    }
        
    protected function buildJsDataFromArray(string $dataJs) : string
    {
        return <<<JS

function() {
    var aDataArray = {$dataJs};
    var aData = [];
    var jExcel = $('#{$this->getId()}');
    aDataArray.forEach(function(aRow, i){
        var oRow = {};
        aRow.forEach(function(val, iColIdx){
            oRow[jExcel.jexcel('getHeader', iColIdx)] = val;
        });
        aData.push(oRow);
    });
    
    for (var i = 0; i < {$this->getMinSpareRows()}; i++) {
        aData.pop();
    }

    return aData;
}()

JS;
    }
    
}