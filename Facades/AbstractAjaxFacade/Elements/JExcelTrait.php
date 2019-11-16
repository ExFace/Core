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
 *  "LIBS.JEXCEL.JS": "npm-asset/jexcel/dist/jexcel.js",
 *  "LIBS.JEXCEL.JS_JSUITES": "npm-asset/jsuites/dist/jsuites.js",
 *  "LIBS.JEXCEL.CSS": "npm-asset/jexcel/dist/jexcel.css",
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
        var jqExcel = {$this->buildJsJqueryElement()};
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
        var jqExcel = {$this->buildJsJqueryElement()};
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
    
    /**
     * Returns the jQuery element for jExcel - e.g. $('#element_id') in most cases.
     * @return string
     */
    protected function buildJsJqueryElement() : string
    {
        return "$('#{$this->getId()}')";
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
        $widget = $this->getWidget();
        $colNamesJson = json_encode($this->makeUniqueColumnNames());
        $allowInsertRow = $this->getAllowAddRows() ? 'true' : 'false';
        $allowDeleteRow = $this->getAllowDeleteRows() ? 'true' : 'false';
        $wordWrap = $widget->getNowrap() ? 'false' : 'true';
        
        return <<<JS

    {$this->buildJsJqueryElement()}
    .data('_exfColumnNames', {$colNamesJson})
    .jexcel({
        data: [ [] ],
        allowRenameColumn: false,
        allowInsertColumn: false,
        allowDeleteColumn: false,
        allowInsertRow: $allowInsertRow,
        allowDeleteRow: $allowDeleteRow,
        wordWrap: $wordWrap,
        {$this->buildJsJExcelColumns()}
        {$this->buildJsJExcelMinSpareRows()}
        onload: function(instance) {
            var jqSelf = {$this->buildJsJqueryElement()};
            {$this->buildJsFixedFootersOnLoad('jqSelf')}
        },
        updateTable: function(instance, cell, col, row, value, label, cellName) {
            {$this->buildJsOnUpdateTableRowColors('row', 'cell')} 
        },
        onchange: function(instance, cell, col, row, value) {
            // setTimeout ensures, the minSpareRows are always added before the spread logic runs
            setTimeout(function(){
                {$this->buildJsFixedFootersSpread()}
            }, 0);
        },
        ondeleterow: function(instance) {
            {$this->buildJsFixedFootersSpread()}
        }
    });
    
    {$this->buildJsFixAutoColumnWidth()}
    {$this->buildJsFixContextMenuPosition()}

JS;
    }
    
    protected function buildJsFixAutoColumnWidth() : string
    {
        return "{$this->buildJsJqueryElement()}.find('colgroup col').attr('width','');";
    }
    
    protected function buildJsFixContextMenuPosition() : string
    {
        // Move contex menu to body to fix positioning errors when there is a parent with position:relative
        return "{$this->buildJsJqueryElement()}.find('.jexcel_contextmenu').detach().addClass('exf-partof-{$this->getId()}').appendTo($('body'));";
    }
          
    /**
     * Returns an array with unique column headers as keys and corresponding data column names as values.
     * 
     * If the table contains multiple columns with equal headings (captions), they will be made unique
     * by adding suffixes. If this happens, the caption of the column widget will be changed, so the suffix
     * becomes visible for all subsequent processes. The data column name remains unchanged!
     * 
     * @return string[]
     */
    protected function makeUniqueColumnNames() : array
    {
        $colNames = [];
        foreach ($this->getWidget()->getColumns() as $col) {
            // Add a hidden indicator to hidden column captions, so they do not interfere
            // with visible columns. This is important because a user would not understand
            // why his column keeps gettin a sequence-number even if there are no visible
            // naming conflicts.
            if ($col->isHidden() === true) {
                $col->setCaption($col->getCaption() . ' (hidden)');
            }
            
            // Now see if there already was a column with the same name as the current one.
            // If so, check if the name with the next sequential number is still vailable
            // and either use that number or perform the check with the next number, and
            // so on.
            $cap = $col->getCaption();
            $capCount = 1;
            while (array_key_exists($cap . ($capCount > 1 ? ' (' . $capCount . ')' : ''), $colNames)) {
                $capCount++;
            }
            if ($capCount > 1) {
                $col->setCaption($col->getCaption() . ' (' . $capCount . ')');
            }
            
            // Build a mapping from caption to data column name, so it can be used in data getters/setters
            // later on.
            $colNames[$col->getCaption()] = $col->getDataColumnName();
        }
        return $colNames;
    }
        
    protected function buildJsOnUpdateTableRowColors(string $rowNrJs, string $cellNodeJs) : string
    {
        if ($this->getWidget()->getStriped() === true) {
            return <<<JS

            // Odd row colours
            if ($rowNrJs % 2) {
                $cellNodeJs.parentNode.classList.add('{$this->buildCssClassForStripedRows()}');
            }

JS;
        }
        return '';
    }
    
    protected function buildCssClassForStripedRows() : string
    {
        return 'datagrid-row-alt';
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

                        var jqSelf = {$this->buildJsJqueryElement()};
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
                $numberType = $cellWidget->getValueDataType();
                if ($numberType->getBase() === 10) {
                    $type = "numeric";
                    $decimal = $numberType->getDecimalSeparator();
                    //$options .= "mask: '{$this->buildMaskNumeric($numberType, $decimal)}',decimal:'{$decimal}',";
                }
                $align = EXF_ALIGN_RIGHT;
                break;
            case $cellWidget instanceof InputCheckBox:
            case $cellWidget instanceof Display && $cellWidget->getValueDataType() instanceof BooleanDataType:
                $type = "checkbox";
                break;
            case $cellWidget instanceof InputSelect:
                $type = 'autocomplete';
                $align = EXF_ALIGN_LEFT;
                $options .= $this->buildJsJExcelColumnDropdownOptions($cellWidget);
                break;
            default:
                $type = "text";
                $align = EXF_ALIGN_LEFT;
        }
        
        if ($col->isEditable() === false) {
            $options .= 'readOnly: true,';
        }
        
        $width = $col->getWidth();
        if ($width->isFacadeSpecific() === true) {
            if (StringDataType::endsWith($width->getValue(), 'px') === true) {
                $widthJs = str_replace('px', '', $width->getValue());
            }
        }
        
        if ($widthJs) {
            $widthJs = "width: {$widthJs},";
        }
        
        $align = $align ? 'align: "' . $align . '",' : '';
        
        return <<<JS

            {
                title: "{$col->getCaption()}",
                type: "{$type}",
                {$widthJs}
                {$align}
                {$options}
            }
JS;
    }
        
    protected function buildJsJExcelColumnDropdownOptions(InputSelect $cellWidget) : string
    {
        if ($cellWidget->isBoundToAttribute() === false) {
            throw new FacadeLogicError('TODO');
        }
        
        if (! ($cellWidget instanceof InputCombo) || $cellWidget->getLazyLoading() === false) {
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
            } else {
                $srcData = [];
                foreach ($cellWidget->getSelectableOptions() as $key => $val) {
                    $srcData[] = ['id' => $key, 'name' => $val];
                }                
            }
            $srcJson = json_encode($srcData);
        } else {
            // TODO lazy loading
            throw new FacadeUnsupportedWidgetPropertyWarning('Lazy loading not yet supported for combo-cells in JExcel');
            
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
        return $this->getAllowAddRows() === true ? 1 : 0;
    }
    
    protected function getAllowAddRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataImporter) || ($widget instanceof DataSpreadSheet && $widget->getAllowToAddRows());
    }
    
    protected function getAllowDeleteRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataImporter) || ($widget instanceof DataSpreadSheet && $widget->getAllowToDeleteRows());
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
        $rows = $this->buildJsConvertArrayToData("{$this->buildJsJqueryElement()}.jexcel('getData', false)");
            
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
        {$this->buildJsJqueryElement()}.jexcel('setData', aData);
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
    var jExcel = {$this->buildJsJqueryElement()};
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
    var jExcel = {$this->buildJsJqueryElement()};
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
        return "{$this->buildJsJqueryElement()}.jexcel('setData', [ [] ])";
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
        return "jexcel.destroy({$this->buildJsJqueryElement()}[0], true); $('.exf-partof-{$this->getId()}').remove();";
    }
    
    protected function buildMaskNumeric(NumberDataType $dataType, string $decimalSeparator = null) : string
    {
        if ($dataType->getPrecisionMax() === 0) {
            return '0';
        }
        
        if ($dataType->getPrecisionMin() === null && $dataType->getPrecisionMax() === null) {
            return '';
        }
        
        if ($decimalSeparator === null) {
            $decimalSeparator = $dataType->getDecimalSeparator();
        }
        
        $format = '#.##' . $decimalSeparator;
        $minPrecision = $dataType->getPrecisionMin();
        $maxPrecision = $dataType->getPrecisionMax();
        for ($i = 1; $i <= $maxPrecision; $i++) {
            $ph = $minPrecision !== null && $i <= $minPrecision ? '0' : '#';
            $format .= $ph;
        }
        
        return $format;
    }
    
    /**
     * Remove 'use strict'; from all JS files loaded via jQuery.ajax because otherwise they
     * won't be able to create global variables, which will prevent many vanilla-js libs
     * from working (e.g. jExcel)
     * 
     * @return string
     */
    protected function buildJsFixJqueryImportUseStrict() : string
    {
        return <<<JS

$.ajaxSetup({
    dataFilter: function(data, type) {
        if (type === 'script') {
        	var regEx = /['"]use strict['"];/;
        	if (regEx.test(data.substring(0, 100)) === true) {
            	data = data.replace(regEx, '');
        	}
        }
        return data;
    }
});

JS;
    }
}