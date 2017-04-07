<?php
namespace exface\Core\Widgets;

/**
 * A DataTable with certain columns being transposed.
 * 
 * Starting with a DataTable, you make it create additional columns with the values from the label_column as headers
 * and values taken from the data_column. The other columns will keep their values. Thus, the DataMatrix has less
 * rows than the underlying table, because some of the are summarized to a single row with more columns.
 * 
 * The following example will create a color/size matrix with product stock levels out of a table listing
 * the current stock level for each color-size-combination individually:
 * 		  {
 * 	        "widget_type": "DataMatrix",
 * 	        "object_alias": "PRODUCT_COLOR_SIZE",
 * 	        "hide_toolbars": true,
 * 	        "caption": "Stock matrix",
 * 	        "columns": [
 * 	          {
 * 	            "attribute_alias": "COLOR__LABEL"
 * 	          },
 * 	          {
 * 	            "attribute_alias": "SIZE",
 * 	            "id": "SIZE"
 * 	          },
 * 	          {
 * 	            "attribute_alias": "STOCKS__AVAILABLE:SUM",
 * 	            "id": "STOCK_AVAILABLE"
 * 	          }
 * 	        ],
 * 	        "label_column_id": "SIZE",
 * 	        "data_column_id": "STOCK_AVAILABLE",
 * 	        "sorters": [
 * 	          {
 * 	            "attribute_alias": "COLOR__LABEL",
 * 	            "direction": "ASC"
 * 	          },
 * 	          {
 * 	            "attribute_alias": "SIZING_LENGTH",
 * 	            "direction": "ASC"
 * 	          }
 * 	        ]
 * 	      }
 * 
 * @author Andrej Kabachnik
 *
 */
class DataMatrixOld extends DataTable {
	private $label_column_id = null;
	private $data_column_id = null;
	
	protected function init(){
		parent:: init();
		$this->set_paginate(false);
		$this->set_show_row_numbers(false);
		$this->set_multi_select(false);
	}
	
	public function get_label_column_id() {
		return $this->label_column_id;
	}
	
	/**
	 * Defines the id of the column, that contains values to be used as captions for the new new (transposed) columns.
	 * 
	 * @uxon-property label_column_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\DataMatrix
	 */
	public function set_label_column_id($value) {
		$this->label_column_id = $value;
		return $this;
	}
	
	public function get_data_column_id() {
		return $this->data_column_id;
	}
	
	/**
	 * Defines the id of the column, that should be transposed.
	 * 
	 * @uxon-property data_column_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\DataMatrix
	 */
	public function set_data_column_id($value) {
		$this->data_column_id = $value;
		return $this;
	}  
	
	/**
	 * Returns the data column widget or false if no data column specified
	 * @return \exface\Core\Widgets\DataColumn | boolean
	 */
	public function get_data_column(){
		if (!$result = $this->get_column($this->get_data_column_id())){
			$result = $this->get_column_by_attribute_alias($this->get_data_column_id());
		}
		return $result;
	}
	
	/**
	 * Returns the label column widget or false if no label column specified
	 * @return \exface\Core\Widgets\DataColumn | boolean
	 */
	public function get_label_column(){
		if (!$result = $this->get_column($this->get_label_column_id())){
			$result = $this->get_column_by_attribute_alias($this->get_label_column_id());
		}
		return $result;
	}
	  
}
?>