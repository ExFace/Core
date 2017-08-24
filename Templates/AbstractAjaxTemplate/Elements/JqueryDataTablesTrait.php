<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;

/**
 * This trait contains common methods for template elements using the jQuery DataTables library.
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
        
        if ($widget->hasRowDetails()) {
            $output = <<<JS
	// Add event listener for opening and closing details
	$('#{$this->getId()} tbody').on('click', 'td.details-control', function () {
		var tr = $(this).closest('tr');
		var row = {$this->getId()}_table.row( tr );
		
		if ( row.child.isShown() ) {
			// This row is already open - close it
			row.child.hide();
			tr.removeClass('shown');
			tr.find('.{$this->getRowDetailsCollapseIcon()}').removeClass('{$this->getRowDetailsCollapseIcon()}').addClass('{$this->getRowDetailsExpandIcon()}');
			$('#detail'+row.data().id).remove();
			{$this->getId()}_table.columns.adjust();
		}
		else {
			// Open this row
			row.child('<div id="detail'+row.data().{$widget->getMetaObject()->getUidAlias()}+'"></div>').show();
			$.ajax({
				url: '{$this->getAjaxUrl()}',
				method: 'post',
				data: {
					action: '{$widget->getRowDetailsAction()}',
					resource: '{$this->getPageId()}',
					element: '{$widget->getRowDetailsContainer()->getId()}',
					prefill: {
						oId:"{$widget->getMetaObjectId()}",
						rows:[
							{ {$widget->getMetaObject()->getUidAlias()}: row.data().{$widget->getMetaObject()->getUidAlias()} }
						],
						filters: {$this->buildJsDataFilters()}
					},
					exfrid: row.data().{$widget->getMetaObject()->getUidAlias()}
				},
				dataType: "html",
				success: function(data){
					$('#detail'+row.data().{$widget->getMetaObject()->getUidAlias()}).append(data);
					{$this->getId()}_table.columns.adjust();
				},
				error: function(jqXHR, textStatus, errorThrown ){
					{$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')}
				}
			});
			tr.next().addClass('detailRow unselectable');
			tr.addClass('shown');
			tr.find('.{$this->getRowDetailsExpandIcon()}').removeClass('{$this->getRowDetailsExpandIcon()}').addClass('{$this->getRowDetailsCollapseIcon()}');
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
            return $this->getTemplate()->getElement($this->getWidget()->getConfiguratorWidget())->buildJsDataGetter($action);
        } elseif ($this->isEditable() && $action->implementsInterface('iModifyData')) {
            // TODO
        } else {
            $rows = "Array.prototype.slice.call(" . $this->getId() . "_table.rows({selected: true}).data())";
        }
        return "{oId: '" . $this->getWidget()->getMetaObjectId() . "', rows: " . $rows . "}";
    }
    
    public function buildJsRefresh($keep_pagination_position = false)
    {
        if (! $this->getWidget()->getLazyLoading()) {
            return "{$this->getId()}_table.search($('#" . $this->getId() . "_quickSearch').val(), false, true).draw();";
        } else {
            return $this->getId() . "_table.draw(" . ($keep_pagination_position ? "false" : "true") . ");";
        }
    }
    
    public function buildJsColumnDef(\exface\Core\Widgets\DataColumn $col)
    {
        $output = '{
							name: "' . $col->getDataColumnName() . '"
                            ' . ($col->getAttributeAlias() ? ', data: "' . $col->getDataColumnName() . '"' : '') . '
                            ' . ($col->isHidden() || $col->getVisibility() === EXF_WIDGET_VISIBILITY_OPTIONAL ? ', visible: false' : '') . '
                            ' . ($col->getWidth()->isTemplateSpecific() ? ', width: "' . $col->getWidth()->getValue() . '"': '') . '
                            , className: "' . $this->buildCssColumnClass($col) . '"' . '
                            , orderable: ' . ($col->getSortable() ? 'true' : 'false') . '
                    }';
        
        return $output;
    }
    
    
    
    protected function buildJsQuicksearch()
    {
        $output = <<<JS
        	$('#{$this->getId()}_quickSearch_form').on('submit', function(event) {
        		{$this->buildJsRefresh(false)}
        		event.preventDefault();
        		return false;
        	});
        	
        	$('#{$this->getId()}_quickSearch').on('change', function(event) {
        		{$this->buildJsRefresh(false)}
        	});
JS;
		return $output;
    }
    
    public function buildJsValueGetter($column = null, $row = null)
    {
        $output = $this->getId() . "_table";
        if (is_null($row)) {
            $output .= ".rows('.selected').data()";
        } else {
            // TODO
        }
        if (is_null($column)) {
            $column = $this->getWidget()->getMetaObject()->getUidAlias();
        } else {
            // TODO
        }
        return $output . "[0]['" . $column . "']";
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
        
        $ajax_data = <<<JS
			function ( d ) {
				{$this->buildJsBusyIconShow()}
				var filtersOn = false;
				d.action = '{$widget->getLazyLoadingAction()}';
				d.resource = "{$this->getPageId()}";
				d.element = "{$widget->getId()}";
				d.object = "{$this->getWidget()->getMetaObject()->getId()}";
                d.q = $('#{$this->getId()}_quickSearch').val();
				d.data = {$this->getTemplate()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter()};
				
				{$this->buildJsFilterIndicatorUpdater()}
			}
JS;
				
				$result = '';
				if ($this->getWidget()->getLazyLoading()) {
				    $result = <<<JS
		"serverSide": true,
		"ajax": {
			"url": "{$this->getAjaxUrl()}",
			"type": "POST",
			"data": {$ajax_data},
			"error": function(jqXHR, textStatus, errorThrown ){
				{$this->buildJsBusyIconHide()}
				{$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')}
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
						{$this->getTemplate()->encodeData($this->prepareData($data))}
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
						"defaultContent": \'<i class="fa ' . $this->row_details_expand_icon . '"></i>\'
					}
					';
            $column_number_offset ++;
        }
        
        // Sorters
        $default_sorters = '';
        foreach ($widget->getSorters() as $sorter) {
            $column_exists = false;
            foreach ($widget->getColumns() as $nr => $col) {
                if ($col->getAttributeAlias() == $sorter->attribute_alias) {
                    $column_exists = true;
                    $default_sorters .= '[ ' . ($nr + $column_number_offset) . ', "' . $sorter->direction . '" ], ';
                }
            }
            if (! $column_exists) {
                // TODO add a hidden column
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
        if ($widget->getPaginate()) {
            $paging_options = '"pageLength": ' . (!is_null($widget->getPaginatePageSize()) ? $widget->getPaginatePageSize() : $this->getTemplate()->getConfig()->getOption('WIDGET.DATATABLE.PAGE_SIZE')) . ',';
        } else {
            $paging_options = '"paging": false,';
        }
        
        // columns && their footers
        $footer_callback = '';
        foreach ($widget->getColumns() as $nr => $col) {
            $columns[] = $this->buildJsColumnDef($col);
            $nr = $nr + $column_number_offset;
            if ($col->getFooter()) {
                $footer_callback .= <<<JS
	            // Total over all pages
	            if (api.ajax.json().footer[0]['{$col->getDataColumnName()}']){
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
        
        return <<<JS

    $('#{$this->getId()}').DataTable( {
		"dom": 't',
		"deferRender": true,
		"processing": true,
		"select": { {$select_options} },
		{$paging_options}
		"scrollX": true,
		"scrollXollapse": true,
		{$this->buildJsDataSource($filters_ajax)}
		"language": {
            "zeroRecords": "{$widget->getEmptyText()}"
        },
		"columns": [{$columns}],
		"order": [ {$default_sorters} ],
		"drawCallback": function(settings, json) {
			$('#{$this->getId()} tbody tr').on('contextmenu', function(e){
				{$this->getId()}_table.row($(e.target).closest('tr')).select();
			});
			$('#{$this->getId()}').closest('.fitem').trigger('resize');
            context.attach('#{$this->getId()} tbody tr', [{$this->buildJsContextMenu()}]);
			if({$this->getId()}_table){
				{$this->getId()}_drawPagination();
				{$this->getId()}_table.columns.adjust();
			}
			{$this->buildJsDisableTextSelection()}
			{$this->buildJsBusyIconHide()}
		}
		{$footer_callback}
	} );

JS;
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
            $leftclick_script = $this->getTemplate()->getElement($leftclick_button)->buildJsClickFunctionName() . '()';
        }
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
            $dblclick_script = $this->getTemplate()->getElement($dblclick_button)->buildJsClickFunctionName() . '()';
        }
        
        // Double click. Currently only supports one double click action - the first one in the list of buttons
        if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
            $rightclick_script = $this->getTemplate()->getElement($rightclick_button)->buildJsClickFunctionName() . '()';
        }

        return <<<JS

	$('#{$this->getId()} tbody').on( 'click', 'tr', function () {
		{$leftclick_script}
    } );
    
    $('#{$this->getId()} tbody').on( 'dblclick', 'tr', function(e){
		{$dblclick_script}
	});
	
	$('#{$this->getId()} tbody').on( 'rightclick', 'tr', function(e){
		{$rightclick_script}
	});

JS;
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
}
?>
