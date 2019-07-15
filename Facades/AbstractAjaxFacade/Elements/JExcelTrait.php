<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\InputSelect;
use exface\Core\Widgets\InputCombo;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\InputHidden;
use exface\Core\Widgets\InputNumber;
use exface\Core\Widgets\InputCheckBox;
use exface\Core\Widgets\Display;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Widgets\DataSpreadSheet;
use exface\Core\Widgets\Data;
use exface\Core\Widgets\DataImporter;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataSpreadSheetFooter;

/**
 * Common methods for facade elements based on the jExcel library.
 * 
 * Make sure to include jExcel in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "npm-asset/jexcel" : "^3.2.0"
 *  }
 * }
 * 
 * ```
 * 
 * If your facade is based on the `AbstractAjaxFacade`, add these configuration options
 * to the facade config file. Make sure, each config option points to an existing
 * inlcude file!
 * 
 * ```
 *  "LIBS.JEXCEL.JS": "npm-asset/jexcel/dist/jexcel.min.js",
 *  "LIBS.JEXCEL.JS_JSUITES": "npm-asset/jsuites/dist/jsuites.js",
 *  "LIBS.JEXCEL.CSS": "npm-asset/jexcel/dist/jexcel.min.css",
 *	"LIBS.JEXCEL.CSS_JSUITES": "npm-asset/jsuites/dist/jsuites.css",
 *	
 * ```
 * 
 * @method Data getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JExcelTrait 
{
    protected function registerReferencesAtLinkedElements()
    {
        $widget = $this->getWidget();
        
        if ($widget instanceof DataSpreadSheet) {
            $this->registerReferencesAtLinkedElementsForSpreadSheet($widget);
        }
    }
    
    protected function registerReferencesAtLinkedElementsForSpreadSheet(DataSpreadSheet $widget)
    {
        // Add live refs links for default_row
        if ($widget->hasDefaultRow() === true) {
            foreach ($widget->getDefaultRow() as $columnName => $expr) {
                if ($expr->isReference() === true) {
                    $link = $expr->getWidgetLink($this->getWidget());
                    $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
                    // TODO this does not work if another row was accidentally added. It would
                    // be better if we mark the default_row somehow.
                    if ($linked_element) {
                        $script = <<<JS
                        
    !function(){
        var jqExcel = $('#{$this->getId()}');
        var aData = jqExcel.jexcel('getData');
        if (aData.length > {$this->getMinSpareRows()}) {
            return;
        }
        
        var oFirstRow = {$this->buildJsDataGetter()}.rows[0] || {};
        oFirstRow["{$columnName}"] = {$linked_element->buildJsValueGetter($link->getTargetColumnId())};
        {$this->buildJsDataSetter('{rows: [oFirstRow]}')};
        {$this->buildJsFixedFootersSpread()}
    }();
    
JS;
                        $linked_element->addOnChangeScript($script);
                    }
                } else {
                    // TODO add support for other attributes and static values in default_row.
                    // Probably need to mark the row as default_row, so that linked values from above
                    // can be set even if the first row exists.
                    throw new FacadeUnsupportedWidgetPropertyWarning('Cannot use "' . $expr->toString() . '" in default_row property of ' . $this->getWidget()->getWidgetType() . ': only widget links currently supported!');
                }
            }
        }
        
        // add live refs for fixed footer values
        foreach ($widget->getColumns() as $col) {
            if ($col->hasFooter() === true) {
                $footer = $col->getFooter();
                if ($footer->hasFixedValue() === true) {
                    $expr = $footer->getFixedValue();
                    if ($expr->isReference()) {
                        $link = $expr->getWidgetLink($col);
                        $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
                        // FIXME if multiple footers have live refs, each one will remove the value
                        // of the previous one. Need a better footer-setter here! Maybe something like
                        // in buildJsFixedFootersOnLoad()
                        if ($linked_element) {
                            $script = <<<JS
                            
    !function(){
        var jqExcel = $('#{$this->getId()}');
        var oData = {
            footer: [
                {
                    {$col->getDataColumnName()}: {$linked_element->buildJsValueGetter($link->getTargetColumnId())}
                }
            ]
        }
        {$this->buildJsFooterRefresh('oData', 'jqExcel')}
        {$this->buildJsFixedFootersSpread()}
    }();
    
JS;
                            $linked_element->addOnChangeScript($script);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 
     * @return string[]
     */
    protected function buildHtmlHeadTagsForJExcel() : array
    {
        $facade = $this->getFacade();
        return [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEXCEL.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEXCEL.JS_JSUITES') . '"></script>',
            '<link href="' . $facade->buildUrlToSource('LIBS.JEXCEL.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.JEXCEL.CSS_JSUITES') . '" rel="stylesheet" media="screen">'
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
        $colNames = [];
        foreach ($this->getWidget()->getColumns() as $col) {
            $colNames[$col->getCaption()] = $col->getDataColumnName();
        }
        $colNamesJson = json_encode($colNames);
        
        return <<<JS

    $('#{$this->getId()}')
    .data('_exfColumnNames', {$colNamesJson})
    .jexcel({
        data: [ [] ],
        allowRenameColumn: false,
        allowInsertColumn: false,
        allowDeleteColumn: false,
        {$this->buildJsJExcelColumns()}
        {$this->buildJsJExcelMinSpareRows()}
        onload: function(instance) {
            var jqSelf = $('#{$this->getId()}');
            {$this->buildJsFixedFootersOnLoad('jqSelf')}
        },
        updateTable: function(instance, cell, col, row, value, label, cellName) {
            {$this->buildJsOnUpdateTableRowColors()} 
        },
        onchange: function(instance, cell, col, row, value) {
            {$this->buildJsFixedFootersSpread()}
        },
        ondeleterow: function(instance) {
            {$this->buildJsFixedFootersSpread()}
        }
    });
    // Move contex menu to body to fix positioning errors when there is a parent with position:relative
    $('#{$this->getId()} .jexcel_contextmenu').detach().addClass('exf-partof-{$this->getId()}').appendTo($('body'));

JS;
    }
        
    protected function buildJsOnUpdateTableRowColors() : string
    {
        // TODO striped?
        return '';
    }
    
    protected function buildJsFixedFootersOnLoad(string $jqSelfJs) : string
    {
        $js = '';
        if ($this->hasFooter() === true) {
            $js = <<<JS

            var jqFooter = {$this->buildJsFooterGetter($jqSelfJs)};
JS;
            foreach ($this->getWidget()->getColumns() as $colIdx => $col) {
                if ($col->hasFooter() === false) {
                    continue;
                }
                
                $footer = $col->getFooter();
                if ($footer->hasFixedValue() === false) {
                    continue;
                }
                
                $expr = $footer->getFixedValue();
                    if ($expr->isReference()) {
                        $link = $expr->getWidgetLink($col);
                        $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
                        if ($linked_element) {
                            $js .= <<<JS
                    
             jqFooter.find('td[data-x="{$colIdx}"]').text({$linked_element->buildJsValueGetter($link->getTargetColumnId())});
    
JS;
                    }
                }
            }
        }
        return $js;
    }
    
    protected function buildJsFixedFootersSpreadFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'fixedFootersSpread';
    }
    
    protected function buildJsFixedFootersSpread() : string
    {
        return $this->buildJsFixedFootersSpreadFunctionName() . '()';
    }
    
    protected function buildJsFixedFootersSpreadFunction() : string
    {
        return 'function ' . $this->buildJsFixedFootersSpreadFunctionName() . '() {' . $this->buildJsFixedFootersSpreadFunctionBody() . '}';
    }
    
    protected function buildJsFixedFootersSpreadFunctionBody() : string
    {
        $js = '';
        foreach ($this->getWidget()->getColumns() as $idx => $col) {
            if ($col->hasFooter() === false) {
                continue;
            }
            
            $footer = $col->getFooter();
            if ($footer->hasFixedValue() === false) {
                continue;
            }
            
            if ($col->getDataType() instanceof NumberDataType) {
                $precision = $col->getDataType()->getPrecisionMax();
                if ($precision !== null) {
                    $toFixedJs = '.toFixed(' . $precision . ')';
                }
            }
            
            switch ($footer->getFixedValueSpread()) {
                case DataSpreadSheetFooter::SPREAD_TO_FIRST_ONLY:
                    $spreadJS = <<<JS

                        if (fDif != 0) {
                            var sFirstVal = Number(aData[0][$idx]);
                            if (isNaN(sFirstVal)) {
                                sFirstVal = 0;
                            }
                            aData[0][$idx] = (sFirstVal + fDif){$toFixedJs};
                            jqSelf.jexcel('setData', aData);
                        }

JS;
                    break;
                default:
                    throw new FacadeUnsupportedWidgetPropertyWarning('Spreading fixed totals via "' . $footer->getFixedValueSpread() . '" not yet supported!');
            }
            
            // IDEA this will probably not work if we allow column dragging because $idx has only the initial state...
            $js .= <<<JS
                        
                        var fTotal = Number({$this->buildJsFooterValueGetterByColumnIndex('jqSelf', $idx)});
                        var fSum = 0;
                        var fDif = 0;
                        aData.forEach(function(aRow) {
                            fSum += aRow[$idx] == 'NaN' ? 0 : Number(aRow[$idx]);
                        });
                        fDif = fTotal - fSum;
                        $spreadJS

JS;
        }
        return <<<JS

                        var jqSelf = $('#{$this->getId()}');
                        var aData = jqSelf.jexcel('getData');

                        if (aData.length <= {$this->getMinSpareRows()}) return;
                        
                        $js

JS;
    }
        
    protected function buildJsFooterValueGetterByColumnIndex(string $jqSelfJs, string $idxJs) : string
    {
        return $jqSelfJs . ".find('thead.footer td[data-x=\"' + {$idxJs} + '\"]').text()";
    }
    
    protected function buildJsFooterRefresh(string $dataJs, string $jqSelfJs) : string
    {
        if ($this->hasFooter() === false) {
            return '';
        }
        return <<<JS

            !function(){
                if ($dataJs.footer === undefined || Array.isArray($dataJs.footer) === false || $dataJs.footer.length === 0) return;
                var aFooterRows = {$this->buildJsConvertDataToArray($dataJs . '.footer')};
                var jqFooter = {$this->buildJsFooterGetter($jqSelfJs)};
                aFooterRows[0].forEach(function(val, iColIdx) {
                    if (val !== undefined && val !== null) {
                        jqFooter.find('td[data-x="' + iColIdx + '"]').text(val);
                    }
                });
            }()

JS;
    }
        
    protected function buildJsFooterGetter(string $jqSelfJs) : string
    {
        return <<<JS

            function(){
                var jqFooter = {$jqSelfJs}.find('table.jexcel thead.footer').first();
                if (jqFooter.length === 0) {
                    jqFooter = {$jqSelfJs}.find('table.jexcel thead').first().clone();
                    jqFooter.addClass('footer');
                    jqFooter.find('td').empty();
                    jqFooter.find('td:first-of-type').html('&nbsp;');
                    $jqSelfJs.find('table.jexcel').append(jqFooter);
                }
                return jqFooter;
            }()

JS;
    }
     
    /**
     * 
     * @return string
     */
    protected function buildJsJExcelColumns() : string
    {
        $columns = [];
        foreach ($this->getWidget()->getColumns() as $col) {
            $columns[] = $this->buildJsJExcelColumn($col);
        }
            
        return "
        columns: [ " . implode(',', $columns) . " ],";
    }
    
    /**
     * 
     * @param DataColumn $col
     * @return string
     */
    protected function buildJsJExcelColumn(DataColumn $col) : string
    {
        $cellWidget = $col->getCellWidget();
        $options = '';
        switch (true) {
            case $col->isHidden() === true:
            case $cellWidget instanceof InputHidden:
                $type = "hidden";
                break;
            case $cellWidget instanceof InputNumber:
            case $cellWidget instanceof Display && $cellWidget->getValueDataType() instanceof NumberDataType:
                $type = "numeric";
                $align = EXF_ALIGN_RIGHT;
                break;
            case $cellWidget instanceof InputCheckBox:
            case $cellWidget instanceof Display && $cellWidget->getValueDataType() instanceof BooleanDataType:
                $type = "checkbox";
                break;
            case $cellWidget instanceof InputCombo:
                $type = 'autocomplete';
                $align = EXF_ALIGN_LEFT;
                $options .= $this->buildJsJExcelColumnDropdownOptions($cellWidget);
                break;
            default:
                $type = "text";
        }
        
        if ($col->isEditable() === false) {
            $options .= 'readOnly: true,';
        }
        
        $width = $col->getWidth();
        if ($width->isFacadeSpecific() === true) {
            if (StringDataType::endsWith($width->getValue(), 'px') === true) {
                $widthJs = str_replace('px', '', $width->getValue());
            } else {
                $widthJs = '100';
            }
        } else {
            $widthJs= '100';
        }
        
        if ($widthJs) {
            $js .= "width: {$widthJs},";
        }
        
        $align = $align ? 'align: "' . $align . '",' : '';
        
        return <<<JS

            {
                title: "{$col->getCaption()}",
                type: "{$type}",
                width: {$widthJs},
                {$align}
                {$options}
            }
JS;
        
        return '{' . rtrim($js, ","). '}';
    }
        
    protected function buildJsJExcelColumnDropdownOptions(InputSelect $cellWidget) : string
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
        
        return "source: {$srcJson},";
    }
    
    protected function hasFooter() : bool
    {
        foreach ($this->getWidget()->getColumns() as $col) {
            if ($col->hasFooter() === true) {
                return true;
            }
        }
        return false;
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
        
    /**
     *
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        $rows = $this->buildJsConvertArrayToData("$('#{$this->getId()}').jexcel('getData', false)");
            
        if ($widget->isEditable() && $action && ! $action->getMetaObject()->is($widget->getMetaObject()) === true) {
            // If the data is intended for another object, make it a nested data sheet
            if ($relPath = $widget->getObjectRelationPathFromParent()) {
                $relAlias = $relPath->toString();
            } else {
                if ($relPath = $widget->getObjectRelationPathToParent()) {
                    $relAlias = $relPath->reverse()->toString();
                } else {
                    $relation = $action->getMetaObject()->findRelation($widget->getMetaObject(), true);
                    $relAlias = $relation->getAlias();
                }
            }
            $data = <<<JS
    {
        oId: '{$action->getMetaObject()->getId()}',
        rows: [
            {
                '{$relAlias}': {
                    oId: '{$widget->getMetaObject()->getId()}',
                    rows: {$rows}
                }
            }
        ]
    }
    
JS;
        } else {
        
            $data = <<<JS
    {
        oId: '{$this->getWidget()->getMetaObject()->getId()}',
        rows: {$rows}
    }
    
JS;
        }
            
        return "function(){ {$this->buildJsFixedFootersSpread()}; return {$data} }()";
    }
     
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsDataSetter()
     */
    public function buildJsDataSetter(string $jsData) : string
    {
        // The '!' in front of the IFFE is required because it would not get executed stand alone
        // resulting in a "SyntaxError: Function statements require a function name" instead.
        return <<<JS
!function() {    
    var oData = {$jsData};    
    var aData = [];
    if (oData !== undefined && Array.isArray(oData.rows)) {
        aData = {$this->buildJsConvertDataToArray('oData.rows')}
    }
    if (aData.length > 0) {
        $('#{$this->getId()}').jexcel('setData', aData);
    }
}()

JS;
    }
        
    protected function buildJsConvertArrayToData(string $arrayOfArraysJs) : string
    {
        return <<<JS
function() {
    var aDataArray = {$arrayOfArraysJs};
    var aData = [];
    var jExcel = $('#{$this->getId()}');
    var oColNames = jExcel.data('_exfColumnNames');
    aDataArray.forEach(function(aRow, i){
        var oRow = {};
        var sHeaderName;
        var sColName;
        aRow.forEach(function(val, iColIdx){
            try {
                sHeaderName = jExcel.jexcel('getHeader', iColIdx);
            } catch (e) {
                sHeaderName = '';
            }
            sColName = oColNames[sHeaderName];
            if (sColName) {
                oRow[sColName] = val;
            }
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
    
    protected function buildJsConvertDataToArray(string $arrayOfObjectsJs) : string
    {
        return <<<JS
        
function() {
    var aDataRows = {$arrayOfObjectsJs};
    var aData = [];
    var jExcel = $('#{$this->getId()}');
    var oColNames = jExcel.data('_exfColumnNames');
    var aColHeaders = jExcel.jexcel('getHeaders').split(',');
    var oColIdxCache = {};
    aDataRows.forEach(function(oRow, i){
        var oRowIndexed = {};
        var aRow = [];
        var sHeaderName, iColIdx, iLastIdx;
        for (var sColName in oRow) {
            iColIdx = oColIdxCache[sColName];
            if (iColIdx !== undefined) {
                oRowIndexed[iColIdx] = oRow[sColName];
            }

            sHeaderName = Object.keys(oColNames).find(key => oColNames[key] === sColName);
            if (! sHeaderName) continue;
            iColIdx = aColHeaders.indexOf(sHeaderName);
            if (iColIdx >= 0) {
                oRowIndexed[iColIdx] = oRow[sColName];
                oColIdxCache[sColName] = iColIdx;
            }
        }
        
        iLastIdx = -1;
        Object.keys(oRowIndexed).sort(function(a, b){return a-b}).forEach(function(iIdx) {
            while (iIdx > iLastIdx + 1) {
                aRow.push(null);
                iLastIdx++;
            }
            aRow.push(oRowIndexed[iIdx]);
            iLastIdx++;
        });
        
        aData.push(aRow);
    });
    
    return aData;
}()

JS;
    }
    
    
    public function buildJsDataResetter() : string
    {
        return "$('#{$this->getId()}').jexcel('setData', [ [] ])";
    }
    
    protected function buildJsFunctionsForJExcel() : string
    {
        return <<<JS

    {$this->buildJsFixedFootersSpreadFunction()}

JS;
    }
    
    /**
     * 
     * @see AbstractJqueryElement::buildJsDestroy()
     */
    public function buildJsDestroy() : string
    {
        return "jexcel.destroy(document.getElementById('{$this->getId()}'), true); $('.exf-partof-{$this->getId()}').remove();";
    }
}