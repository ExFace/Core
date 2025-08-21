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
use exface\Core\Widgets\Parts\ConditionalProperty;
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
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Widgets\DataButton;
use exface\Core\Widgets\DataTable;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;

/**
 * Common methods for facade elements based on the jExcel library.
 * 
 * ## API
 * 
 * ### exfWidget
 *
 * - getJExcel() : Object
 * - getDom() : DomElement
 * - getDataLastLoaded() : array
 * - getColumnName(iColIdx) : string
 * - getColumnIndex(string colName) : int
 * - setValueGetterRow(iRow) : void
 * - getValueGetterRow() : Object,
 * - getColumnModel(int iColIdx) : ColumnModel
 * - getInitValue(iCol, iRow) : mixed
 * - restoreInitValue(iCol, iRow) : void
 * - hasChanged(iCol, iRow, mValue) : bool
 * - hasChanges() : bool
 * - validateValue(iCol, iRow, mValue) : mixed
 * - validateCell(cell, iCol, iRow, mValue, bParseValue) : mixed
 * - validateAll() : void
 * - refreshConditionalProperties();
 * - convertArrayToData(aDataArray) : array
 * - convertDataToArray(aDataRows) : array
 * - setDisabled(bDisable)
 * - isDisabled()
 * - success(data, textStatus, jqXHR)
 * - error(jqXHR, textStatus, errorThrown)
 * 
 * ### ColumnModel
 * 
 * Accessible via `{$this->buildJsJqueryElement()}[0].exfWidget.getColumnModel(iColIdx)` or similar.
 * 
 * - dataColumnName: string,
 * - caption: string,
 * - tooltip: string,
 * - parser: function(mValue),
 * - formatter: function(mValue),
 * - validator: function(mValue),
 * - hidden: bool,
 * - lazyLoading: bool,
 * - wasLazyLoaded: bool,
 * - requestConfig: Object,
 * - isRelation: bool,
 * - isSelfReferencing: bool,
 * - dropdownIdField: string,
 * - dropdownLabelField: string,
 * - system: bool,
 * - conditionize: function(oWidget)
 * 
 * ## Usage
 * 
 * Make sure to include jExcel in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "npm-asset/jspreadsheet-ce" : "^4.10",
		* "npm-asset/jspreadsheet--autowidth" : "^2"
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
    
    private $onInitScripts = [];
    
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
        var aData = jqExcel.jspreadsheet('getData');
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
        $disabledJs = $widget->isDisabled() ? 'true' : 'false';
        
        if (($widget instanceof DataSpreadSheet) && $widget->hasRowNumberAttribute()) {
            $rowNumberCol = $widget->getRowNumberColumn();
            $rowNumberColNameJs =  "'{$rowNumberCol->getDataColumnName()}'";
        } else {
            $rowNumberCol = null;
            $rowNumberColNameJs = 'null';
        }
        
        $columnsJson = $this->buildJsJExcelColumnModels($rowNumberCol);
        
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
            var oWidget = jqSelf[0].exfWidget;
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

            if (oWidget !== undefined && oWidget.isDisabled() === true) {
                {$this->buildJsSetDisabled(true)}
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
        oncreateeditor: function(el, cell, x, y, value) {
            // only request data if column type is autocomplete, everything is fully rendered, lazy loading is true for column
            let columnType = el.jexcel.options.columns[x].type;
            if (columnType === 'autocomplete' && el.exfWidget.bLoaded && el.exfWidget.getColumnModel(x).lazyLoading && !el.exfWidget.getColumnModel(x).wasLazyLoaded && !el.isLazyLoadingInProgress) {
                
                let colName = el.exfWidget.getColumnName(x);
                if (colName) {
                    // set flag to avoid multiple requets when re-opening editor
                    el.isLazyLoadingInProgress = true;

                    // remove readonly classes (disabled-if) from column before loading data,
                    // then re-conditionize everything after data has loaded
                    var aCells = [];
                    el.jexcel.getColumnData(x).forEach(function(mVal, iRowIdx){
                        aCells.push(el.jexcel.getCellFromCoords(x , (iRowIdx)));
                    });
                    aCells.forEach(function(domCell, iRowIdx){
                        if (! domCell) return;
                        domCell.classList.remove('readonly');
                    });

                    // request data for dropdown, then re-render
                    el.exfWidget.refreshDropdown(x).then(() => {
                        el.jexcel.closeEditor(el.jexcel.records[y][x]);
                        let rows = el.jexcel.getData().length;
                        // re-set values in column to re-render
                        for (let rowIndex = 0; rowIndex < rows; rowIndex++) {
                            let value = el.jexcel.getValueFromCoords(x, rowIndex);
                            if (value){
                                el.jexcel.setValueFromCoords(x, rowIndex, value); 
                            }
                        }
                    }).catch((err) => {
                        console.error('Failed to update dropdown data:', err);
                    }).then(() => {
                        //reopen dropdown to re-render and set loaded flag for column true
                        el.jexcel.openEditor(el.jexcel.records[y][x]);
                        el.exfWidget.getColumnModel(x).wasLazyLoaded = true;
                    })
                    .finally(() => {
                        // set loading flag false and re-conditionize table
                        el.isLazyLoadingInProgress = false;
                        el.exfWidget.refreshConditionalProperties();
                    });
                }
            }  
            
        },
        onchange: function(instance, cell, col, row, value, oldValue) {
            // setTimeout ensures, the minSpareRows are always added before the subsequent logic runs. This is
            // important for value/data getters to work properly as they will ignore spare rows
            {$this->buildJsOnUpdateApplyValuesFromWidgetLinks('instance', 'col', 'row')};
            setTimeout(function(){
                // Calculate footer
                {$this->buildJsFixedFootersSpread()}

                // refresh the conditional properties of spreadsheet onchange
                // dont conditionize while lazy loading is in progress, otherwise disabled cells do not load data
                if (!instance.isLazyLoadingInProgress){
                    instance.exfWidget.refreshConditionalProperties();
                }
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
                $(el).jspreadsheet('updateSelectionFromCoords', oSel.x1, oSel.y1, oSel.x2, oSel.y2);
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
        },

        /**
        * Before the paste action is performed. Can return parsed or filtered data, can cancel the action when return false.
        *
        * @param el: Object
        * @param data: Array
        * @param x: Number
        * @param y: Number
        * @param style: Array
        * @param processedData: String
        */
        onbeforepaste: function(el, data, x, y, style, processedData) {
            var oDropdownVals = {};
            var oDropdownFiters = {};
            var aPastedData = [];
            var aProcessedData = [];
            var iXStart = parseInt(x);
            var iXEnd = iXStart;
            var oColOpts = {};

            el.jspreadsheet.parseCSV(data, "\\n").forEach(function(aRow){
                aPastedData.push(aRow[0].split("\\t"));
            });
            iXEnd = iXStart + aPastedData[0].length;

            for (var i = iXStart; i <= iXEnd; i++) {
                oColOpts = el.jspreadsheet.options.columns[i];
                if (oColOpts !== undefined && oColOpts.type === 'autocomplete' && Array.isArray(oColOpts.source) && oColOpts.source.length > 0) {
                    if (typeof(oColOpts.filter) == 'function') {
                        oDropdownVals[i - iXStart] = oColOpts.filter(el, null, (i - iXStart), null, oColOpts.source);
                    } else {
                        oDropdownVals[i - iXStart] = oColOpts.source;
                    }
                }
            };

            if (oDropdownVals === {}) {
                return selectedCells;
            }

            aPastedData.forEach(function(aRow) {  
                var aValRows, mVal, oValRow, bKeyFound;
                for (var iCol in oDropdownVals) {
                    bKeyFound = false;
                    aValRows = oDropdownVals[iCol];
                    mVal = aRow[iCol];
                    for (var i = 0; i < aValRows.length; i++) {
                        oValRow = aValRows[i];
                        if (oValRow.name == mVal) {
                            aRow[iCol] = oValRow.id;
                            bKeyFound = true;
                            break;
                        }
                    }
                    if (bKeyFound === false) {
                        aRow[iCol] = '';
                    }
                }
                aProcessedData.push(aRow.join("\t"));
            });

            // If a single value is pasted and it does not represent a valid dropdown value
            // we have to return false, else the original value will still be pasted into the cell
            // even when we did overwrite it with an empty string.
            // Seems like an unfortunate implemantation in the jspreadsheet library.
            if (aProcessedData.length === 1 && aProcessedData[0] === '') {
                return false;
            }
            return aProcessedData.join("\\r\\n");
        },

        /**
        * When a copy is performed in the spreadsheet. 
        * Any string returned will overwrite the user data or return null to progress with the default behavior.
        * NOTE: returning a string does not work though!
        * @param el: Object
        * @param selectedCells: Array
        * @param data: String
        */
        oncopy: function(el, selectedCells, data) {
            var oDropdownVals = {};
            var aSelectedData = [];

            el.jspreadsheet.getSelectedColumns().forEach(function(iX, iCol){
                var oColOpts = el.jspreadsheet.getColumnOptions(iX);
                if (oColOpts.type === 'autocomplete' && Array.isArray(oColOpts.source) && oColOpts.source.length > 0) {
                    oDropdownVals[iCol] = oColOpts.source;
                }
            });

            if (oDropdownVals === {}) {
                return selectedCells;
            }

            selectedCells.forEach(function(sRow, iX) {
                var aRow = sRow.split("\t");
                var aValRows, mVal, oValRow;
                for (var iCol in oDropdownVals) {
                    aValRows = oDropdownVals[iCol];
                    mVal = aRow[iCol];
                    for (var i = 0; i < aValRows.length; i++) {
                        oValRow = aValRows[i];
                        if (oValRow.id == mVal) {
                            aRow[iCol] = oValRow.name;
                            break;
                        }
                    }
                }
                aSelectedData.push(aRow.join("\t"));
            });

            this.data = aSelectedData.join("\\r\\n");

            // Create a hidden textarea to copy the values
            this.textarea.value = this.data;
            this.textarea.select();
            document.execCommand("copy");

            return this.data;
        }
    });

    {$this->buildJsJqueryElement()}[0].exfWidget = {
        _dom: {$this->buildJsJqueryElement()}[0],
        _colNames: {$colNamesJson},
        _cols: {$columnsJson},
        _rowNumberColName: $rowNumberColNameJs,
        _initData: [],
        _disabled: $disabledJs,
        _valueGetterRow: null,
        getJExcel: function(){
            return this._dom.jspreadsheet;
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
        getColumnIndex: function(colName) {
            let keys = Object.keys(this._colNames);
            for (let i = 0; i < keys.length; i++) {
                if (this._colNames[keys[i]] === colName) {
                    return i;
                }
            }
            return -1;
        },
        setValueGetterRow: function(iRow) {
            this._valueGetterRow = iRow;
        },
        getValueGetterRow: function() {
            return this._valueGetterRow;
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
                // Make sure to parse the initial value too! For example, a decimal would be `100.00`
                // as raw (initial) data and `100` once edited in the spreadsheet.
                mInitVal = oCol.parser ? oCol.parser(mInitVal) : mInitVal;
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
            var oColModel = this.getColumnModel(iCol);
            var fnValidator = oColModel.validator;

            if (fnValidator === null || fnValidator === undefined || oColModel.hidden === true) {
                return true;
            }            
            return fnValidator(mValue);
        },
        validateCell: function (cell, iCol, iRow, mValue, bParseValue) {
            var mValidationResult;
            var oCol = this.getColumnModel(iCol);
            var bRequired = oCol.checkRequired(iRow);
            var bDisabled = $(cell).children('input').prop('disabled');
            var bEmpty = false;
            if (mValue === '\u0000') {
                mValue = '';
            }
            bParseValue = bParseValue === undefined ? false : true;
            if (bParseValue === true) {
                mValue = oCol.parser ? oCol.parser(mValue) : mValue;
            }
            bEmpty = (mValue === '' || mValue === null || mValue === undefined);

            mValidationResult = this.validateValue(iCol, iRow, mValue);
            if (mValidationResult === true && bRequired === true && bDisabled !== true && bEmpty) {
                mValidationResult = {$this->escapeString($this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.INPUT.VALIDATION_REQUIRED'))};
            }
            
            if (this.hasChanged(iCol, iRow, mValue)) {
                $(cell).addClass('exf-spreadsheet-change');
            } else {
                $(cell).removeClass('exf-spreadsheet-change');
                mValue = this.getInitValue(iCol, iRow);
            }

            // only remove invalid class if it is not explicitly set for required-if (in conditionize())
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
                    var oCell = oWidget.getJExcel().getCell(jspreadsheet.getColumnName(iColIdx) + (iRowIdx + 1));
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
            var domEl = this._dom;
            var oWidget = this;
            var oColNames = this._colNames;
            var aColHeaders = domEl.jspreadsheet.getHeaders().split(',');
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
        },

        setDisabled: function(bDisable) {
            var oWidget = this;
            var oJExcel = oWidget.getJExcel();
            var iColNo = 1;
            oWidget._disabled = bDisable;
            oJExcel.getConfig().columns.forEach(function(oColCfg, iColIdx){
                var fnDisabler;
                var aCells = [];
                if (oColCfg.type === 'hidden') {
                    return;
                }
                iColNo++;
                if (oColCfg.readOnly === true) {
                    return;
                }
                switch (true) {
                    case oColCfg.type === 'checkbox':
                        fnDisabler = function(domCell){
                            $(domCell).children('input').prop('disabled', bDisable);
                        };
                        break;
                }
                oJExcel.getColumnData(iColNo).forEach(function(mVal, iRowIdx){
                    aCells.push(oJExcel.getCell(jspreadsheet.getColumnName(iColNo) + (iRowIdx + 1)));
                });
                aCells.forEach(function(domCell, iRowIdx){
                    // Do nothing if no cell was (yet) rendered
                    if (! domCell) return;
                    if (bDisable) {
                        if (oWidget.hasChanged(iColIdx, iRowIdx)) {
                            oWidget.restoreInitValue(iColIdx, iRowIdx); 
                        }
                        domCell.classList.add('readonly');
                    } else {
                        domCell.classList.remove('readonly');
                    }
                    if (fnDisabler !== undefined) {
                        fnDisabler(domCell);
                    }
                });
            });
        },

        isDisabled: function(){
            return this._disabled;
        },

        refreshDropdown(iColIdx) {
            return new Promise((resolve, reject) => { 
                // load config and additional data for dropdown
                var oJExcel = this.getJExcel(); 
                var requestConfig = this.getColumnModel(iColIdx).requestConfig;
                var isRelation = this.getColumnModel(iColIdx).isRelation;
                var dropdownLabel = this.getColumnModel(iColIdx).dropdownLabelField;
                var dropdownId = this.getColumnModel(iColIdx).dropdownIdField;
                
                // TODO: if dropdowns have filters applied to them, lazy loading the data might disable them 
                // -> if filters are applied on a column name that doesnt exist in dropdown src (after lazy loading)
                $.ajax({
                    type: 'GET',
                    url: '{$this->getAjaxUrl()}',
                    headers: requestConfig.headers,
                    data: { 
                        action: requestConfig.action_alias,
                        resource: requestConfig.page_id,
                        element: requestConfig.element_id,
                        object: requestConfig.object_id
                    },
                    success: function(data, textStatus, jqXHR) {
                        if (typeof data === 'object') {
                            response = data;
                        } else {
                            var response = {};
                            try {
                                response = $.parseJSON(data);
                            } catch (e) {
                                response.error = data;
                            }
                        }
                        if (response.success){
                            newData = response.rows;
                            // if it is relation, use requested data and set id and label names
                            if (isRelation && dropdownLabel && dropdownId) {
                                newData = newData.map(row => {
                                    const transformedRow = {};
                                    if (row[dropdownId]) {
                                        transformedRow['id'] = row[dropdownId];
                                    }
                                    if (row[dropdownLabel]) {
                                        transformedRow['name'] = row[dropdownLabel];
                                    }
                                    // append all keys marked with underscore
                                    Object.keys(row).forEach(key => {
                                        transformedRow['_'+key] = row[key];
                                    });
                                    return transformedRow;
                                });
                                //overwrite dropdown source array and resolve promise
                                if (newData != null){
                                    oJExcel.getConfig().columns[iColIdx].source =  newData; 
                                }
                                resolve();
                            }
                        } else {
                            {$this->buildJsShowError('jqXHR.responseText', "(jqXHR.statusCode+' '+jqXHR.statusText)")}
                            reject("Lazy loading request failed");
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown){
                        {$this->buildJsShowError('jqXHR.responseText', "(jqXHR.statusCode+' '+jqXHR.statusText)")}
                        reject("Lazy loading request failed");
                    }
                })
            });
        }
    };
    
    {$this->buildJsInitPlugins()}
    {$this->buildJsFixContextMenuPosition()}

    {$this->buildJsOnInitScript()}
    
    // set loaded flag to allow onclick lazy loading for dropdowns
    {$this->buildJsJqueryElement()}[0].exfWidget.bLoaded = true;

JS;
    }
    
    protected function buildJsJExcelColumnModels(?DataColumn $rowNumberCol = null) : string
    {
        $widget = $this->getWidget();
        /* @var $col \exface\Core\Widgets\DataColumn */
        foreach ($widget->getColumns() as $colIdx => $col) {
            $cellWidget = $col->getCellWidget();
            // If the values were formatted according to their data types in buildJsConvertDataToArray()
            // parse them back here
            if ($this->needsDataFormatting($col)) {
                $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
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
                $cellEl = $this->getFacade()->getElement($cellWidget);
                if ($cellWidget instanceof Input) {
                    $validatorJs = <<<JS
                        function(value){ 
                            var bValid = {$cellEl->buildJsValidator('value')};
                            var sInvalidHint = {$this->escapeString($cellEl->getValidationErrorText(), true, false)};
                            return bValid ? true : sInvalidHint;
                        }
JS;
                }
            }
            if (! $validatorJs) {
                $validatorJs = 'function(value){return true}';
            }

            $hiddenFlagJs = $col->isHidden() ? 'true' : 'false';
            $systemFlagJs = $col->isBoundToAttribute() && $col->getAttribute()->isSystem() ? 'true' : 'false';

            // Disabling conditions
            $conditionsJs = '';
            if (null !== $condProp = $col->getDisabledIf()) {
                $conditionsJs .= $this->buildJsColumnDisabledIf($condProp, 'aCells');
            }

            // Required conditions
            $requiredCheckerJs = '';
            if ($cellWidget instanceof Input && ($condProp = $cellWidget->getRequiredIf())) {
                $conditionsJs .= $this->buildJsColumnRequiredIf($condProp, 'oJExcel', 'aCells', 'iColIdx');
                $requiredCheckerJs = <<<JS
                    
                        var oWidget = {$this->buildJsJqueryElement()}[0].exfWidget;
                        var bRequired = false;
                        oWidget.setValueGetterRow(iRowIdx);
                        {$this->buildJsConditionalProperty($condProp, 'bRequired = true', 'bRequired = false')};
                        oWidget.setValueGetterRow(null);
                        return bRequired;
JS;
            } elseif ($cellWidget instanceof iCanBeRequired) {
                $requiredCheckerJs = 'return ' . $this->escapeBool($cellWidget->isRequired());
            } else {
                $requiredCheckerJs = 'return false;';
            }

            // Build condition script
            if ('' !== trim($conditionsJs)) {
                $conditionsJs = <<<JS

                        var iColIdx = {$colIdx};
                        var oJExcel = oWidget.getJExcel();
                        var aCells = [];
                        oJExcel.getColumnData(iColIdx).forEach(function(mVal, iRowIdx){
                            aCells.push(oJExcel.getCell(jspreadsheet.getColumnName(iColIdx) + (iRowIdx + 1)));
                        });
                        $conditionsJs
JS;
            }

            $lazyLoadingFlagJs = (($cellWidget instanceof InputComboTable) && $cellWidget->getLazyLoading()) ? 'true' : 'false';
            $wasLazyLoaded = 'false';

            $lazyLoadingRequestJs = json_encode("");
            $isRelation = false;
            $dropdownLabel = '';
            $dropdownId = '';

            // request config for ajax request to refresh dropdown data 
            if ($cellWidget instanceof InputComboTable && $cellWidget->getAttribute()->isRelation()) {

                $lazyLoadingRequestOptions = [
                    'action_alias' => $cellWidget->getLazyLoadingActionAlias(),
                    'page_id' => $this->getPageId(),
                    'element_id' => $cellWidget->getTable()->getId(),
                    'object_id' => $cellWidget->getTable()->getMetaObject()->getId(),
                    'headers' => ! empty($this->getAjaxHeaders()) ? 'headers: ' . json_encode($this->getAjaxHeaders()) . ',' : ''
                ];
                $lazyLoadingRequestJs = json_encode($lazyLoadingRequestOptions);

                // get id and label names for dropdown formatting
                $srcIdName = $cellWidget->getValueColumn()->getDataColumnName();
                $srcLabelName = $cellWidget->getTextColumn()->getDataColumnName();

                $dropdownLabel = $srcLabelName;
                $dropdownId = $srcIdName;
                $isRelation = true;
            }

            $columnsJson .= <<<JS
                "{$col->getDataColumnName()}": {
                    dataColumnName: "{$col->getDataColumnName()}",
                    caption: {$this->escapeString($col->getCaption(), true, false)},
                    tooltip: {$this->escapeString($col->getHint() ?? '', true, false)},
                    parser: {$parserJs},
                    formatter: {$formatterJs},
                    validator: {$validatorJs},
                    checkRequired: function(iRowIdx){
                        {$requiredCheckerJs}
                    },
                    hidden: {$hiddenFlagJs},
                    lazyLoading: {$lazyLoadingFlagJs},
                    wasLazyLoaded: {$wasLazyLoaded},
                    requestConfig: {$lazyLoadingRequestJs},
                    isRelation: {$this->escapeBool($isRelation)},
                    isSelfReferencing: {$this->escapeBool($hasSelfReference ?? false)},
                    dropdownIdField: {$this->escapeString($dropdownId)},
                    dropdownLabelField: {$this->escapeString($dropdownLabel)},
                    system: {$systemFlagJs},
                    conditionize: function(oWidget){
                        $conditionsJs
                    }
                }, 

JS;
            // If there is a row_number_attribute_alias, double check if the same attribute is also used
            // as a regular column. This does not make sense, as the row numbers cannot be explicitly edited.
            // It is also a technical problem because 
            if ($rowNumberCol !== null && $col !== $rowNumberCol && $col->getDataColumnName() === $rowNumberCol->getDataColumnName()) {
                throw new WidgetConfigurationError($widget, 'Cannot use the row number column of a DataSpreadSheet as a regular column too!');
            }
        }
        return '{' . $columnsJson . '}';
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
            if ($col->getCellWidget() instanceof iCanBeRequired && $col->getCellWidget()->isRequired() && $col->isEditable()) {
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
                            jqSelf.jspreadsheet('setData', aData);
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
                        var aData = jqSelf.jspreadsheet('getData');

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
                $align = "center";
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
        
        $align = $align ? 'align: "' . $align . '",' : 'align: "left",';
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

filter: function(instance, cell, x, y, source) {
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
                        $srcLabelCol = $srcSheet->getColumns()->addFromExpression(RelationPath::join($srcLabelAttr->getAlias(), 'LABEL'));
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
            // Lazy Loading:
            // loads column src data when dropdown is opened (see oneditorcreate event)
            // sets placeholder text while loading data

            if ($cellWidget instanceof InputComboTable && $cellWidget->getAttribute()->isRelation()) {
                $srcData[] = ['id' => 1, 'name' => '...'];
                $srcJson = json_encode($srcData);
            }
            else{
                throw new FacadeUnsupportedWidgetPropertyWarning('Lazy loading is not supported for this type of attribute in Jexcel.');
            }
        }

        // Update dropdown values on action effects that might affect them
        if ($cellWidget instanceof InputComboTable && $cellWidget->getAttribute()->isRelation()) {

            // get affected objects
            $colTable = $cellWidget->getTable();
            foreach ($colTable->getMetaObjectsEffectingThisWidget() as $object) {
                if ($object->getAliasWithNamespace() !== null) {
                    $effectedAliases[] = $object->getAliasWithNamespace();
                }
            }
            $effectedAliasesJs = json_encode(array_values(array_unique($effectedAliases)));
            $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;

            // js to remove old event listeners
            // TODO: check in ui5
            $removeDropdownRefreshListeners = <<<JS

                       $( document ).off( '{$actionperformed}.{$this->getId()}' );
JS;
            $this->addOnInitScript($removeDropdownRefreshListeners);

            // event listeners for action effects
            $refreshDropdownJs = <<<JS

                        $( document ).on( '{$actionperformed}.{$this->getId()}', function( oEvent, oParams ) {
                    
                            var oEffect = {};
                            var aUsedObjectAliases = {$effectedAliasesJs};
                            var fnRefresh = function() {
                                {$this->buildJsJqueryElement()}[0].exfWidget.refreshDropdown({$colIdx});
                            };
                        
                            for (var i = 0; i < oParams.effects.length; i++) {
                                oEffect = oParams.effects[i];
                                if (aUsedObjectAliases.indexOf(oEffect.effected_object) !== -1) {
                                    // refresh immediately if directly affected or delayed if it is an indirect effect
                                    if (oEffect.effected_object === '{$this->getWidget()->getMetaObject()->getAliasWithNamespace()}') {
                                        fnRefresh();
                                    } else {
                                        setTimeout(fnRefresh, 100);
                                    }
                                    return;
                                }
                            }
                            
                        });
JS;
            $this->addOnInitScript($refreshDropdownJs);
        }
        
        return "options: {newOptions: false}, source: {$srcJson}, {$filterJs}";
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
        return ($widget instanceof DataImporter) || ($widget instanceof DataSpreadSheet && $widget->getAllowToDeleteRows() === true);
    }
    
    /**
     *
     * @return bool
     */
    protected function getAllowToDragRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataSpreadSheet) && $widget->getAllowToDragRows() === true;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getAllowEmptyRows() : bool
    {
        $widget = $this->getWidget();
        return ($widget instanceof DataSpreadSheet) && $widget->getAllowEmptyRows() === true;
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
        $widgetObj = $widget->getMetaObject();
        $dataObj = $this->getMetaObjectForDataGetter($action);
        
        // Determine the columns we need in the actions data
        $colNamesList = implode(',', $widget->getActionDataColumnNames());
        
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        $relPathToParent = $widget->getObjectRelationPathToParent();
        
        switch (true) {
            // If there is no action or the action 
            case $customMode === DataButton::INPUT_ROWS_ALL:
            case $action === null:
            // Or the action is based on the same object (really the same one, not
            // even a self-relation)
            case $widget->isEditable() 
            && $action->implementsInterface('iModifyData')
            && (
                $dataObj->is($widgetObj)
                && ($relPathToParent === null || $relPathToParent->isEmpty() || $relPathToParent->getEndObject()->isExactly($dataObj) === false)
            ):
                $data = <<<JS
    {
        oId: '{$widgetObj->getId()}',
        rows: aRows
    }
    
JS;
                break;
                
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';
                
            // If we have an action, that 
            // - is based on another object OR the same object with an explicitly defined relation
            // - AND does not have an input mapper for
            // the widgets's object, the data should become a subsheet.
            case $customMode === DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:
            case $widget->isEditable() 
            && $action->implementsInterface('iModifyData')
            && (
                ! $dataObj->is($widgetObj) 
                || ($relPathToParent !== null && $relPathToParent->isEmpty() === false && $relPathToParent->getEndObject()->isExactly($dataObj))
            )
            && $action->getInputMapper($widgetObj) === null:
                // If the action is based on the same object as the widget's parent, use the widget's
                // logic to find the relation to the parent. Otherwise try to find a relation to the
                // action's object and throw an error if this fails.
                if ($widget->hasParent() && $dataObj->is($widget->getParent()->getMetaObject()) && null !== $relPathFromParent = $widget->getObjectRelationPathFromParent()) {
                    $relAlias = $relPathFromParent->toString();
                } elseif (null !== $relPathFromDataObj = $dataObj->findRelationPath($widgetObj)) {
                    $relAlias = $relPathFromDataObj->toString();
                }
                
                if ($relAlias === null || $relAlias === '') {
                    throw new WidgetConfigurationError($widget, 'Cannot use data from widget "' . $widget->getId() . '" with action on object "' . $dataObj->getAliasWithNamespace() . '": no relation can be found from widget object to action object', '7CYA39T');
                }
                
                $configurator_element = $this->getFacade()->getElement($widget->getConfiguratorWidget());
                
                // FIXME the check for visibility in case of empty data is there to prevent data loss if
                // jExcel was hidden. This happened in UI5 in a Tab, that got hidden after a certain action.
                // The jExcel in that tab was visible and got an HTML element. Once the dialog was closed and
                // reopened, the tab was not visible anymore and for some reason the jExce inside did not get
                // proper data. Not sure, if hidden subsheet excels shoud maybe be excluded from the data in
                // general?
                $data = <<<JS
    {
        oId: '{$dataObj->getId()}',
        rows: [
            {
                '{$relAlias}': function(){
                    var oData = {$configurator_element->buildJsDataGetter()};
                    if (aRows.length === 0 && {$this->buildJsCheckHidden('jqEl')}) {
                        return {};
                    }
                    oData.rows = aRows;
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
        oId: '{$widgetObj->getId()}',
        rows: (aRows || []).filter(function(oRow, i){
            return {$this->buildJsJqueryElement()}.jspreadsheet('getSelectedRows', true).indexOf(i) >= 0;
        })
    }

JS;         
        }
            
        return <<<JS
        (function(){ 
            var jqEl = {$this->buildJsJqueryElement()};
            var aRows;
            if (jqEl.length === 0) return {};
            aRows = {$this->buildJsConvertArrayToData("jqEl.jspreadsheet('getData', false)")};
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
    var jqCtrl = {$this->buildJsJqueryElement()};
    if (jqCtrl.length === 0) {
        return;
    }
    if (oData !== undefined && Array.isArray(oData.rows)) {
        aData = {$this->buildJsConvertDataToArray('oData.rows')}
        jqCtrl[0].exfWidget._initData = oData.rows;
    } else {
        jqCtrl[0].exfWidget._initData = [];
    }
    if (aData.length === 0) {
        for (var i = 0; i < {$this->getMinSpareRows()}; i++) {
            aData.push([]);
        }
    }
    jqCtrl.jspreadsheet('setData', aData);
    {$this->buildJsResetSelection('jqCtrl')};
    jqCtrl[0].exfWidget.refreshConditionalProperties();
}()

JS;
    }
       
    /**
     * 
     * @param string $arrayOfArraysJs
     * @return string
     */
    protected function buildJsConvertArrayToData(string $arrayOfArraysJs) : string
    {
        return "{$this->buildJsJqueryElement()}[0].exfWidget.convertArrayToData({$arrayOfArraysJs})";
    }
    
    /**
     * 
     * @param string $arrayOfObjectsJs
     * @return string
     */
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
        return "(function(){ {$this->buildJsJqueryElement()}.jspreadsheet('setData', [ [] ]); {$this->buildJsResetSelection($this->buildJsJqueryElement())} })();";
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
        return "jspreadsheet.destroy({$this->buildJsJqueryElement()}[0], false); $('.exf-partof-{$this->getId()}').remove();";
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
        
        $format = '';
        if ($groupSeparator) {
            $format = '#' . $groupSeparator . '##';
        }
        $format .= '0';
        
        $minPrecision = $dataType->getPrecisionMin() ?? 0;
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
                $link = $valueExpr->getWidgetLink($cellWidget);
                $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
                $addLocalValuesToRowJs .= <<<JS
                $oExcelElJs.jspreadsheet.setValueFromCoords({$colIdx}, parseInt({$iRowJs}), {$linkedEl->buildJsValueGetter($link->getTargetColumnId())}, true);

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
    public function buildJsValueGetter($columnName = null, $row = null, bool $filterTargetIsSpreadsheet = false) : string
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

        // handle rowcount col for ui5 facade
        if (mb_strtolower($columnName) === '~rowcount') {
                return $this->buildJsCountRows();
        }

        if ($col === null) {
            throw new WidgetConfigurationError($this->getWidget(), 'Cannot create a filter for column "' . $columnName . '" in the spreadsheet widget "' . $this->getWidget()->getId() . '": column does not exist!');
        }

        $delimiter = $col->isBoundToAttribute() ? $col->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;

        // check if requested data is for filter, or conditionize (disabled if/required if)
        // -> if it is for a self-referencing filter, use value from the current row
        // -> if it is for conditionize, use current value from saved exfwidget row idx
        // otherwise return values from seleted indices

        if ($filterTargetIsSpreadsheet === true){
            // if target is the spreadsheet itself, get filter value from current spreadsheet row
            return <<<JS

(function(){
        var aAllRows = {$this->buildJsDataGetter()}.rows; 
        var aVals = [];

        if (aAllRows[y]['{$col->getDataColumnName()}'] === undefined) {
            console.warn('Column {$col->getDataColumnName()} does not exist in the current spreadsheet'); 
        }
        else {
            aVals.push(aAllRows[y]['{$col->getDataColumnName()}']); 
        }

        return aVals.join('{$delimiter}');
})()
JS;
        } else{
            // if filter target is not the spreadsheet itself, get values from selected indices
            // or return data from saved row idx if exfwidget data getter is not null (for disabled if)
            return <<<JS
(function(jqEl){
        const oWidget = jqEl ? jqEl[0]?.exfWidget : undefined;
        if (oWidget === undefined) {
            return null;
        }
        var aAllRows = {$this->buildJsDataGetter()}.rows; 

        // for disabled if and required-if: use current rowIdx saved in exfwidget 
        if (oWidget && oWidget.bLoaded === true){
            if (oWidget.getValueGetterRow() !== null){
                var iRowIdx = oWidget.getValueGetterRow();

                // skip automatically added empty rows (when adding new rows is allowed)
                if (aAllRows[iRowIdx] === undefined){
                    return "";
                }

                let mValue = aAllRows[iRowIdx]['{$col->getDataColumnName()}'];

                // get current column type
                let iCurrentCol = oWidget.getColumnIndex('{$col->getDataColumnName()}');
                if (iCurrentCol === -1){
                    return "";
                }
                
                let oColModel = oWidget.getColumnModel(iCurrentCol);
                let mParsed = oColModel.parser(mValue);
                var aVals = [];
                aVals.push(mParsed); 
                return aVals.join('{$delimiter}');
            }

            // otherwise: return vals from currently selected rows
            var aSelectedIdxs = jqEl.jspreadsheet('getSelectedRows', true);
            var aVals = [];

            aSelectedIdxs.forEach(function(iRowIdx){
                aVals.push(aAllRows[iRowIdx]['{$col->getDataColumnName()}']);
            })

            return aVals.join('{$delimiter}');
        }

        // if spreadsheet is not loaded, return empty
        return "";
        
})({$this->buildJsJqueryElement()})
JS;
        }
    }
    
    protected function hasSelfReference(ConditionalProperty $condProp) : bool
    {
        foreach ($condProp->getConditions() as $condition) {
            $expr = $condition->getValueLeftExpression();
            if ($expr->isReference()) {
                $targetWidget = $expr->getWidgetLink($condProp->getWidget())->getTargetWidgetId();
                if ($targetWidget === $this->getWidget()->getId()) {
                    return true;
                }
            }
        }
        return false;
    }
    
    protected function buildJsColumnRequiredIf(ConditionalProperty $requiredIf, string $oJExcelJs, string $aCellsJs = 'aCells', string $iColIdxJs) : string
    {
        $conditionsJs = '';
        // check for self-references
        $hasSelfReference = $this->hasSelfReference($requiredIf);

        if ($hasSelfReference) {
            $conditionsJs .= <<<JS

                            {$aCellsJs}.forEach(function(domCell, iRowIdx){
                                var oWidget = {$this->buildJsJqueryElement()}[0].exfWidget;
                                var mVal, mValidationResult;
                                // apply required only if cell is empty 
                                // (and also not a checkbox, as they cannot/should not be required)
                                if (domCell.innerHTML.trim() !== ''){
                                    domCell.classList.remove('exf-spreadsheet-invalid', 'required-if');
                                    return;
                                }
                                
                                oWidget.validateCell(domCell, {$iColIdxJs}, iRowIdx, mVal);
                                if (domCell.classList.contains('exf-spreadsheet-invalid')) {
                                    domCell.classList.add('required-if');
                                } else {
                                    domCell.classList.remove('required-if');
                                }
                            });
    JS;
        }

        return $conditionsJs;
    }
    
    protected function buildJsColumnDisabledIf(ConditionalProperty $condProp, string $aCellsJs) {
        $conditionsJs = '';

        // if it is self-referencing, apply conditions per cell
        // otherwise apply to entire column
        if ($this->hasSelfReference($condProp)) {
            $conditionsJs .= <<<JS
                    
                    var oColOpts = oJExcel.options.columns[iColIdx];
                    if (oColOpts !== undefined && oColOpts.type === 'checkbox'){
                        // checkboxes need to be disabled, not set to readonly
                        {$aCellsJs}.forEach(function(domCell, iRowIdx){
                            {$this->buildJsJqueryElement()}[0].exfWidget.setValueGetterRow(iRowIdx);

                            {$this->buildJsConditionalProperty(
                                $condProp,
                                "$(domCell).children('input').prop('disabled', true); ",
                                "$(domCell).children('input').prop('disabled', false);"
                            )}
                            
                            {$this->buildJsJqueryElement()}[0].exfWidget.setValueGetterRow(null);
                        });

                    } else {
                        // other column types can be set to readonly
                        {$aCellsJs}.forEach(function(domCell, iRowIdx){
                            {$this->buildJsJqueryElement()}[0].exfWidget.setValueGetterRow(iRowIdx);

                            {$this->buildJsConditionalProperty(
                                $condProp,
                                "if (oWidget.hasChanged(iColIdx, iRowIdx)) { oWidget.restoreInitValue(iColIdx, iRowIdx); } domCell.classList.add('readonly'); ",
                                "domCell.classList.remove('readonly');"
                            )}

                            {$this->buildJsJqueryElement()}[0].exfWidget.setValueGetterRow(null);
                        });
                    }
                    
JS;

        } else {
            // If no self-ref
            $conditionsJs .= $this->buildJsConditionalProperty(
                $condProp,
                "{$aCellsJs}.forEach(function(domCell, iRowIdx){
                            if (oWidget.hasChanged(iColIdx, iRowIdx)) {
                                oWidget.restoreInitValue(iColIdx, iRowIdx); 
                            }
                            domCell.classList.add('readonly');
                        });",
                "{$aCellsJs}.forEach(function(domCell){domCell.classList.remove('readonly')});"
            );
        }
        
        return $conditionsJs;
    }

    /**
     * @return string
     */
    protected function buildJsCountRows() : string
    {
        return "{$this->buildJsJqueryElement()}[0].exfWidget.getDataLastLoaded().length";
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

                    // check if current spreadsheet is target to enable live relations
                    $targetWidget = $expr->getWidgetLink($cellWidget)->getTargetWidgetId(); 
                    $isTargetWidget = ($targetWidget === $this->getWidget()->getId()) ? true : false;

                    $valueJs = $linked_element->buildJsValueGetter($link->getTargetColumnId(), null, $isTargetWidget);
                }
                break;
            case $expr->isFormula() === false && $expr->isMetaAttribute() === false:
                $valueJs = "'" . str_replace('"', '\"', $expr->toString()) . "'";
                break;
            default:
                throw new WidgetConfigurationError($this->getWidget(), 'Cannot use expression "' . $expr->toString() . '" in the filter value: only scalar values and widget links supported!');
        }
        
        return $valueJs;
    }
    
    /**
     * @return void
     */
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
    public function buildJsValidator(?string $valJs = null) : string
    {
        // Make sure to avoid errors if JExcel is not (yet) initialized in the DOM
        // This might happen for example if it is placed inside a (temporary) invisible
        // dev.
        $required = $this->getWidget() instanceof iCanBeRequired ? $this->getWidget()->isRequired() : false;
        $bRequiredJs = $required ? 'true' : 'false';
        return <<<JS
        
(function(jqExcel) {
    var bRequired = $bRequiredJs;
    if (jqExcel.length === 0) {
        return bRequired ? false : true;
    }
    jqExcel[0].exfWidget.validateAll();
    return jqExcel.find('.exf-spreadsheet-invalid').length === 0;
})({$this->buildJsJqueryElement()})

JS;
    }
    
    public function buildJsValidationError()
    {
        $required = $this->getWidget() instanceof iCanBeRequired ? $this->getWidget()->isRequired() : false;
        $bRequiredJs = $required ? 'true' : 'false';
        return <<<JS

(function(jqExcel) {
    var bRequired = $bRequiredJs;
    if (jqExcel.length === 0) {
        return bRequired ? false : true;
    }
    jqExcel[0].exfWidget.validateAll();
})({$this->buildJsJqueryElement()})

JS;
    }
    
    protected function buildJsCheckHidden(string $jqElement) : string
    {
        return "($jqElement.parents().filter(':visible').length !== $jqElement.parents().length)";
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsEmpty() : string
    {
        return $this->buildJsDataSetter('[]');
    }
    
    /**
     * Return a JS snippet to call a widget function if that function is supported this trait and NULL otherwise.
     * 
     * In classes, that use this trait, you can include this method like this:
     * 
     * ```
     * public function buildJsCallFunction(string $functionName = null, array $parameters = [], ?string $jsRequestData = null) : string
     * {
     *     if (null !== $js = $this->buildJsCallFunctionOfJExcel($functionName, $parameters)) {
     *         return $js;
     *     }
     *     return parent::buildJsCallFunction($functionName, $parameters);
     * }
     * 
     * ```
     * 
     * @param string $functionName
     * @param array $parameters
     * @return string|null
     */
    protected function buildJsCallFunctionOfJExcel(string $functionName = null, array $parameters = [], ?string $jsRequestData = null) : ?string
    {
        switch (true) {
            case $functionName === DataTable::FUNCTION_EMPTY:
                return "setTimeout(function(){ {$this->buildJsEmpty()} }, 0);";
        }
        return null;
    }
    
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        $disableJs = $trueOrFalse ? 'true' : 'false';
        return <<<JS
        
        (function(jqEl, bDisable){
            if (jqEl.length === 0) {
                return;
            }
            jqEl[0].exfWidget.setDisabled($disableJs);
        })({$this->buildJsJqueryElement()}, $disableJs);

JS;
    }
    
    protected function addOnInitScript(string $js) : self
    {
        $this->onInitScripts[] = $js;
        return $this;
    }
    
    protected function buildJsOnInitScript() : string
    {
        $scripts = array_unique($this->onInitScripts);
        return implode("\n", $scripts);
    }
}