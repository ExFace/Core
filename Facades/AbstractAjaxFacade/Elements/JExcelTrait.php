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
use exface\Core\Widgets\DataImporter;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Widgets\Parts\DataSpreadSheetFooter;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Widgets\InputComboTable;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\Input;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsNumberFormatter;
use exface\Core\Widgets\InputText;
use exface\Core\Widgets\Text;

/**
 * Common methods for facade elements based on the jExcel library.
 * 
 * Make sure to include jExcel in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "npm-asset/jspreadsheet-ce" : "^4.10",
		"npm-asset/jspreadsheet--autowidth" : "^2"
 *  }
 * }
 * 
 * ```
 * 
 * If your facade is based on the `AbstractAjaxFacade`, add these configuration options
 * to the facade config file. Make sure, each config option points to an existing
 * include file!
 * 
 * ```
 *  "LIBS.JEXCEL.JS": "npm-asset/jspreadsheet-ce/dist/index.js",
 *  "LIBS.JEXCEL.JS_JSUITES": "npm-asset/jsuites/dist/jsuites.js",
 *  "LIBS.JEXCEL.CSS": "npm-asset/jspreadsheet-ce/dist/jspreadsheet.css",
 *	"LIBS.JEXCEL.CSS_JSUITES": "npm-asset/jsuites/dist/jsuites.css"
 *  "LIBS.JEXCEL.PLUGINS": {
 *		"jss_autoWidth": "npm-asset/jspreadsheet--autowidth/plugins/dist/autoWidth.min.js"
 *	},
 * ```
 * 
 * NOTE: This trait requires the exfTools JS library to be available!
 * 
 * @method Data getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JExcelTrait 
{
    use JsConditionalPropertyTrait;
    
    /**
     * @return void
     */
    protected function registerReferencesAtLinkedElements()
    {
        $widget = $this->getWidget();
        
        if ($widget instanceof DataSpreadSheet) {
            $this->registerReferencesAtLinkedElementsForSpreadSheet($widget);
        }
    }
    
    /**
     * 
     * @param DataSpreadSheet $widget
     * @throws FacadeUnsupportedWidgetPropertyWarning
     */
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
        $includes = [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEXCEL.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JEXCEL.JS_JSUITES') . '"></script>',
            '<link href="' . $facade->buildUrlToSource('LIBS.JEXCEL.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.JEXCEL.CSS_JSUITES') . '" rel="stylesheet" media="screen">'
        ];
        if ($facade->getConfig()->hasOption('LIBS.JEXCEL.PLUGINS')) {
            foreach ($facade->getConfig()->getOption('LIBS.JEXCEL.PLUGINS') as $path) {
                $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToVendorFile($path) . '"></script>';
            }
        }
        return $includes;
    }
    
    /**
     * Returns the jQuery element for jExcel - e.g. $('#element_id') in most cases.
     * @return string
     */
    protected function buildJsJqueryElement() : string
    {
        return "$('#{$this->getId()}')";
    }
    
    /**
     * 
     * @return string
     */
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
        $allowDragRow = $this->getAllowToDragRows() ? 'true' : 'false';
        $allowDeleteRow = $this->getAllowDeleteRows() ? 'true' : 'false';
        $allowEmptyRows = $this->getAllowEmptyRows() ? 'true' : 'false';
        $wordWrap = $widget->getNowrap() ? 'false' : 'true';
        
        /* @var $col \exface\Core\Widgets\DataColumn */
        foreach ($widget->getColumns() as $colIdx => $col) {
            // If the values were formatted according to their data types in buildJsConvertDataToArray()
            // parse them back here
            if ($this->needsDataFormatting($col)) {
                $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
                $cellWidget = $col->getCellWidget();
                if (($cellWidget instanceof InputNumber) && ($formatter instanceof JsNumberFormatter)) {
                    $formatter->setDecimalSeparator($cellWidget->getDecimalSeparator());
                    $formatter->setThousandsSeparator($cellWidget->getThousandsSeparator());
                }
                $parserJs = 'function(value){ return ' . $formatter->buildJsFormatParser('value') . '}';
                // For those cells, that do not have a specific editor, use the data type formatter
                // to format the values before showing them and parse them back in buildJsConvertArrayToData()
                $formatterJs = 'function(value){ return ' . $formatter->buildJsFormatter('value') . '}';
            } else {
                $parserJs = 'function(value){ return value; }';
                $formatterJs = 'function(value){ return value; }';
            }
            
            $validatorJs = '';
            if (! $col->isHidden() && $col->isEditable()) {
                $cellEl = $this->getFacade()->getElement($col->getCellWidget());
                if ($cellEl->getWidget() instanceof Input) {
                    $validatorJs = 'function(value){ return ' . $cellEl->buildJsValidator('value') . ' ? true : ' . json_encode($cellEl->getValidationErrorText()) . ' }';
                }
            }
            if (! $validatorJs) {
                $validatorJs = 'function(value){return true}';
            }
            
            $hiddenFlagJs = $col->isHidden() ? 'true' : 'false';
            $systemFlagJs = $col->isBoundToAttribute() && $col->getAttribute()->isSystem() ? 'true' : 'false';
            
            $conditionsJs = '';
            if ($condProp = $col->getDisabledIf()) {
                $conditionsJs .= $this->buildJsConditionalProperty(
                    $condProp, 
                    "aCells.forEach(function(domCell, iRowIdx){
                        if (oWidget.hasChanged(iColIdx, iRowIdx)) {
                            oWidget.restoreInitValue(iColIdx, iRowIdx); 
                        }
                        domCell.classList.add('readonly');
                    });", 
                    "aCells.forEach(function(domCell){domCell.classList.remove('readonly')});"
                );
            }
            if ($conditionsJs) {
                $conditionsJs = <<<JS

                        var iColIdx = {$colIdx};
                        var oJExcel = oWidget.getJExcel();
                        var aCells = [];
                        oJExcel.getColumnData(iColIdx).forEach(function(mVal, iRowIdx){
                            aCells.push(oJExcel.getCell(jexcel.getColumnName(iColIdx) + (iRowIdx + 1)));
                        });
                        $conditionsJs
JS;
            }
            
            $columnsJson .= <<<JS
                "{$col->getDataColumnName()}": {
                    dataColumnName: "{$col->getDataColumnName()}",
                    caption: {$this->escapeString($col->getCaption(), true, false)},
                    tooltip: {$this->escapeString($col->getHint() ?? '', true, false)},
                    parser: {$parserJs},
                    formatter: {$formatterJs},
                    validator: {$validatorJs},
                    hidden: {$hiddenFlagJs},
                    system: {$systemFlagJs},
                    conditionize: function(oWidget){
                        $conditionsJs
                    }
                }, 

JS;           
        }
        $columnsJson = '{' . $columnsJson . '}';
        
        $rowNumberColName = ($widget instanceof DataSpreadSheet) && $widget->hasRowNumberAttribute() ? "'{$widget->getRowNumberColumn()->getDataColumnName()}'" : 'null'; 
        
        return <<<JS

    {$this->buildJsJqueryElement()}
    {$this->buildJsResetSelection('')}
    .jspreadsheet({
        data: [ [] ],
        columnSorting:false,
        allowRenameColumn: false,
        allowInsertColumn: false,
        allowDeleteColumn: false,
        allowInsertRow: $allowInsertRow,
        rowDrag: $allowDragRow,
        allowDeleteRow: $allowDeleteRow,
        wordWrap: $wordWrap,
        {$this->buildJsJExcelColumns()}
        {$this->buildJsJExcelMinSpareRows()}
        onload: function(instance) {
            var jqSelf = {$this->buildJsJqueryElement()};
            {$this->buildJsFixedFootersOnLoad('jqSelf')}
            
            try {
                if (instance.exfWidget !== undefined) {
                    jqSelf.find('thead > tr > td').each(function(iIdx, oTD) {
                        var iX = $(oTD).data('x');
                        if (iX !== undefined) {
                            oTD.title = instance.exfWidget.getColumnModel(iX).tooltip;
                        }
                    });
                }
            } catch (e) {
                console.warn('Cannot set tooltips for columns:', e);
            }
        },
        updateTable: function(instance, cell, col, row, value, label, cellName) {
            {$this->buildJsOnUpdateTableRowColors('row', 'cell')} 
        },
        onbeforechange: function(instance, cell, x, y, value) {
            var fnParser = instance.exfWidget.getColumnModel(x).parser;
            var fnFormatter = instance.exfWidget.getColumnModel(x).formatter;
            var mValueParsed, mValidated;

            if (value === undefined) {
                return;
            }

            mValueParsed = fnParser ? fnParser(value) : value;
            if ((mValueParsed === '' || mValueParsed === null) && mValueParsed !== value) {
                mValidated = instance.exfWidget.validateCell(cell, x, y, value);
            } else {
                mValidated = instance.exfWidget.validateCell(cell, x, y, mValueParsed);
            }
            
            if (mValueParsed === mValidated && fnFormatter) {
                mValidated = fnFormatter(mValueParsed);
            }

            return mValidated;
        },
        onchange: function(instance, cell, col, row, value, oldValue) {
            // setTimeout ensures, the minSpareRows are always added before the spread logic runs
            {$this->buildJsOnUpdateApplyValuesFromWidgetLinks('instance', 'col', 'row')};
            setTimeout(function(){
                {$this->buildJsFixedFootersSpread()}
            }, 0);
        },
        oninsertrow: function(el, rowNumber, numOfRows, rowTDs, insertBefore) {
            
        },
        ondeleterow: function(el, rowNumber, numOfRows, rowDOMElements, rowData, cellAttributes) {
            {$this->buildJsFixedFootersSpread()}
        },
        onselection: function(el, x1, y1, x2, y2, origin) {
            $(el).data('_exfSelection', {
                x1: x1,
                y1: y1,
                x2: x2,
                y2: y2
            });
        },
        onblur: function(el) {
            var oSel = $(el).data('_exfSelection');
            if (oSel.x1 !== null) {
                $(el).jexcel('updateSelectionFromCoords', oSel.x1, oSel.y1, oSel.x2, oSel.y2);
            }
        },
        onundo: function(el, historyRecord) {
            el.exfWidget.validateAll();
        },
        onredo: function(el, historyRecord) {
            el.exfWidget.validateAll();
        },
        onevent: function(event) {
            ({$this->buildJsJqueryElement()}[0].jssPlugins || []).forEach(function(oPlugin) {
                oPlugin.onevent(event);
            });
        }
    });

    {$this->buildJsJqueryElement()}[0].exfWidget = {
        _dom: {$this->buildJsJqueryElement()}[0],
        _colNames: {$colNamesJson},
        _cols: {$columnsJson},
        _rowNumberColName: $rowNumberColName,
        _initData: [],
        getJExcel: function(){
            return this._dom.jexcel;
        },
        getDom: function(){
            return this._dom;
        },
        getDataLastLoaded: function(){
            return this._initData;
        },
        getColumnName: function(iColIdx) {
            return this._colNames[this.getJExcel().getHeader(iColIdx)];
        },
        getColumnModel: function(iColIdx) {
            return (this._cols[this.getColumnName(iColIdx)] || {});
        },
        getInitValue: function(iCol, iRow) {
            return (this.getDataLastLoaded()[iRow] || {})[this.getColumnName(iCol)];
        },
        restoreInitValue: function(iCol, iRow) {
            var mInitVal = this.getInitValue(iCol, iRow);
            if (mInitVal === undefined) {
                mInitVal = '';
            }
            this.getJExcel().setValueFromCoords(iCol, iRow, mInitVal);            
        },
        hasChanged: function(iCol, iRow, mValue){
            var mInitVal = this.getInitValue(iCol, iRow);
            var oCol = this.getColumnModel(iCol);
            
            mValue = mValue === undefined ? this.getJExcel().getValueFromCoords(iCol, iRow) : mValue;
            mValue = oCol.parser ? oCol.parser(mValue) : mValue;
            if (mValue === undefined || mValue === null) {
                mValue = '';
            }
            if (mInitVal === undefined || mInitVal === null) {
                mInitVal = '';
            } else {
                //mInitVal = oCol.formatter ? oCol.formatter(mInitVal) : mInitVal;
            }

            // Checkboxes cannot distinguish `false` and `null` or empty. Catch that here 
            if ((this.getJExcel().getConfig().columns[iCol] || {}).type === 'checkbox') {
                if (mValue === false && (mInitVal === null || mInitVal === '' || mInitVal === undefined) && mInitVal !== true && mInitVal !== 1) {
                    return false;
                }
            }

            return mInitVal.toString() != mValue.toString();
        },
        hasChanges: function() {
            var aData = this.getJExcel().getData() || [];
            var bChanged = false;
            var oWidget = this;
            var oRow;
            for (var iRowIdx = 0; iRowIdx < aData.length - {$this->getMinSpareRows()}; iRowIdx++) {
                oRow = aData[iRowIdx];
                for (var iColIdx = 0; iColIdx < oRow.length; iColIdx++) {
                    bChanged = oWidget.hasChanged(iColIdx, iRowIdx, oRow[iColIdx]);
                    if (bChanged) {
                        break;
                    }
                }
                if (bChanged) {
                    break;
                }
            }
            return bChanged;
        },
        validateValue: function(iCol, iRow, mValue) {
            var fnValidator = this.getColumnModel(iCol).validator;
            if (fnValidator === null || fnValidator === undefined) {
                return true;
            }
            return fnValidator(mValue);
        },
        validateCell: function (cell, iCol, iRow, mValue, bParseValue) {
            var mValidationResult;
            var oCol;
            bParseValue = bParseValue === undefined ? false : true;
            if (bParseValue === true) {
                oCol = this.getColumnModel(iCol);
                mValue = oCol.parser ? oCol.parser(mValue) : mValue;
            }
            mValidationResult = this.validateValue(iCol, iRow, mValue);

            if (this.hasChanged(iCol, iRow, mValue)) {
                $(cell).addClass('exf-spreadsheet-change');
            } else {
                $(cell).removeClass('exf-spreadsheet-change');
                mValue = this.getInitValue(iCol, iRow);
            }

            if (mValidationResult === true) {
                $(cell).removeClass('exf-spreadsheet-invalid');
                cell.title = '';
            } else {
                $(cell).addClass('exf-spreadsheet-invalid');
                cell.title = (mValidationResult || '');
            }

            return mValue;
        },
        validateAll: function() {
            var aData = this.getJExcel().getData() || [];
            var iDataCnt = aData.length;
            var iSpareRows = {$this->getMinSpareRows()};
            var oWidget = this;
            
            aData.forEach(function(aRow, iRowIdx) {
                var bRowEmpty = true;
                var aCells = [];
                // Spare rows cannot be invalid
                if (iRowIdx >= iDataCnt - iSpareRows) {
                    return;
                }
                
                aRow.forEach(function(mValue, iColIdx) {
                    var mValidated;                    
                    var oCell = oWidget.getJExcel().getCell(jexcel.getColumnName(iColIdx) + (iRowIdx + 1));
                    aCells.push(oCell);
                    mValidated = oWidget.validateCell(oCell, iColIdx, iRowIdx, mValue, true);
                    if (mValidated !== '' && mValidated !== null && mValidated !== undefined) {
                        bRowEmpty = false;
                    }                   
                });
                if (bRowEmpty === true) {
                    aCells.forEach(function(oCell) {
                        $(oCell).removeClass('exf-spreadsheet-invalid');
                    });
                }
            });
        },
        refreshConditionalProperties: function() {
            for (i in this._cols) {
                this._cols[i].conditionize(this);
            }
        },
        convertArrayToData: function(aDataArray) {
            var aData = [];
            var iDataCnt = aDataArray.length;
            var jExcel = $(this._dom);
            var oColNames = this._colNames;
            var oWidget = this;
            var bAllowEmptyRows = {$allowEmptyRows};
            aDataArray.forEach(function(aRow, i){
                var oRow = {};
                var bEmptyRow = true;

                if (i >= (iDataCnt - {$this->getMinSpareRows()})) {
                    return;
                }

                aRow.forEach(function(val, iColIdx){
                    var oCol = oWidget.getColumnModel(iColIdx);
                    var sColName = oWidget.getColumnName(iColIdx);
                    if (sColName) {
                        val = oCol.parser ? oCol.parser(val) : val;
                        if (val !== undefined && val !== '' && val !== null && oCol.hidden === false) {
                            bEmptyRow = false;
                        }
                        oRow[sColName] = val;
                    }
                });

                if (bEmptyRow === false || bAllowEmptyRows === true) {
                    if (oWidget._rowNumberColName !== null) {
                        oRow[oWidget._rowNumberColName] = (i+1);
                    }
                    aData.push(oRow);
                }
            });

            return aData;
        },
        convertDataToArray: function(aDataRows) {
            var aData = [];
            var jExcel = $(this._dom);
            var oWidget = this;
            var oColNames = this._colNames;
            var aColHeaders = jExcel.jexcel('getHeaders').split(',');
            var oColIdxCache = {};
            aDataRows.forEach(function(oRow, i){
                var oRowIndexed = {};
                var aRow = [];
                var sHeaderName, iColIdx, iLastIdx;
                for (var sColName in oRow) {
                    if (oColIdxCache[sColName] !== undefined) {
                        oColIdxCache[sColName].forEach(function(iColIdx) {
                            oRowIndexed[iColIdx] = oRow[sColName];
                        });
                    }
        
                    Object.keys(oColNames)
                    .filter(key => oColNames[key] === sColName)
                    .forEach(function(sHeaderName) {
                        var fnFormatter;
                        if (! sHeaderName) return;
                        iColIdx = aColHeaders.indexOf(sHeaderName);
                        if (iColIdx >= 0) {
                            fnFormatter = oWidget.getColumnModel(iColIdx).formatter;
                            oRowIndexed[iColIdx] = fnFormatter ? fnFormatter(oRow[sColName]) : oRow[sColName];
                            oColIdxCache[sColName] = [...(oColIdxCache[sColName] || []), ...[iColIdx]];
                        }
                    });
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
        }
    };
    
    {$this->buildJsInitPlugins()}
    {$this->buildJsFixContextMenuPosition()}

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsInitPlugins() : string
    {
        $pluginsJs = '';
        $cfg = $this->getFacade()->getConfig();
        if ($cfg->hasOption('LIBS.JEXCEL.PLUGINS')) {
            foreach ($cfg->getOption('LIBS.JEXCEL.PLUGINS') as $var => $path) {
                $pluginsJs = "{$var}({$this->buildJsJqueryElement()}[0].exfWidget.getJExcel())";
            }
        }
        return <<<JS
        
        {$this->buildJsJqueryElement()}[0].jssPlugins = [
            $pluginsJs
        ];
JS;
    }
    
    /**
     * 
     * @return string
     */
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
            if ($col->isEditable() && $col->getCellWidget()->isRequired()) {
                $col->setCaption($col->getCaption() . ' *');
            }
            
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
        
    /**
     * 
     * @param string $rowNrJs
     * @param string $cellNodeJs
     * @return string
     */
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
    
    /**
     * 
     * @return string
     */
    protected function buildCssClassForStripedRows() : string
    {
        return 'datagrid-row-alt';
    }
    
    /**
     * 
     * @param string $jqSelfJs
     * @return string
     */
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
    
    /**
     * 
     * @return string
     */
    protected function buildJsFixedFootersSpreadFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'fixedFootersSpread';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsFixedFootersSpread() : string
    {
        return $this->buildJsFixedFootersSpreadFunctionName() . '()';
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsFixedFootersSpreadFunction() : string
    {
        return 'function ' . $this->buildJsFixedFootersSpreadFunctionName() . '() {' . $this->buildJsFixedFootersSpreadFunctionBody() . '}';
    }
    
    /**
     * 
     * @throws FacadeUnsupportedWidgetPropertyWarning
     * @return string
     */
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
       
    /**
     * 
     * @param string $jqSelfJs
     * @param string $idxJs
     * @return string
     */
    protected function buildJsFooterValueGetterByColumnIndex(string $jqSelfJs, string $idxJs) : string
    {
        return $jqSelfJs . ".find('thead.footer td[data-x=\"' + {$idxJs} + '\"]').text()";
    }
    
    /**
     * 
     * @param string $dataJs
     * @param string $jqSelfJs
     * @return string
     */
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
        
    /**
     * 
     * @param string $jqSelfJs
     * @return string
     */
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
     * Returns the column properties to create an editor appropriate the columns cell widget.
     * 
     * Returns NULL if a standard editor is to be used (just an editable cell)!
     * 
     * @param DataColumn $col
     * @return string|NULL
     */
    protected function buildJsJExcelColumnEditorOptions(DataColumn $col) : ?string
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
                    $decSep = ($cellWidget instanceof InputNumber) ? $cellWidget->getDecimalSeparator() : $numberType->getDecimalSeparator();
                    $tsdSep = ($cellWidget instanceof InputNumber) ? $cellWidget->getThousandsSeparator() : $numberType->getGroupSeparator();
                    $options .= "allowEmpty: true, decimal:'{$decSep}',";
                    // FIXME what to do with the thousands/group-separator???
                    $type = "numeric";
                    // Add a mask for DataSpreadSheet but not for the DataImporter (it needs to accept any number format!)
                    if ($this->getWidget() instanceof DataSpreadSheet) {
                        $options .= "mask: '{$this->buildMaskNumeric($numberType, $decSep, $tsdSep)}',";
                    }
                    //$type = "numeral";
                    //$options .= "mask: '0', decimal:'{$decimal}', thousands:'{$thousands}',";
                }
                $align = EXF_ALIGN_RIGHT;
                break;
            case $cellWidget instanceof InputCheckBox:
            case $cellWidget instanceof Display && $cellWidget->getValueDataType() instanceof BooleanDataType:
                $type = "checkbox";
                break;
            case $cellWidget instanceof InputText:
            case $cellWidget instanceof Text:
                $type = "text";
                $options = 'wordWrap: true,';
                break;
            case $cellWidget instanceof InputSelect:
                $type = 'autocomplete';
                $align = EXF_ALIGN_LEFT;
                $options .= $this->buildJsJExcelColumnDropdownOptions($cellWidget);
                break;
            default:
                return null;
        }
        
        $align = $align ? 'align: "' . $align . '",' : '';
        return <<<JS
                type: "$type",
                $options
                $align

JS;
    }
    
    /**
     * 
     * @param DataColumn $col
     * @return string
     */
    protected function buildJsJExcelColumn(DataColumn $col) : string
    {
        $options = $this->buildJsJExcelColumnEditorOptions($col);
        
        if ($options === null) {
            $options = <<<JS
                type: "text",
                align: "left",

JS;
        }
        
        if ($col->isEditable() === false) {
            $options .= 'readOnly: true,';
        }
        
        $width = $col->getWidth();
        $widthJs = '';
        switch (true) {
            case $width->isFacadeSpecific() === true && StringDataType::endsWith($width->getValue(), 'px'):
                $widthJs = str_replace('px', '', $width->getValue());
                break;
            case $width->isFacadeSpecific():
            case $width->isPercentual():
                $widthJs = $this->escapeString($width->getValue());
                break;
            case $width->isRelative():
                $widthJs = $this->getWidthRelativeUnit() * $width->getValue();
                break;
            default:
                $widthJs = "'auto'";
        }
        
        if ($widthJs) {
            $widthJs = "width: {$widthJs},";
        }
        
        return <<<JS

            {
                title: "{$col->getCaption()}",
                {$widthJs}
                {$options}
            }
JS;
    }
      
    /**
     * 
     * @param InputSelect $cellWidget
     * @throws FacadeLogicError
     * @throws FacadeUnsupportedWidgetPropertyWarning
     * @return string
     */
    protected function buildJsJExcelColumnDropdownOptions(InputSelect $cellWidget) : string
    {
        if ($cellWidget->isBoundToAttribute() === false) {
            throw new FacadeLogicError('TODO');
        }
        $filterJs = '';
        if (! ($cellWidget instanceof InputCombo) || $cellWidget->getLazyLoading() === false) {
            if ($cellWidget->getAttribute()->isRelation()) {
                $rel = $cellWidget->getAttribute()->getRelation();
                
                if ($cellWidget instanceof InputComboTable) {
                    $srcSheet = $cellWidget->getOptionsDataSheet();
                    
                    // See if the widget has additional filters
                    // If so, add any attributes required for them to the $srcSheet
                    $filters = $cellWidget->getFilters();
                    if ($filters !== null) {
                        foreach ($filters->getConditions() as $cond) {
                            $expr = $cond->getValueLeftExpression();
                            if (! $expr->isReference() && ! $expr->isConstant()) {
                                $srcSheet->getColumns()->addFromExpression($expr);
                            }  
                            $expr = $cond->getValueRightExpression();
                            if (! $expr->isReference() && ! $expr->isConstant()) {
                                $srcSheet->getColumns()->addFromExpression($expr);
                            } 
                        }
                    }
                    foreach ($srcSheet->getFilters()->getConditions() as $cond) {
                        if ($cond->getRightExpression()->isReference()) {
                            $srcSheet->getFilters()->removeCondition($cond);
                        }
                    }
                    $srcIdName = $cellWidget->getValueColumn()->getDataColumnName();
                    $srcLabelName = $cellWidget->getTextColumn()->getDataColumnName();
                    $srcSheet->dataRead(0, 0); // Read all rows regardless of the settings in the data sheet!!!

                    // If the widget has additional filters, generate the JS to evaluate them
                    // here and put it into the `filter` property of the column
                    if ($filters !== null) {
                        $conditionJs = <<<JS

            var aSourcenew = [];
            var oConditionGroup = {'operator': "{$filters->getConditionGroup()->getOperator()}"};
            var aConditions = [];
JS;
                        foreach ($filters->getConditions() as $key => $cond) {
                            $valueExpr = $cond->getValueRightExpression();
                            $colName = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($cond->getValueLeftExpression()->toString());
                            $conditionJs .= <<<JS

            var sFilterValue_{$key} = {$this->buildJsFilterPropertyValue($valueExpr, $cellWidget)};
            var sColumnName_{$key} = '_' + "{$colName}";
            aConditions.push({'columnName': sColumnName_{$key}, 'comparator': "{$cond->getComparator()}", 'value':sFilterValue_{$key}})
JS;
                        }
                        $conditionJs .= <<<JS

            oConditionGroup.conditions = aConditions;
JS;
                        $filterJs = <<<JS

filter: function(instance, cell, c, r, source) {
{$conditionJs}
            aSourceNew = exfTools.data.filterRows(source, oConditionGroup);
            return aSourceNew;
        },
JS;
                    }
                } else {
                    $srcSheet = DataSheetFactory::createFromObject($rel->getRightObject());
                    
                    $srcIdAttr = $srcSheet->getMetaObject()->getUidAttribute();
                    $srcIdCol = $srcSheet->getColumns()->addFromAttribute($srcIdAttr);
                    $srcIdName = $srcIdCol->getName();
                    
                    $srcLabelAttr = $srcSheet->getMetaObject()->getLabelAttribute();
                    if ($srcLabelAttr->isRelation() === true && $srcLabelAttr->getRelation()->getRightObject()->hasLabelAttribute() === true) {
                        $srcLabelCol = $srcSheet->getColumns()->addFromExpression(RelationPath::relationPathAdd($srcLabelAttr->getAlias(), 'LABEL'));
                    } else {
                        $srcLabelCol = $srcSheet->getColumns()->addFromAttribute($srcLabelAttr);
                    }
                    $srcLabelName = $srcLabelCol->getName();
                    
                    $srcSheet->dataRead();
                }
                
                $srcData = [];
                foreach ($srcSheet->getRows() as $row) {
                    $data = ['id' => $row[$srcIdName], 'name' => $row[$srcLabelName]];
                    
                    foreach ($srcSheet->getColumns() as $col) {
                        $key = '_' . \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($col->getExpressionObj()->toString());
                        $data[$key] = $row[$col->getName()];
                    }
                    $srcData[] = $data;
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
        
        return "source: {$srcJson}, {$filterJs}";
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasFooter() : bool
    {
        foreach ($this->getWidget()->getColumns() as $col) {
            if ($col->hasFooter() === true) {
                return true;
            }
        }
        return false;
    }
        
    /**
     * 
     * @return int
     */
    protected function getMinSpareRows() : int
    {
        return $this->getAllowAddRows() === true ? 1 : 0;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getAllowAddRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataImporter) || ($widget instanceof DataSpreadSheet && $widget->getAllowToAddRows());
    }
    
    /**
     * 
     * @return bool
     */
    protected function getAllowDeleteRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataImporter) || ($widget instanceof DataSpreadSheet && $widget->getAllowToDeleteRows());
    }
    
    /**
     *
     * @return bool
     */
    protected function getAllowToDragRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataSpreadSheet) && $widget->getAllowToDeleteRows();
    }
    
    /**
     * 
     * @return bool
     */
    protected function getAllowEmptyRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataSpreadSheet) && $widget->getAllowEmptyRows();
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
        $dataObj = $this->getMetaObjectForDataGetter($action);
        
        // Determine the columns we need in the actions data
        $colNamesList = implode(',', $widget->getActionDataColumnNames());
        
        switch (true) {
            // If there is no action or the action 
            case $action === null:
            case $widget->isEditable() 
            && $action->implementsInterface('iModifyData')
            && $dataObj->is($widget->getMetaObject()):
                $data = <<<JS
    {
        oId: '{$this->getWidget()->getMetaObject()->getId()}',
        rows: aRows
    }
    
JS;
                break;
                
            // If we have an action, that is based on another object and does not have an input mapper for
            // the widgets's object, the data should become a subsheet.
            case $widget->isEditable() 
            && $action->implementsInterface('iModifyData')
            && ! $dataObj->is($widget->getMetaObject()) 
            && $action->getInputMapper($widget->getMetaObject()) === null:
                // If the action is based on the same object as the widget's parent, use the widget's
                // logic to find the relation to the parent. Otherwise try to find a relation to the
                // action's object and throw an error if this fails.
                if ($widget->hasParent() && $dataObj->is($widget->getParent()->getMetaObject()) && $relPath = $widget->getObjectRelationPathFromParent()) {
                    $relAlias = $relPath->toString();
                } elseif ($relPath = $dataObj->findRelationPath($widget->getMetaObject())) {
                    $relAlias = $relPath->toString();
                }
                
                if ($relAlias === null || $relAlias === '') {
                    throw new WidgetConfigurationError($widget, 'Cannot use data from widget "' . $widget->getId() . '" with action on object "' . $dataObj->getAliasWithNamespace() . '": no relation can be found from widget object to action object', '7CYA39T');
                }
                
                $configurator_element = $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
                
                $data = <<<JS
    {
        oId: '{$dataObj->getId()}',
        rows: [
            {
                '{$relAlias}': function(){
                    var oData = {$configurator_element->buildJsDataGetter()};
                    oData.rows = aRows
                    return oData;
                }()
            }
        ],
        filters: [
            
        ]
    }
    
JS;
                break;
                
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            case $action instanceof iReadData:
                return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
            
            // In all other cases, get the selected rows as a regular table would do
            default:
                $data = <<<JS
    {
        oId: '{$this->getWidget()->getMetaObject()->getId()}',
        rows: (aRows || []).filter(function(oRow, i){
            return {$this->buildJsJqueryElement()}.jexcel('getSelectedRows', true).indexOf(i) >= 0;
        })
    }

JS;         
        }
            
        return <<<JS
        (function(){ 
            var aRows = {$rows};
            // Remove any keys, that are not in the columns of the widget
            aRows = aRows.map(({ $colNamesList }) => ({ $colNamesList }));

            {$this->buildJsFixedFootersSpread()}; 

            return {$data} 
        })()
JS;
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
        {$this->buildJsJqueryElement()}[0].exfWidget._initData = oData.rows;
    } else {
        {$this->buildJsJqueryElement()}[0].exfWidget._initData = [];
    }
    if (aData.length === 0) {
        for (var i = 0; i < {$this->getMinSpareRows()}; i++) {
            aData.push([]);
        }
    }
    {$this->buildJsJqueryElement()}.jexcel('setData', aData);
    {$this->buildJsResetSelection($this->buildJsJqueryElement())};
    {$this->buildJsJqueryElement()}[0].exfWidget.refreshConditionalProperties();
}()

JS;
    }
        
    protected function buildJsConvertArrayToData(string $arrayOfArraysJs) : string
    {
        return "{$this->buildJsJqueryElement()}[0].exfWidget.convertArrayToData({$arrayOfArraysJs})";
    }
    
    protected function buildJsConvertDataToArray(string $arrayOfObjectsJs) : string
    {
        return "{$this->buildJsJqueryElement()}[0].exfWidget.convertDataToArray({$arrayOfObjectsJs})";
    }
    
    /**
     * 
     * @param DataColumn $col
     * @return bool
     */
    protected function needsDataFormatting(DataColumn $col) : bool
    {
        switch (true) {
            // No formatting if explicitly disabled
            case $col->getCellWidget() instanceof Display && $col->getCellWidget()->getDisableFormatting():
                return false;
            // No formatting for dropdowns (need raw values here!)
            case $col->getCellWidget() instanceof InputSelect:
                return false;
            // Force formatting for numbers and columns without special editors
            case $col->getDataType() instanceof NumberDataType:
            case $this->buildJsJExcelColumnEditorOptions($col) === null:
                return true;
        }
        // No formatting by default
        return false;
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsDataResetter() : string
    {
        return "(function(){ {$this->buildJsJqueryElement()}.jexcel('setData', [ [] ]); {$this->buildJsResetSelection($this->buildJsJqueryElement())} })();";
    }
    
    /**
     * 
     * @return string
     */
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
        return "jexcel.destroy({$this->buildJsJqueryElement()}[0], false); $('.exf-partof-{$this->getId()}').remove();";
    }
    
    /**
     * 
     * @param NumberDataType $dataType
     * @param string $decimalSeparator
     * @return string
     */
    protected function buildMaskNumeric(NumberDataType $dataType, string $decimalSeparator = null, string $groupSeparator = null, int $groupLenght = 3) : string
    {
        $groupSeparator = $groupSeparator ?? $dataType->getGroupSeparator();
        $decimalSeparator = $decimalSeparator ?? $dataType->getDecimalSeparator();
        
        /*
        if ($dataType->getPrecisionMax() === 0) {
            return '0';
        }
        
        if ($dataType->getPrecisionMin() === null && $dataType->getPrecisionMax() === null) {
            return '';
        }*/
        
        $format = '';
        if ($groupSeparator) {
            $format = '#' . $groupSeparator . '##';
        }
        $format .= '0';
        
        $minPrecision = $dataType->getPrecisionMin();
        if ($minPrecision > 0) {
            $format .= $decimalSeparator;
            for ($i = 1; $i <= $minPrecision; $i++) {
                $format .= '0';
            }
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
        	if (regEx.test(data.substring(0, 500)) === true) {
            	data = data.replace(regEx, '');
        	}
        }
        return data;
    }
});

JS;
    }
    
    /**
     * 
     * @param string $oExcelElJs
     * @param string $iColJs
     * @param string $iRowJs
     * @return string
     */
    protected function buildJsOnUpdateApplyValuesFromWidgetLinks(string $oExcelElJs, string $iColJs, string $iRowJs) : string
    {
        $linkedColIdxsJs = '[';
        foreach ($this->getWidget()->getColumns() as $colIdx => $col) {
            $cellWidget = $col->getCellWidget();
            if ($cellWidget->hasValue() === false) {
                continue;
            }
            $valueExpr = $cellWidget->getValueExpression();
            if ($valueExpr->isReference() === true) {
                $linkedColIdxsJs .= $colIdx . ',';
                $linkedEl = $this->getFacade()->getElement($valueExpr->getWidgetLink($cellWidget)->getTargetWidget());
                $addLocalValuesToRowJs .= <<<JS
                $oExcelElJs.jexcel.setValueFromCoords({$colIdx}, parseInt({$iRowJs}), {$linkedEl->buildJsValueGetter()}, true);

JS;
            }    
        }
        $linkedColIdxsJs .= ']';
        return <<<JS

            var aLinkedCols = $linkedColIdxsJs;
            if (! aLinkedCols.includes($iColJs)) {
                $addLocalValuesToRowJs
            }
JS;
    }
    
    /**
     *
     * @see AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter($columnName = null, $row = null)
    {
        if (is_null($columnName)) {
            if ($this->getWidget()->hasUidColumn() === true) {
                $col = $this->getWidget()->getUidColumn();
            } else {
                throw new FacadeRuntimeError('Cannot create a value getter for a data widget without a UID column: either specify a column to get the value from or a UID column for the table.');
            }
        } else {
            if (! $col = $this->getWidget()->getColumnByDataColumnName($columnName)) {
                $col = $this->getWidget()->getColumnByAttributeAlias($columnName);
            }
        }
        
        $delimiter = $col->isBoundToAttribute() ? $col->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
        
        return <<<JS
(function(){
    var aAllRows = {$this->buildJsDataGetter()}.rows;
    var aSelectedIdxs = $('#{$this->getId()}').jexcel('getSelectedRows', true);
    var aVals = [];
    aSelectedIdxs.forEach(function(iRowIdx){
        aVals.push(aAllRows[iRowIdx]['{$col->getDataColumnName()}']);
    })
    return aVals.join('{$delimiter}');
})()
JS;
    }
    
    /**
     * 
     * @param string $elementJs
     * @return string
     */
    protected function buildJsResetSelection(string $elementJs) : string
    {
        return "$elementJs.data('_exfSelection', {x1: null, y1: null, x2: null, y2: null})";
    }
    
    private function buildJsFilterPropertyValue(ExpressionInterface $expr, WidgetInterface $cellWidget = null) : string
    {
        switch (true) {
            case $expr->isReference() === true:
                $link = $expr->getWidgetLink($cellWidget);
                if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                    $valueJs = $linked_element->buildJsValueGetter($link->getTargetColumnId());
                }
                break;
            case $expr->isFormula() === false && $expr->isMetaAttribute() === false:
                $valueJs = "'" . str_replace('"', '\"', $expr->toString()) . "'";
                break;
            default:
                throw new WidgetConfigurationError('Cannot use expression "' . $expr->toString() . '" in the filter value: only scalar values and widget links supported!');
        }
        
        return $valueJs;
    }
    
    protected function registerConditionalPropertiesOfColumns() 
    {
        foreach ($this->getWidget()->getColumns() as $col) {
            if ($condProp = $col->getDisabledIf()) {
                $this->registerConditionalPropertyUpdaterOnLinkedElements(
                    $condProp,
                    "{$this->buildJsJqueryElement()}[0].exfWidget.refreshConditionalProperties()",
                    "{$this->buildJsJqueryElement()}[0].exfWidget.refreshConditionalProperties()"
                );
            }
        }
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsValidator() : string
    {
        return <<<JS

(function(jqExcel) {
    jqExcel[0].exfWidget.validateAll();
    return jqExcel.find('.exf-spreadsheet-invalid').length === 0;
})({$this->buildJsJqueryElement()})
        
JS;
    }
}