<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Widgets\DataTable;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\DataTableResponsive;
use exface\Core\Widgets\DataColumn;

/**
 * This trait contains common methods for facade elements using the jQuery DataTables library.
 *
 * @see http://www.datatables.net
 *
 * @method DataTable getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryDataTablesTrait {
    
    use JqueryAlignmentTrait;

    private $row_details_expand_icon = 'fa-plus-square-o';

    private $row_details_collapse_icon = 'fa-minus-square-o';
    
    private $on_load_success = '';
    
    /**
     * 
     * @return string
     */
    protected function buildHtmlTable($css_class = '')
    {
        $widget = $this->getWidget();
        $thead = '';
        $tfoot = '';
        
        // Column headers
        /* @var $col \exface\Core\Widgets\DataColumn */
        foreach ($widget->getColumns() as $col) {
            $thead .= '<th title="' . $col->getHint() . '">' . $col->getCaption() . '</th>';
            if ($widget->hasColumnFooters()) {
                $tfoot .= '<th class="text-right"></th>';
            }
        }
        
        // Extra column for the multiselect-checkbox
        if ($widget->getMultiSelect()) {
            $checkbox_header = '<th onclick="javascript: if(!$(this).parent().hasClass(\'selected\')) {' . $this->getId() . '_table.rows().select(); $(\'#' . $this->getId() . '_wrapper\').find(\'th.select-checkbox\').parent().addClass(\'selected\');} else{' . $this->getId() . '_table.rows().deselect(); $(\'#' . $this->getId() . '_wrapper\').find(\'th.select-checkbox\').parent().removeClass(\'selected\');}"></th>';
            $thead = $checkbox_header . $thead;
            if ($tfoot) {
                $tfoot = $checkbox_header . $tfoot;
            }
        }
        
        // Extra column for expand-button if rows have details
        if ($widget->hasRowDetails()) {
            $thead = '<th></th>' . $thead;
            if ($tfoot) {
                $tfoot = '<th></th>' . $tfoot;
            }
        }
        
        // Extra column for expand-button if rows have details
        if ($this->isResponsive()) {
            $thead = '<th></th>' . $thead;
            if ($tfoot) {
                $tfoot = '<th></th>' . $tfoot;
            }
        }
        
        if ($tfoot) {
            $tfoot = '<tfoot>' . $tfoot . '</tfoot>';
        }
        
        if ($this->isResponsive()) {
            $css_class .= ' responsive';
        }
        
        return <<<HTML
        <table id="{$this->getId()}" class="{$css_class}" cellspacing="0" width="100%">
            <thead>
                {$thead}
            </thead>
            {$tfoot}
        </table>
HTML;
    }

    /**
     * Returns JS code adding a click-event handler to the expand-cell of each row of a table with row details,
     * that will create and show an additional row for the details of the clicked row.
     *
     * The contents of the detail-row will be loaded via POST request.
     *
     * @return string
     */
    protected function buildJsRowDetails()
    {
        $output = '';
        $widget = $this->getWidget();
        $collapse_icon_selector = '.' . str_replace(' ', '.', $this->getRowDetailsCollapseIcon());
        $expand_icon_selector = '.' . str_replace(' ', '.', $this->getRowDetailsExpandIcon());
        $headers = ! empty($this->getAjaxHeaders()) ? json_encode($this->getAjaxHeaders()) : '{}';
        
        if ($widget->hasRowDetails()) {
            $output = <<<JS
	// Add event listener for opening and closing details
	$('#{$this->getId()} tbody').on('click', 'td.details-control', function () {
		var tr = $(this).closest('{$this->buildCssSelectorDataRows()}');
		var row = {$this->getId()}_table.row( tr );
		
		if ( row.child.isShown() ) {
			// This row is already open - close it
			row.child.hide();
			tr.removeClass('shown');
			tr.find('{$collapse_icon_selector}').removeClass('{$this->getRowDetailsCollapseIcon()}').addClass('{$this->getRowDetailsExpandIcon()}');
			$('#detail'+row.data().id).remove();
			{$this->getId()}_table.columns.adjust();
		} else {
			// Open this row
			row.child('<div id="detail'+row.data().{$widget->getMetaObject()->getUidAttributeAlias()}+'"></div>').show();
            // Fetch content
            var headers = {$headers};
            headers['Subrequest-ID'] = row.data().{$widget->getMetaObject()->getUidAttributeAlias()};
			$.ajax({
				url: '{$this->getAjaxUrl()}',
				method: 'post',
                headers: headers,
				data: {
					action: '{$widget->getRowDetailsAction()}',
					resource: '{$widget->getPage()->getAliasWithNamespace()}',
					element: '{$widget->getRowDetailsContainer()->getId()}',
					prefill: {
						oId:"{$widget->getMetaObject()->getId()}",
						rows:[
							{ {$widget->getMetaObject()->getUidAttributeAlias()}: row.data().{$widget->getMetaObject()->getUidAttributeAlias()} }
						],
						filters: {$this->buildJsDataFilters()}
					}
				},
				dataType: "html",
				success: function(data){
					$('#detail'+row.data().{$widget->getMetaObject()->getUidAttributeAlias()}).append(data);
					{$this->getId()}_table.columns.adjust();
				},
				error: function(jqXHR, textStatus, errorThrown ){
					{$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')}
				}
			});
			tr.next().addClass('detailRow unselectable');
			tr.addClass('shown');
			tr.find('{$expand_icon_selector}').removeClass('{$this->getRowDetailsExpandIcon()}').addClass('{$this->getRowDetailsCollapseIcon()}');
		}
	} );
JS;
        }
        return $output;
    }

    public function getRowDetailsExpandIcon()
    {
        return $this->buildCssIconClass(Icons::PLUS_SQUARE_O);
    }

    public function getRowDetailsCollapseIcon()
    {
        return $this->buildCssIconClass(Icons::MINUS_SQUARE_O);
    }
    
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        if (is_null($action)) {
            $rows = $this->getId() . "_table.rows().data()";
        } elseif ($action instanceof iReadData) {
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            return $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget())->buildJsDataGetter($action);
        } elseif ($this->isEditable() && $action->implementsInterface('iModifyData')) {
            // TODO
            $rows = "[]";
        } else {
            $rows = "Array.prototype.slice.call(" . $this->getId() . "_table.rows({selected: true}).data())";
        }
        return "{oId: '" . $this->getWidget()->getMetaObject()->getId() . "', rows: " . $rows . "}";
    }
    
    /**
     *
     * @return boolean
     */
    protected function isLazyLoading()
    {
        return $this->getWidget()->getLazyLoading(true);
    }
    
    public function buildJsRefresh($keep_pagination_position = false)
    {
        if (! $this->isLazyLoading()) {
            return "{$this->getId()}_table.search({$this->getFacade()->getElement($this->getWidget()->getQuickSearchWidget())->buildJsValueGetter()}, false, true).draw();";
        } else {
            return $this->getId() . "_table.draw(" . ($keep_pagination_position ? "false" : "true") . ");";
        }
    }
    
    public function buildJsColumnDef(\exface\Core\Widgets\DataColumn $col)
    {
        // Data type specific formatting
        $formatter_js = '';
        $cellWidget = $col->getCellWidget();
        $cellTpl = $this->getFacade()->getElement($cellWidget);
        if (($cellTpl instanceof JsValueDecoratingInterface) && $cellTpl->hasDecorator()) {
            $formatter_js = $cellTpl->buildJsValueDecorator('data');
        } elseif ($cellWidget instanceof Input) {
            // FIXME how to place the data into the cell editor? Maybe use the data setter?
            $cellData = $col->isBoundToAttribute() ? 'data' : "''";
            $cellHtml = $cellTpl->buildHtml();
            $cellHtml = preg_replace('/id="[^"]*"/', '', $cellHtml);
            $cellHtml = preg_replace('/name="[^"]*"/', '', $cellHtml);
            $cellHtml = preg_replace('/\s\s+/', ' ', $cellHtml);
            $formatter_js = "'" . $cellHtml . "'";
        }
        
        // Default values
        if ($cellWidget instanceof iTakeInput) {
            $defaultVal = $cellWidget->getValueWithDefaults();
        } else {
            $defaultVal = '';
        }
        
        // In datatables with remote source sorting is allways performed remotely, so
        // it cannot be done for columns without attribute binding (the server cannot
        // sort those)
        $sortable = $col->isBoundToAttribute() ? ($col->isSortable() ? 'true' : 'false') : 'false';
        
        $output = '{
							name: "' . $col->getAttributeAlias() . '"
                            , data: ' . ($col->isBoundToAttribute() ? '"' . $col->getDataColumnName() . '"' : 'null') . '
                            ' . ($col->isHidden() || $col->getVisibility() === EXF_WIDGET_VISIBILITY_OPTIONAL ? ', visible: false' : '') . '
                            ' . ($col->getWidth()->isFacadeSpecific() ? ', width: "' . $col->getWidth()->getValue() . '"': '') . '
                            , className: "' . $this->buildCssColumnClass($col) . '"' . '
                            , orderable: ' . $sortable . '
                            , defaultContent: "' . $defaultVal . '"
                            ' . ($formatter_js ? ", render: function(data, type, row){try {return " . $formatter_js . "} catch (e) {return data;} }" : '') . '
                            
                    }';
        
        return $output;
    }
    
    
    
    protected function buildJsQuicksearch()
    {
        $widget = $this->getWidget();
        $output = <<<JS
        	$('#{$this->getId()}_quickSearch_form').on('submit', function(event) {
        		{$this->buildJsRefresh(false)}
        		event.preventDefault();
        		return false;
        	});
        	
        	$('#{$this->getFacade()->getElement($widget->getQuickSearchWidget())->getId()}').on('change', function(event) {
        		{$this->buildJsRefresh(false)}
        	});
JS;
		return $output;
    }
    
    public function buildJsValueGetter($dataColumnName = null, $rowNr = null)
    {
        $widget = $this->getWidget();
        $output = $this->getId() . "_table";
        if (is_null($rowNr)) {
            $output .= ".rows('.selected').data()";
        } else {
            // TODO
        }
        
        $uid_column = $widget->getUidColumn();
        if ($dataColumnName === null) {
            $column_widget = $uid_column;
        } else {
            // FIXME #uid-column-missing remove this ugly if once UID column are added to tables by default again
            if ($dataColumnName == $uid_column->getDataColumnName()) {
                $column_widget = $uid_column;
            } else {
                $column_widget = $widget->getColumnByDataColumnName($dataColumnName);
            }
        }
        
        if (! $column_widget) {
            throw new FacadeOutputError('Column "' . $dataColumnName . '" of ' . $widget->getWidgetType() . ' "' . $widget->getCaption() . '" required for in-page scripting is missing!');
        }
        
        $column_name = $column_widget->getDataColumnName();
        if ($column_widget->isBoundToAttribute()) {
            $delimiter = $column_widget->getAttribute()->getValueListDelimiter();
        } else {
            $delimiter = EXF_LIST_SEPARATOR;
        }
        return "{$output}.pluck('{$column_name}').join('{$delimiter}')";
    }
    
    public function buildJsValueSetter($value, $dataColumnName = null, $rowNr = null)
    {
        if ($dataColumnName === null) {
            $dataColumnName = $this->getWidget()->getUidColumn()->getDataColumnName();
        }
        
        if ($rowNr === null) {
            $rowNr = $this->getId() . "_table.rows('.selected').indexes()[0]";
        }
        
        return $this->getId() . "_table.cell({$rowNr}, '{$dataColumnName}:name').data({$value}).nodes().to$().fadeOut(0, function(){ $(this).fadeIn(200); });";
    }
    
    /**
     * Returns a list of CSS classes to be used for the specified column: e.g.
     * alignment, etc.
     *
     * @param \exface\Core\Widgets\DataColumn $col
     * @return string
     */
    public function buildCssColumnClass(\exface\Core\Widgets\DataColumn $col)
    {
        return 'text-' . $this->buildCssTextAlignValue($col->getAlign());
    }
    
    public function addOnLoadSuccess($script)
    {
        $this->on_load_success .= $script;
    }
    
    public function getOnLoadSuccess()
    {
        return $this->on_load_success;
    }
    
    protected function buildJsDataSource($js_filters = '')
    {
        $widget = $this->getWidget();
        $configurator_element = $this->getFacade()->getElement($widget->getConfiguratorWidget());
        
        $headers = ! empty($this->getAjaxHeaders()) ? 'headers: ' . json_encode($this->getAjaxHeaders()) . ',' : '';
        
        $ajax_data = <<<JS
			function ( d ) {
				{$this->buildJsBusyIconShow()}
				var filtersOn = false;
				d.action = '{$widget->getLazyLoadingActionAlias()}';
				d.resource = "{$widget->getPage()->getAliasWithNamespace()}";
				d.element = "{$widget->getId()}";
				d.object = "{$this->getWidget()->getMetaObject()->getId()}";
                d.q = {$this->getFacade()->getElement($widget->getQuickSearchWidget())->buildJsValueGetter()};
				d.data = {$configurator_element->buildJsDataGetter()};
				
				{$this->buildJsFilterIndicatorUpdater()}
			}
JS;
				
				$result = '';
				if ($this->isLazyLoading()) {
				    $result = <<<JS
		"serverSide": true,
		"ajax": {
			"url": "{$this->getAjaxUrl()}",
			"type": "POST",
            {$headers}
			"data": {$ajax_data},
			"error": function(jqXHR, textStatus, errorThrown ){
				{$this->buildJsBusyIconHide()}
				{$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')}
			},
            "beforeSend": function(jqXHR, settings) {
                jqself = $("#{$this->getId()}");
                if (jqself.data("_skipNextLoad") === true) {
                    jqself.data("_skipNextLoad", false);
                    {$this->buildJsBusyIconHide()}
                    return false;
                }
                if (! {$configurator_element->buildJsValidator()}) {
                    {$this->buildJsBusyIconHide()}
                    console.warn('Invalid filters set for {$this->getId()}: skipping reload!');
                    return false;
                }
            }
		}
JS;
				} else {
				    // Data embedded in the code of the DataGrid
				    if ($widget->getValuesDataSheet()) {
				        $data = $widget->getValuesDataSheet();
				    }
				    
				    $data = $widget->prepareDataSheetToRead($data ? $data : null);
				    
				    if (! $data->isFresh()) {
				        $data->dataRead();
				    }
				    $result = <<<JS
			"ajax": function (data, callback, settings) {
				callback(
						{$this->getFacade()->encodeData($this->getFacade()->buildResponseData($data, $widget))}
						);
				}
JS;
				}
				
				return $result . ',';
    }
    
    protected function buildJsTableInit()
    {
        $widget = $this->getWidget(); 
        
        $columns = array();
        $column_number_offset = 0;
        
        // Multiselect-Checkbox
        if ($widget->getMultiSelect()) {
            $columns[] = '
					{
						"className": "select-checkbox",
						"width": "10px",
						"orderable": false,
						"data": null,
						"targets": 0,
						"defaultContent": ""
					}
					';
            $column_number_offset ++;
        }
        
        // Expand-Button for row details
        if ($widget->hasRowDetails()) {
            $columns[] = '
					{
						"class": "details-control text-center",
						"width": "10px",
						"orderable": false,
						"data": null,
						"defaultContent": \'<i class="fa ' . $this->getRowDetailsExpandIcon() . '"></i>\'
					}
					';
            $column_number_offset ++;
        }
        
        // Expand-Button for responsive tables with collapsible overflow
        if ($this->isResponsive()) {
            $columns[] = '
					{
						"class": "details-control text-center",
						"orderable": false,
						"data": null,
						"defaultContent": \'<i class="fa fa-chevron-down"></i>\'
					}
					';
            $column_number_offset ++;
        }
        
        // Sorters
        $default_sorters = '';
        foreach ($widget->getSorters() as $sorter) {
            $column_exists = false;
            $sorter_alias = $sorter->getProperty('attribute_alias');
            $sorter_dir = strcasecmp($sorter->getProperty('direction'), SortingDirectionsDataType::ASC) === 0 ? 'asc' : 'desc';
            foreach ($widget->getColumns() as $nr => $col) {
                if ($col->getAttributeAlias() == $sorter_alias) {
                    $column_exists = true;
                    $default_sorters .= '[ ' . ($nr + $column_number_offset) . ', "' . $sorter_dir . '" ], ';
                }
            }
            if (! $column_exists) {
                $widget->addColumn($widget->createColumnFromAttribute($widget->getMetaObject()->getAttribute($sorter_alias), null, true));
                $default_sorters .= '[ ' . ($nr + 1 + $column_number_offset) . ', "' . $sorter_dir . '" ], ';
            }
        }
        // Remove tailing comma
        if ($default_sorters)
            $default_sorters = substr($default_sorters, 0, - 2);
        
        // Selection configuration
        if ($this->getWidget()->getMultiSelect()) {
            $select_options = 'style: "multi"';
        } else {
            $select_options = 'style: "single"';
        }
        
        // configure pagination
        if ($widget->isPaged()) {
            $paging_options = ', pageLength: ' . $widget->getPaginator()->getPageSize($this->getFacade()->getConfig()->getOption('WIDGET.DATATABLE.PAGE_SIZE'));
        } else {
            $paging_options = ', paging: false';
        }
        
        // columns && their footers
        $footer_callback = '';
        foreach ($widget->getColumns() as $nr => $col) {
            $columns[] = $this->buildJsColumnDef($col);
            $nr = $nr + $column_number_offset;
            if ($col->hasFooter()) {
                $footer_callback .= <<<JS
	            // Total over all pages
	            var data;
                var loadFromServer = api.init().serverSide;
                if (loadFromServer){
                    data = api.ajax.json();
                } else {
                    data = api.data() ? api.data() : {};
                }
	            if (data.footer && data.footer[0]['{$col->getDataColumnName()}']){
		            total = api.ajax.json().footer[0]['{$col->getDataColumnName()}'];
		            // Update footer
		            $( api.column( {$nr} ).footer() ).html( total );
	           	}
JS;
            }
        }
        $columns = implode(', ', $columns);
        
        if ($footer_callback) {
            $footer_callback = '
		, "footerCallback": function ( row, data, start, end, display ) {
			var api = this.api(), data;
        
            // Remove the formatting to get integer data for summation
            var intVal = function ( i ) {
                return typeof i === \'string\' ?
                    i.replace(/[\$,]/g, \'\')*1 :
                    typeof i === \'number\' ?
                        i : 0;
            };
			' . $footer_callback . '
		}';
        }
        
        if ($widget->getContextMenuEnabled() && $widget->hasButtons()){
            $context_menu_js = $this->buildJsContextMenu();
        }
        
        if ($widget->hasRowGroups()){
            $grouper = $widget->getRowGrouper();
            if ($grouper->getShowCounter()){
                $rowGroupConter = "' ('+rows.count()+')'";
            } else {
                $rowGroupConter = "''";
            }
            
            if ($grouper->getExpandFirstGroupOnly() === true) {
                $this->addOnLoadSuccess("setTimeout(function(){ $('#{$this->getId()} > tbody > tr').not(':first-child').trigger('click');}, 0);\n");
            } elseif ($grouper->getExpandAllGroups() === false){
                $this->addOnLoadSuccess("setTimeout(function(){ $('#{$this->getId()} > tbody > tr').trigger('click');}, 0);\n");
            }
            
            $rowGroup = <<<JS
        , "rowGroup": {
            dataSrc: '{$grouper->getGroupByColumn()->getDataColumnName()}',
            startRender: function ( rows, group ) {
                var counter = {$rowGroupConter} ;
                return $('<tr onclick="{$this->buildJsFunctionPrefix()}RowGroupToggle(this);"/>')
                    .append( '<td colspan="'+{$this->getId()}_table.columns(':visible').count()+'"><i class="{$this->getRowDetailsCollapseIcon()}"></i> '+group+counter+'</td>' );
            }
        }
JS;
            
        }
        
        return <<<JS

    $('#{$this->getId()}').DataTable( {
		"dom": 't'
		, deferRender: true
		, processing: true
		, select: { {$select_options} }
		{$paging_options}
		, scrollX: true
		, scrollXollapse: true
		, {$this->buildJsDataSource()}
		language: {
            zeroRecords: "{$widget->getEmptyText()}"
        }
		, columns: [{$columns}]
		, order: [ {$default_sorters} ]
        {$rowGroup}
		, drawCallback: function(settings, json) {
			$('#{$this->getId()} tbody tr').on('contextmenu', function(e){
				{$this->getId()}_table.row($(e.target).closest('{$this->buildCssSelectorDataRows()}')).select();
			});
			$('#{$this->getId()}').closest('.exf-grid-item').trigger('resize');
            {$context_menu_js}
			if({$this->getId()}_table){
				{$this->getId()}_drawPagination();
				{$this->getId()}_table.columns.adjust();
			}
			{$this->buildJsDisableTextSelection()}
			{$this->buildJsBusyIconHide()}
			{$this->getOnLoadSuccess()}
		}
		{$footer_callback}
		{$this->buildJsOptionResponsive()}
	} );

JS;
    }
		
    protected function buildJsOptionResponsive() : string
    {
        $js = '';
        if ($this->isResponsive()) {
            $widget = $this->getWidget();
        
            $display = $widget->getOverflowCollapsed() ? '$.fn.dataTable.Responsive.display.childRow' : '$.fn.dataTable.Responsive.display.childRowImmediate';
        
            return <<<JS
, responsive: {
            details: {
                display: {$display},
                type: 'column'
            }
}

JS;
        }
        return $js;
    }
    
    /**
     * Returns JS code selecting those rows, that should be selected wen the table is created.
     * 
     * @return string
     */
    protected function buildJsInitialSelection()
    {
        if ($this->getWidget()->getMultiSelect() && $this->getWidget()->getMultiSelectAllSelected()) {
                $initial_row_selection = $this->getId() . '_table.rows().select(); $(\'#' . $this->getId() . '_wrapper\').find(\'th.select-checkbox\').parent().addClass(\'selected\');';
        }
        return $initial_row_selection;
    }
    
    protected function buildJsClickListeners()
    {
        $widget = $this->getWidget();
        $leftclick_script = '';
        $dblclick_script = '';
        $rightclick_script = '';
        
        // Click actions
        // Single click. Currently only supports one double click action - the first one in the list of buttons
        if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
            $leftclick_script = $this->getFacade()->getElement($leftclick_button)->buildJsClickFunctionName() . '()';
        }
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
            $dblclick_script = $this->getFacade()->getElement($dblclick_button)->buildJsClickFunctionName() . '()';
        }
        
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
            $rightclick_script = $this->getFacade()->getElement($rightclick_button)->buildJsClickFunctionName() . '()';
        }
        
        // Need to wait for the default handlers to run before performing our click handlers - that's why some
        // are wrapped in setTimeout().
        return <<<JS

	$('#{$this->getId()} tbody').on( 'click', '{$this->buildCssSelectorDataRows()}', function () {
	    setTimeout(function(){
            {$leftclick_script}
        }, 0);
    } );
    
    $('#{$this->getId()} tbody').on( 'dblclick', '{$this->buildCssSelectorDataRows()}', function(e){
		{$dblclick_script}
	});
	
	$('#{$this->getId()} tbody').on( 'rightclick', '{$this->buildCssSelectorDataRows()}', function(e){
		{$rightclick_script}
	});

    {$this->buildJsOnChangeHandler()}

JS;
    }
		
    protected function buildCssSelectorDataRows()
    {
        return 'tr:not(.group)';
    }
    
    /**
     * Generates JS to disable text selection on the rows of the table.
     * If not done so, every time you longtap a row, something gets selected along
     * with the context menu being displayed. It look awful.
     *
     * @return string
     */
    protected function buildJsDisableTextSelection()
    {
        return "$('#{$this->getId()} tbody tr td').attr('unselectable', 'on').css('user-select', 'none').on('selectstart', false);";
    }
    
    protected function buildJsPagination()
    {
        $output = <<<JS
	$('#{$this->getId()}_prevPage').on('click', function(){{$this->getId()}_table.page('previous'); {$this->buildJsRefresh(true)}});
	$('#{$this->getId()}_nextPage').on('click', function(){{$this->getId()}_table.page('next'); {$this->buildJsRefresh(true)}});
	
	$('#{$this->getId()}_pageInfo').on('click', function(){
		$('#{$this->getId()}_pageInput').val({$this->getId()}_table.page()+1);
	});
	
	$('#{$this->getId()}_pageControls').on('hidden.bs.dropdown', function(){
		{$this->getId()}_table.page(parseInt($('#{$this->getId()}_pageSlider').val())-1).draw(false);
	});
JS;
		return $output;
    }
    
    protected function buildJsRowGroupFunctions()
    {
        if (! $this->getWidget()->hasRowGroups()){
            return '';
        }
        
        return <<<JS

    function {$this->buildJsFunctionPrefix()}RowGroupToggle(row){
        var jqRow = $(row);
        if (jqRow.hasClass('collapsed')){
            jqRow.removeClass('collapsed').nextUntil('.group').show();
            jqRow.find('i').removeClass().addClass('{$this->getRowDetailsCollapseIcon()}');
        } else {
            jqRow.addClass('collapsed').nextUntil('.group').hide();
            jqRow.find('i').removeClass().addClass('{$this->getRowDetailsExpandIcon()}');
        }
    }

JS;
    }
        
    protected function buildJsOnChangeHandler()
    {
        $js = '';
        if ($script = $this->getOnChangeScript()) {
            $js = <<<JS

    {$this->getId()}_table.on( 'select', function ( e, dt, type, indexes ) {
        {$script}
    });

JS;
        }
        return $js;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isResponsive() : bool
    {
        return $this->getWidget() instanceof DataTableResponsive;
    }
    
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();
        // DataTables
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.DATATABLES.THEME.CSS') . '">';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.DATATABLES.CORE.JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.DATATABLES.THEME.JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.DATATABLES.SELECT.JS') . '"></script>';
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.DATATABLES.SELECT.CSS') . '">';
        
        if ($this->getWidget()->hasRowGroups()){
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.DATATABLES.ROWGROUP.JS') . '"></script>';
            $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.DATATABLES.ROWGROUP.CSS') . '">';
        }
        
        if ($this->isResponsive()){
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.DATATABLES.RESPONSIVE.JS') . '"></script>';
            $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.DATATABLES.RESPONSIVE.CSS') . '">';
        }
        
        return $includes;
    }
    
    /**
     * Returns JS code to select the first row in a table, that has the given value in the specified column.
     * 
     * The generated code will search the current values of the $column for an exact match
     * for the value of $valueJs JS variable, mark the first matching row as selected and
     * scroll to it to ensure it is visible to the user.
     * 
     * The row index (starting with 0) is saved to the JS variable specified in $rowIdxJs.
     * 
     * If the $valueJs is not found, $onNotFoundJs will be executed and $rowIdxJs will be
     * set to -1.
     * 
     * @param DataColumn $column
     * @param string $valueJs
     * @param string $onNotFoundJs
     * @param string $rowIdxJs
     * @return string
     */
    public function buildJsSelectRowByValue(DataColumn $column, string $valueJs, string $onNotFoundJs = '', string $rowIdxJs = 'rowIdx') : string
    {
        return <<<JS

var {$rowIdxJs} = function() {
    var rowIdx = {$this->getId()}_table.column('{$column->getAttributeAlias()}:name').data().indexOf({$valueJs});
    if (rowIdx == -1){
		{$onNotFoundJs};
	} else {
        // {$this->getId()}_table.row(rowIdx).to$().scrollIntoView();
        {$this->getId()}_table.rows(rowIdx).select();
	}
    return rowIdx;
}();

JS;
    }
}
?>
