<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\DataColumnTransposed;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\CommonLogic\DataSheets\DataAggregation;

/**
 * Provides methods to generate JS code to transpose data columns in DataMatrix widgets
 * 
 * The logic was initially developed for the jEasyUI facade. The code is far from being
 * elegant, but has been tested in many constellations.
 * 
 * @author andrej.kabachnik
 *
 */
trait JqueryDataTransposerTrait {
    
    /**
     * Returns the JS code to generate an object of column models
     * 
     * ```
     *   data_column_name: {
     *      sWidgetId: ...,
     *      sDataColumnName: ...,
     *      sAttributeAlias: ...,
     *      sCaption: ...,
     *      sHint: ...,
     *      bHidden: ...,
     *      sAlign: null,
     *      sFooterAggregator: ...,
     *      fnFormatter: ...,
     *      bTransposeData: ..., // true if this column contains data to be transposed
     *      sTransposeWithLabelsColumnKey: ..., // name of column to use as labels when transposing
     *      iTransposedToSubrow: ..., // will contain the subrow index after transposing if multiple columns are transposed
     *      bTransposedColumn: false, // true if this column was generated while transposing (set automatically)
     *      sTransposedColumnRole: null, // data, subRowIndex, subRowTitle, subRowTotal (set automatically)
     *      aTransposedDataKeys: [],
     *      sTransposedLabelKey: '', // name of the original label column for generated data columns
     *      aReplacedWithColumnKeys: [], // names of columns that replace this (label) column after transposing
     *   }
     *   
     * ```
     * 
     * @return string
     */
    protected function buildJsTransposerColumnModels() : string
    {
        $colModelsJs = '';
        $widget = $this->getWidget();
        foreach ($widget->getColumns() as $col) {
            $colKey = $col->getDataColumnName() ? $col->getDataColumnName() : $col->getId();
            $colModelsJs .= "
        '{$colKey}': {
            sWidgetId: '{$col->getId()}',
            sDataColumnName: {$this->escapeString($col->isBoundToDataColumn() ? $col->getDataColumnName() : '')},
            sAttributeAlias: {$this->escapeString($col->isBoundToAttribute() ? $col->getAttributeAlias() : '')},
            sCaption: {$this->escapeString($col->getCaption())},
            sHint: {$this->escapeString($col->getHint())},
            bHidden: " . ($col->isHidden() ? 'true' : 'false') . ",
            sAlign: null,
            sFooterAggregator: {$this->escapeString($col->hasFooter() === true && $col->getFooter()->hasAggregator() === true ? $col->getFooter()->getAggregator()->exportString() : '')},
            fnFormatter: function(value){return {$this->getFacade()->getDataTypeFormatter($col->getCellWidget()->getValueDataType())->buildJsFormatter('value')} },
            bTransposeData: " . ($col instanceof DataColumnTransposed ? 'true' : 'false') . ",
            sTransposeWithLabelsColumnKey: {$this->escapeString($col instanceof DataColumnTransposed ? $widget->getColumnByAttributeAlias($col->getLabelAttributeAlias())->getDataColumnName() : '')},
            bTransposedColumn: false,
            sTransposedColumnRole: null,
            aTransposedDataKeys: [],
            sTransposedLabelKey: '',
            aReplacedWithColumnKeys: [],
        },";
        };
        $colModelsJs = '{' . $colModelsJs . '}';
        return $colModelsJs;
    }
    
    /**
     * Returns the JS code to transposed provided data into a result object with the following structure:
     * 
     * ```
     *  {
     *      bTransposed: ,
     *      oDataOriginal: ...,
     *      oDataTransposed: ...,
     *      oColModelsOriginal: ...,
     *      oColModelsTransposed: ...
     *  }
     * 
     * ```
     * 
     * @param string $dataJs
     * @param string $colModelsJs
     * @return string
     */
    protected function buildJsTranspose(string $dataJs, string $colModelsJs) : string
    {
        $visible_cols = array();
        $data_cols = array();
        $data_cols_totals = array();
        $label_cols = array();
        $widget = $this->getWidget();
        foreach ($widget->getColumns() as $col) {
            if ($col instanceof DataColumnTransposed) {
                $data_cols[] = $col->getDataColumnName();
                $label_cols[$col->getLabelAttributeAlias()][] = $col->getDataColumnName();
                if ($col->hasFooter() === true && $col->getFooter()->hasAggregator() === true) {
                    $data_cols_totals[$col->getDataColumnName()] = $col->getFooter()->getAggregator()->exportString();
                }
            } elseif (! $col->isHidden()) {
                $visible_cols[] = $col->getDataColumnName();
            }
        }
        $visible_cols = "'" . implode("','", $visible_cols) . "'";
        $data_cols = "'" . implode("','", $data_cols) . "'";
        $label_cols = json_encode($label_cols);
        $data_cols_totals = json_encode($data_cols_totals);
        $aggr_function_type = DataTypeFactory::createFromPrototype($this->getWorkbench(), AggregatorFunctionsDataType::class);
        $aggr_names = json_encode($aggr_function_type->getLabels());

        $systemColNames = [];
        foreach ($widget->getMetaObject()->getAttributes()->getSystem() as $attr) {
            $systemColNames[] = DataColumn::sanitizeColumnName($attr->getAlias());
            $systemColNames[] = DataColumn::sanitizeColumnName(DataAggregation::addAggregatorToAlias($attr->getAlias(), $attr->getDefaultAggregateFunction()));
        }
        $systemColNames = json_encode($systemColNames);
        
        return <<<JS
(function(oData, oColModels) {
    var oResult = {
        bTransposed: false,
        oDataOriginal: $.extend({}, oData),
        oDataTransposed: null,
        oColModelsOriginal: oColModels,
        oColModelsTransposed: {}
    };
    var aDataCols = [ {$data_cols} ]; // [transpColName1, transpColName2, ...]
    var aVisibleCols = [ {$visible_cols} ];
    var oDataColsTotals = {$data_cols_totals}; // {transpColName2: SUM, ...}
    var oAggrLabels = $aggr_names; // {SUM: 'Sum', ...}
    var oLabelCols = {$label_cols}; // {labelAttrAlias1: [transpColName1, transpColName2], labelAttrAlias2: [...], ...}
    var aSystemCols = $systemColNames;
    var aRows = oData.rows;
    
	var aRowsNew = [];
	var oRowKeys = {};
    
    var oColCurrent;
    for (fld in oColModels) {
        var oCol = oColModels[fld];
        switch (true) {
            // If this column is to be transposed
    		case oCol.bTransposeData === true:
    			oData.transposed = false;
                break;
            // If it is one of the label columns
    		case oLabelCols[fld] !== undefined:
                oCol.aReplacedWithColumnKeys = [];
    			// Add a subtitle column to show a caption for each subrow if there are multiple
    			if (aDataCols.length > 1){                    
                    oResult.oColModelsTransposed['_subRowIndex'] = {
                        bTransposedColumn: true,
                        sTransposedColumnRole: 'subRowIndex',
        				sDataColumnName: '_subRowIndex',
                        sCaption: '',
        				sAlign: 'right',
        				bHidden: true
                    };
                    oCol.aReplacedWithColumnKeys.push('_subRowIndex');

                    oResult.oColModelsTransposed[fld+'_subtitle'] = $.extend(true, {}, oCol, {
                        bTransposedColumn: true,
                        sTransposedColumnRole: 'subRowTitle',
        				sDataColumnName: fld+'_subtitle',
                        sCaption: '',
        				sAlign: 'right',
                        fnFormatter: null
                    });
                    oCol.aReplacedWithColumnKeys.push(fld+'_subtitle');
    			}
    			// Create a column for each value if it is the label column
    			var labels = [];
    			for (var l=0; l<aRows.length; l++){
    				if (labels.indexOf(aRows[l][fld]) == -1){
    					labels.push(aRows[l][fld]);
    				}
    			}
    			for (var l=0; l<labels.length; l++){
    				let label = labels[l];
					if (typeof label !== 'string') {
						label = String(label);
					}
					label = label.replaceAll('-', '_').replaceAll(':', '_');
    			    oResult.oColModelsTransposed[label] = $.extend(true, {}, oCol, {
                        sDataColumnName: label,
                        sCaption: (oCol.fnFormatter ? oCol.fnFormatter(labels[l]) : labels[l]),
                        sHint: oCol.sCaption + ' ' + (oCol.fnFormatter ? oCol.fnFormatter(labels[l]) : labels[l]),
        				bTransposedColumn: true,
                        sTransposedColumnRole: 'data',
                        aTransposedDataKeys: oLabelCols[fld],
                        sTransposedLabelKey: fld,
                        fnFormatter: null
                    });
                    oCol.aReplacedWithColumnKeys.push(label);
    			}
    			// Create a totals column if there are totals
                // The footer of the totals column will contain the overall total provided by the server
    			if (oDataColsTotals !== {}){
    				var totals = [];
    				for (var tfld in oDataColsTotals){
    					var tfunc = oDataColsTotals[tfld];
    					if (totals.indexOf(tfunc) === -1){
    						totals.push(tfunc);
                            oData.footer[0][oCol.sDataColumnName] = oData.footer[0][tfld];

                            oResult.oColModelsTransposed[fld+'_'+tfunc] = $.extend(true, {}, oCol, {
                                bTransposedColumn: true,
                                sTransposedColumnRole: 'subRowTotal',
        				        sDataColumnName: fld+'_'+tfunc,
        						sCaption: oAggrLabels[tfunc],
        						sAlign: 'right',
                            });
                            oCol.aReplacedWithColumnKeys.push(fld+'_'+tfunc);
    					}
    				}
    			}
                oResult.oColModelsTransposed[oCol.sDataColumnName] = oCol;
    		    break;
            // Regular columns
            default:
                oResult.oColModelsTransposed[oCol.sDataColumnName] = oCol;
		}
    } // for (fld in oColModels)
    
    if (oData.transposed === false){
    	for (var i=0; i<aRows.length; i++){
    		var newRowId = '';
    		var newRow = {};
    		var newColVals = {};
    		var newColId = '';
            var newColGroup;
    		for (var fld in aRows[i]){
    			var val = aRows[i][fld];
                switch (true) {
    			    case oLabelCols[fld] != undefined:
        				if (typeof val !== 'string') {
        					val = String(val);
        				}
        				val = val.replaceAll('-', '_').replaceAll(':', '_');
        				newColId = val;
        				newColGroup = fld;
                        break;
    			    case aDataCols.indexOf(fld) > -1:
    				    newColVals[fld] = val;
                        break;
    			    case aVisibleCols.indexOf(fld) > -1:
                    case oColModels[fld] === undefined && aSystemCols.includes(fld) === false:
        				newRowId += val;
        				newRow[fld] = val;
                        break;
    			
    			    // TODO save UID and other system attributes to some invisible data structure
    			}
    		}
    		
    		var subRowCounter = 0;
    		for (var fld in newColVals){
                var oColOrig = oResult.oColModelsOriginal[fld];
                oColOrig.iTransposedToSubrow = subRowCounter++;
    			if (oRowKeys[newRowId+fld] == undefined){
    				oRowKeys[newRowId+fld] = $.extend(true, {}, newRow);
    				oRowKeys[newRowId+fld]['_subRowIndex'] = oColOrig.iTransposedToSubrow;
    			}
    			oRowKeys[newRowId+fld][newColId] = oColOrig.fnFormatter ? oColOrig.fnFormatter(newColVals[fld]) : newColVals[fld];
                oRowKeys[newRowId+fld][newColGroup+'_subtitle'] = oResult.oColModelsOriginal[fld].sCaption;
    			if (oDataColsTotals[fld] != undefined){
    				var newVal = parseFloat(newColVals[fld]);
    				var oldVal = oRowKeys[newRowId+fld][newColGroup+'_'+oDataColsTotals[fld]];
                    var oldTotal = (oData.footer[0][newColId] || 0);
    				oldVal = oldVal ? oldVal : 0;
    				switch (oDataColsTotals[fld]){
    					case 'SUM':
    						oRowKeys[newRowId+fld][newColGroup+'_'+oDataColsTotals[fld]] = oldVal + newVal;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal + newVal;
                            }
    						break;
    					case 'MAX':
    						oRowKeys[newRowId+fld][newColGroup+'_'+oDataColsTotals[fld]] = oldVal < newVal ? newVal : oldVal;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal < newVal ? newVal : oldTotal;
                            }
    						break;
    					case 'MIN':
    						oRowKeys[newRowId+fld][newColGroup+'_'+oDataColsTotals[fld]] = oldVal > newVal ? newVal : oldVal;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal > newVal ? newVal : oldTotal;
                            }
    						break;
    					case 'COUNT':
    						oRowKeys[newRowId+fld][newColGroup+'_'+oDataColsTotals[fld]] = oldVal + 1;
                            if (aDataCols.length === 1){
                                oData.footer[0][newColId] = oldTotal + 1;
                            }
    						break;
    					// TODO add more totals
    				}
    			}
    		}
    	}
    	for (var i in oRowKeys){
    		aRowsNew.push(oRowKeys[i]);
    	}
    	
    	oData.rows = aRowsNew;
    	oData.transposed = true;
        oResult.bTransposed = true;
    
        oResult.oDataTransposed = oData;
    }

    return oResult;

})($dataJs, $colModelsJs);    

JS;        
    }
}