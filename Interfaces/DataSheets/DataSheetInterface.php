<?php namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Exceptions\DataSheetException;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

interface DataSheetInterface extends ExfaceClassInterface, iCanBeCopied, iCanBeConvertedToUxon {
	
	/**
	 * Adds an array of rows to the data sheet. Each row must be an assotiative array [ column_id => value ].
	 * @param array $rows
	 */
	function add_rows(array $rows);
	
	/**
	 * Adds a new row to the data sheet. The row must be a non-empty assotiative array [ column_id => value ].
	 * @param array $row
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 */
	public function add_row(array $row);
	
	/**
	 * Makes $this LEFT JOIN $data_sheet ON $this.$left_key_column = $data_sheet.right_key_column
	 * All joined columns are prefixed with the $column_prefix.
	 * 
	 * IDEA improve performance by checking, which data sheet has less rows and iterating through that one instead of alwasy the left one.
	 * This would be especially effective if there is nothing to join...
	 * 
	 * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface data_sheet
	 * @param string left_key_column
	 * @param string right_key_column
	 * @param string column_prefix
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 */
	public function join_left(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $left_key_column = null, $right_key_column = null, $relation_path = '');
	
	public function import_rows(DataSheetInterface $other_sheet);
	
	/**
	 * Returns the values a column of the data sheet as an array
	 * @param string column_name
	 * @param boolean include_totals if set to ture, the total rows will be appended to the data rows
	 * @return boolean|array
	 */
	public function get_column_values($column_name, $include_totals=false);
	
	/**
	 * Overwrites all values of a data sheet column using a given array with values per row or
	 * a single value, which will be replicated into each row. Values for total rows can be specified
	 * via $totals_values in the same manner. This is usefull when applying data functions to columns.
	 * NOTE: if the data sheet does not contain a column with the given name, it will be added automatically.
	 * @param string column_name
	 * @param mixed|array column_values
	 * @param mixed|array totals_values
	 * @return DataSheetInterface
	 */
	public function set_column_values($column_name, $column_values, $totals_values = null);
	
	public function get_cell_value($column_name, $row_number);
	
	public function set_cell_value($column_name, $row_number, $value);
	
	public function get_total_value($column_name, $row_number);
	
	/**
	 * Populates the data sheet with actual data from the respecitve data sources. Returns the number of rows created in the sheet.
	 *
	 * @param integer offset
	 * @param integer limit
	 * @return integer
	 */
	public function data_read($limit = null, $offset = null);
	
	public function count_rows();
	
	/**
	 * Saves the values in this data sheet to the appropriate data sources. By default id does an update, creating new rows only if the data
	 * does not contain an object uid for the corresponding row.
	 * 
	 * @param DataTransactionInterface $transaction
	 * @return integer
	 */
	public function data_save(DataTransactionInterface $transaction = null);
	
	/**
	 * Updates data sources of all objects in the data sheet with the current values. Returns the number of data sets updated.
	 * Setting $create_if_uid_not_found to TRUE will create new data entries for those rows of the data sheets, where the row UID
	 * is currently not present in the data source.
	 * 
	 * If no transaction is given, a new transaction will be created an committed at the end of this method
	 * 
	 * @param boolean $create_if_uid_not_found
	 * @param DataTransactionInterface $transaction
	 * @return integer
	 */
	public function data_update($create_if_uid_not_found = false, DataTransactionInterface $transaction = null);
	
	/**
	 * Replaces all rows matching current filters with data contained in this data sheet: rows with matching UIDs are updated, new rows
	 * are created and those missing in the current data sheet will get deleted in the data source, unless $delete_missing_rows is set to
	 * FALSE.
	 * 
	 * If no transaction is given, a new transaction will be created an committed at the end of this method
	 * 
	 * @param DataTransactionInterface $transaction
	 * @param boolean $delete_missing_rows
	 */
	public function data_replace_by_filters($delete_redundant_rows = true, DataTransactionInterface $transaction = null);
	
	/**
	 * Saves all values of the data sheets creating new data in the corresponding data sources. By default rows with existing UID-values are updated. 
	 * This can be turned off, however by settin $update_if_uid_found to FALSE (e.g. if you want to explicitly set the UIDs for some reason). 
	 * Returns the number of data sets created.
	 * 
	 * If no transaction is given, a new transaction will be created an committed at the end of this method
	 * 
	 * @param boolean $update_if_uid_found
	 * @param DataTransactionInterface $transaction
	 * @return array
	 */
	public function data_create($update_if_uid_found = true, DataTransactionInterface $transaction = null);
	
	/**
	 * Deletes all object loaded in the data sheet from the appropriate data sources.
	 * Performes cascading deletes for all instances explicitly referencing the deleted objects
	 * 
	 * If no transaction is given, a new transaction will be created an committed at the end of this method
	 * 
	 * @param DataTransactionInterface $transaction
	 * @return number
	 */
	public function data_delete(DataTransactionInterface $transaction = null);
	
	/**
	 * @return DataSheetList
	 */
	public function get_subsheets();
	
	/**
	 * Returns an array of data sorters
	 * @return DataSorterListInterface
	 */
	public function get_sorters();
	
	/**
	 * Returns TRUE if the data sheet has at least one sorter and FALSE otherwise
	 * @return boolean
	 */
	public function has_sorters();
	
	public function set_counter_rows_all($count);
	
	/**
	 * Returns multiple rows of the data sheet as an array of associative array (e.g. [rownum => [col1 => val1, col2 => val2, ...] ])
	 * By default returns all rows. Use the arguments to select only a range of rows.
	 * @param number $how_many
	 * @param number $offset
	 * @return array
	 */
	public function get_rows($how_many = 0, $offset = 0);
	
	/**
	 * Returns the specified row as an associative array (e.g. [col1 => val1, col2 => val2, ...])
	 * @param number $row_number
	 * @return multitype:
	 */
	public function get_row($row_number = 0);
	
	/**
	 * Returns the first row, that contains a given value in the specified column. Returns NULL if no row matches.
	 * @param string $column_name
	 * @param mixed $value
	 * @throws DataSheetException
	 * @return array
	 */
	public function get_row_by_column_value($column_name, $value);
	
	/**
	 * Returns the total rows as assotiative arrays. Multiple total rows can be used to display multiple totals per column.
	 * @return array [ column_id => total value ]
	 */
	public function get_totals_rows();
	
	public function count_rows_all();
	
	public function count_rows_loaded($include_totals=false);
	
	/**
	 * Returns an array of DataColumns
	 * @return DataColumnListInterface
	 */
	public function get_columns();
	
	/**
	 * Returns the data sheet column containing the UID values of the main object or false if the data sheet does not contain that column
	 * @return \exface\Core\Interfaces\DataSheets\DataColumnInterface
	 */
	public function get_uid_column();
	
	/**
	 * @return Object
	 */
	public function get_meta_object();
	
	/**
	 * @return DataAggregatorListInterface
	 */
	public function get_aggregators();
	
	/**
	 * Returns TRUE if the data sheet has at least one aggregator and FALSE otherwise
	 * @return boolean
	 */
	public function has_aggregators();
	
	/**
	 * Returns the root condition group with all filters of the data sheet
	 * @return ConditionGroup
	 */
	public function get_filters();
	
	/**
	 * Replaces all filters of the data sheet by the given condition group
	 * @param ConditionGroup $condition_group
	 */
	public function set_filters(ConditionGroup $condition_group);
	
	/**
	 * Removes all rows of the data sheet without chaning anything in the column structure
	 * @return DataSheetInterface
	 */
	public function remove_rows();
	
	/**
	 * Removes a single row of the data sheet
	 * @param integer $row_number
	 * @return DataSheetInterface
	 */
	public function remove_row($row_number);
	
	/**
	 * Removes all rows from the specified column. If it is the only column in the row, the entire row will be removed.
	 * @param string $column_name
	 * @return DataSheetInterface
	 */
	public function remove_rows_for_column($column_name);
	
	/**
	 * Returns TRUE if the data sheet currently does not have any data and FALSE otherwise.
	 * @return boolean
	 */
	public function is_empty();
	
	/**
	 * Returns TRUE if the data in the sheet is up to date and FALSE otherwise (= if the data needs to be loaded)
	 * @return boolean
	 */
	public function is_up_to_date();
	
	public function get_rows_on_page();
	
	public function set_rows_on_page($value);

	public function get_row_offset();
	
	public function set_row_offset($value);
	
	/**
	 * Merges the current data sheet with another one. Values of the other sheet will overwrite values of identical columns of the current one!
	 * @param DataSheet $other_sheet
	 */
	public function merge(DataSheetInterface $other_sheet);
	
	public function get_meta_object_relation_path(Object $related_object);
	
	/**
	 * Clones the data sheet and returns the new copy. The copy will point to the same meta object, but will
	 * have separate columns, filters, aggregations, etc.
	 * @return DataSheetInterface
	 */
	public function copy();
	
	/**
	 * @return string
	 */
	public function get_uid_column_name();
	
	/**
	 * 
	 * @param string $value
	 * @return DataSheetInterface
	 */
	public function set_uid_column_name($value);
	
	/**
	 * 
	 * @param DataColumnInterface $column
	 * @return DataSheetInterface
	 */
	public function set_uid_column(DataColumnInterface $column);
	
	/**
	 * Returns TRUE if all data in this sheet passes all validation schecks and FALSE otherwise.
	 * Apart from automatic validation based on the meta model (e.g. data types, etc.), custom validators can be
	 * easily added by creating object behaviours listening to validation events. If they detect invalid data,
	 * they would only need to call data_mark_invalid() and the sheet will fail validation in any case.
	 * @throws DataSheetValidationError
	 * @return boolean
	 */
	public function data_validate();
	
	/**
	 * Marks the data in this sheet as invalid causing the validation to fail in any case
	 * @return DataSheetInterface
	 */
	public function data_mark_invalid();
}

?>