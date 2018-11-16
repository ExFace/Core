<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\CommonLogic\DataSheets\DataSheetList;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;

interface DataSheetInterface extends WorkbenchDependantInterface, iCanBeCopied, iCanBeConvertedToUxon
{

    /**
     * Adds an array of rows to the data sheet.
     * Each row must be an assotiative array [ column_id => value ].
     * Missing columns will be automatically created. If $merge_uid_dublicates is TRUE, given rows with UIDs
     * already present in the sheet, will overwrite old rows instead of being added at the end of the sheet.
     *
     * @see import_rows() for an easy way of adding rows from another data sheet
     *     
     * @param array $rows            
     * @param boolean $merge_uid_dublicates            
     * @return DataSheetInterface
     */
    public function addRows(array $rows, bool $merge_uid_dublicates = false, bool $auto_add_columns = true) : DataSheetInterface;

    /**
     * Adds a new row to the data sheet.
     * The row must be a non-empty assotiative array [ column_id => value ].
     * Missing columns will be automatically created. If $merge_uid_dublicates is TRUE, given rows with UIDs
     * already present in the sheet, will overwrite old rows instead of being added at the end of the sheet.
     *
     * @param array $row            
     * @param boolean $merge_uid_dublicates            
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function addRow(array $row, bool $merge_uid_dublicates = false, bool $auto_add_columns = true) : DataSheetInterface;

    /**
     * Makes this data sheet LEFT JOIN the other data sheet ON $this.$left_key_column = $data_sheet.right_key_column
     * All joined columns are prefixed with the $column_prefix.
     *
     * IDEA improve performance by checking, which data sheet has less rows and iterating through that one instead of alwasy the left one.
     * This would be especially effective if there is nothing to join...
     *
     * @param DataSheetInterface data_sheet
     * @param string left_key_column
     * @param string right_key_column
     * @param string column_prefix
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function joinLeft(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $left_key_column = null, $right_key_column = null, $relation_path = '');

    /**
     * Imports data from matching columns of the given sheet.
     * If the given sheet has the same columns, as this one, their
     * values will be copied to this sheet. If this sheet has columns with formulas, they will automatically get calculated
     * for the imported rows.
     *
     * @param DataSheetInterface $other_sheet            
     * @return DataSheetInterface
     */
    public function importRows(DataSheetInterface $other_sheet);

    /**
     * Returns the values a column of the data sheet as an array
     *
     * @param
     *            string column_name
     * @param
     *            boolean include_totals if set to ture, the total rows will be appended to the data rows
     * @return boolean|array
     */
    public function getColumnValues($column_name, $include_totals = false);

    /**
     * Overwrites all values of a data sheet column using a given array with values per row or
     * a single value, which will be replicated into each row.
     * Values for total rows can be specified
     * via $totals_values in the same manner. This is usefull when applying data functions to columns.
     * NOTE: if the data sheet does not contain a column with the given name, it will be added automatically.
     *
     * @param string column_name
     * @param mixed|array column_values
     * @param mixed|array totals_values
     * @return DataSheetInterface
     */
    public function setColumnValues($column_name, $column_values, $totals_values = null);

    public function getCellValue($column_name, $row_number);

    public function setCellValue($column_name, $row_number, $value);

    public function getTotalValue($column_name, $row_number);

    /**
     * Populates the data sheet with actual data from the respecitve data sources.
     * Returns the number of rows created in the sheet.
     *
     * @param int offset
     * @param int limit
     * 
     * @triggers \exface\Core\Events\DataSheet\OnBeforeReadDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnReadDataEvent
     * 
     * @return int
     */
    public function dataRead(int $limit = null, int $offset = null) : int;
    
    /**
     * Performs a count operation on the data source to get fresh information about
     * how many rows would match the filters and aggregations of this data sheet.
     * 
     * Avoid calling dataCount() explicitly!!! Some data sources like large SQL tables 
     * or OLAP cubes in general have very poor counting performance. Instead use
     * countRowsInDataSource() and let the syste decide if a count operation is
     * really required!
     *  
     * @return int
     */
    public function dataCount() : int;

    /**
     * 
     * @return int
     */
    public function countRows() : int;
    
    /**
     * Returns the total number of rows available in the data source matching
     * these sheet's filters and aggregations or NULL if not available.
     *
     * Will return NULL if the information is not available from the data source
     * or the data source was not read (e.g. the sheet was populated
     * programmatically)!
     * 
     * If you expect poor performance for count operations in the data source
     * (e.g. for OLAP sources), you can explicitly pervent this method 
     * from performing them by setting setAutoCount(false) for this sheet.
     * 
     * @see setAutoCount()
     *
     * @return int|NULL
     */
    public function countRowsInDataSource() : ?int;
    
    /**
     * Explicitly sets the counter for rows matching this sheet's filters, etc. available in the data source.
     * 
     * This method is usefull for custom-built data sheets or programmatic data sources.
     * 
     * @param int $count
     * @return DataSheetInterface
     */
    public function setCounterForRowsInDataSource(int $count) : DataSheetInterface;
    
    /**
     * Set to TRUE if you do not want the sheet to counting all rows matching it's filters in the 
     * data source when countRowsInDataSource() is called.
     * 
     * Some data sources automatically count available rows with every paged read operation, but
     * most do wait for count() to be called explicitly to improve reading performance. By
     * default, a data sheet will perform a count() automatically when countRowsInDataSource()
     * is called. If you suspect poor performance of a count (e.g. for OLAP sources), 
     * you can block this behavior by setAutoCount(false). In this case countRowsInDataSource() 
     * will return null.
     * 
     * Disabling this counter can significatly improve performance, but has negative effects on 
     * pagination - the total amount of pages cannot be determined anymore.
     * 
     * Note: This setting will not have any effect on data sources, that count rows with every 
     * read operation. 
     * 
     * Note: Even if autocount is disabled, you can still force the sheet to count rows in the
     * data source with dataCount()
     * 
     * @see countRowsInDataSource()
     * @see dataCount()
     * 
     * @param bool $trueOrFalse
     * @return DataSheetInterface
     */
    public function setAutoCount(bool $trueOrFalse) : DataSheetInterface;
    
    /**
     * Returns TRUE if the sheet will automatically perform a count() on it's data source when
     * countRowsInDataSource() is called and FALSE otherwise.
     * 
     * @see setAutoCount() for more details.
     * 
     * @return bool
     */
    public function getAutoCount() : bool;

    /**
     * Saves the values in this data sheet to the appropriate data sources.
     * By default id does an update, creating new rows only if the data
     * does not contain an object uid for the corresponding row.
     *
     * @param DataTransactionInterface $transaction            
     * @return integer
     */
    public function dataSave(DataTransactionInterface $transaction = null);

    /**
     * Updates data sources of all objects in the data sheet with the current values.
     * Returns the number of data sets updated.
     * Setting $create_if_uid_not_found to TRUE will create new data entries for those rows of the data sheets, where the row UID
     * is currently not present in the data source.
     *
     * If no transaction is given, a new transaction will be created an committed at the end of this method
     * 
     * @param bool $create_if_uid_not_found            
     * @param DataTransactionInterface $transaction  
     *           
     * @triggers \exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnUpdateDataEvent
     *
     * @return int
     */
    public function dataUpdate(bool $create_if_uid_not_found = false, DataTransactionInterface $transaction = null) : int;

    /**
     * Replaces all rows matching current filters with data contained in this data sheet returning the number of rows changed in the data source.
     * Rows with matching UIDs are updated, new rows are created and those missing in the current data sheet will get deleted in the data source,
     * unless $delete_missing_rows is set to FALSE.
     *
     * The update operation will perform an update on all records matching the UIDs in the data sheet - regardless of the filter.
     * Thus, if replacing all attributes of an object, all attributes in the data sheet will get updated - even if they belong to another
     * object in the data source (so the probably will get attached to the object we are replacing for). Set $update_by_uid_ignoring_filters
     * to FALSE to use the filters in the update operation too. In the above example, this would mean that attributes, that currently belong
     * to the other object will remain untouched.
     *
     * If no transaction is given, a new transaction will be created an committed at the end of this method
     *
     * @param DataTransactionInterface $transaction            
     * @param bool $delete_redundant_rows
     * @param bool $update_by_uid_ignoring_filters
     *             
     * @triggers \exface\Core\Events\DataSheet\OnBeforeReplaceDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnReplaceDataEvent
     * 
     * @return int
     */
    public function dataReplaceByFilters(DataTransactionInterface $transaction = null, bool $delete_redundant_rows = true, bool $update_by_uid_ignoring_filters = true) : int;

    /**
     * Saves all values of the data sheets creating new data in the corresponding data sources.
     * By default rows with existing UID-values are updated.
     * This can be turned off, however by settin $update_if_uid_found to FALSE (e.g. if you want to explicitly set the UIDs for some reason).
     * Returns the number of data sets created.
     *
     * If no transaction is given, a new transaction will be created an committed at the end of this method
     *
     * @param bool $update_if_uid_found            
     * @param DataTransactionInterface $transaction  
     *           
     * @triggers \exface\Core\Events\DataSheet\OnBeforeCreateDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnCreateDataEvent
     * 
     * @return int
     */
    public function dataCreate(bool $update_if_uid_found = true, DataTransactionInterface $transaction = null) : int;

    /**
     * Deletes all object loaded in the data sheet from the appropriate data sources.
     * Performes cascading deletes for all instances explicitly referencing the deleted objects
     *
     * If no transaction is given, a new transaction will be created an committed at the end of this method
     *
     * @param DataTransactionInterface $transaction   
     *          
     * @triggers \exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnDeleteDataEvent
     * 
     * @return int
     */
    public function dataDelete(DataTransactionInterface $transaction = null) : int;

    /**
     *
     * @return DataSheetList
     */
    public function getSubsheets();

    /**
     * Returns an array of data sorters
     *
     * @return DataSorterListInterface|DataSorterInterface[]
     */
    public function getSorters();

    /**
     * Returns TRUE if the data sheet has at least one sorter and FALSE otherwise
     *
     * @return boolean
     */
    public function hasSorters();

    /**
     * Returns multiple rows of the data sheet as an array of associative array (e.g.
     * [rownum => [col1 => val1, col2 => val2, ...] ])
     * By default returns all rows. Use the arguments to select only a range of rows.
     *
     * @param number $how_many            
     * @param number $offset            
     * @return array
     */
    public function getRows($how_many = 0, $offset = 0);

    /**
     * Returns the specified row as an associative array (e.g.
     * [col1 => val1, col2 => val2, ...])
     *
     * @param number $row_number            
     * @return multitype:
     */
    public function getRow($row_number = 0);

    /**
     * Returns the first row, that contains a given value in the specified column.
     * Returns NULL if no row matches.
     *
     * @param string $column_name            
     * @param mixed $value            
     * @throws DataSheetColumnNotFoundError
     * @return array
     */
    public function getRowByColumnValue($column_name, $value);

    /**
     * Returns the total rows as assotiative arrays.
     * Multiple total rows can be used to display multiple totals per column.
     *
     * @return array [ column_id => total value ]
     */
    public function getTotalsRows();

    /**
     * Returns an array of DataColumns
     *
     * @return DataColumnInterface[]|DataColumnListInterface
     */
    public function getColumns();

    /**
     * Returns the data sheet column containing the UID values of the main object or false if the data sheet does not contain that column
     *
     * @return \exface\Core\Interfaces\DataSheets\DataColumnInterface
     */
    public function getUidColumn();
    
    /**
     * Returns TRUE if the sheet has a UID column optionally checking for non-empty values and FALSE otherwise.
     * 
     * hasUidColumn() will return TRUE even if the column is empty, while hasUidColumn(true) will only return TRUE
     * if the column has at least one non-empty value.
     * 
     * @param bool $checkValues
     * 
     * @return boolean
     */
    public function hasUidColumn(bool $checkValues = false) : bool;

    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject();

    /**
     *
     * @return DataAggregationListInterface|DataAggregationInterface[]
     */
    public function getAggregations();

    /**
     * Returns TRUE if the data sheet has at least one aggregator and FALSE otherwise
     *
     * @return boolean
     */
    public function hasAggregations();

    /**
     * Returns the root condition group with all filters of the data sheet
     *
     * @return ConditionGroup
     */
    public function getFilters();

    /**
     * Replaces all filters of the data sheet by the given condition group
     *
     * @param ConditionGroup $condition_group            
     */
    public function setFilters(ConditionGroup $condition_group);

    /**
     * Removes all rows of the data sheet without chaning anything in the column structure
     *
     * @return DataSheetInterface
     */
    public function removeRows();

    /**
     * Removes a single row of the data sheet
     *
     * @param integer $row_number            
     * @return DataSheetInterface
     */
    public function removeRow($row_number);

    /**
     * Removes all rows with the given value in the UID column
     *
     * @param string $instance_uid
     * @return DataSheetInterface
     */
    public function removeRowsByUid($uid);

    /**
     * Removes all rows from the specified column.
     * If it is the only column in the row, the entire row will be removed.
     *
     * @param string $column_name            
     * @return DataSheetInterface
     */
    public function removeRowsForColumn($column_name);

    /**
     * Returns TRUE if the data sheet currently does not have any data and FALSE otherwise.
     *
     * @return boolean
     */
    public function isEmpty() : bool;

    /**
     * Returns TRUE if the data in the sheet is up to date and FALSE otherwise (= if the data needs to be loaded)
     *
     * @return boolean
     */
    public function isFresh() : bool;

    /**
     * Returns true if the data sheet will load all available data when performing data_read().
     * In general this is the case,
     * if neither filters nor UIDs in the data rows are specified. This method is mainly usefull for error detection, as
     * it is generally not a good idea to delete or update the entire data - it is always a good idea to have some filters.
     *
     * @return boolean
     */
    public function isUnfiltered() : bool;

    /**
     * Returns TRUE if the data sheet has neither content nor filters - and thus will not contain any meaningfull data if read.
     *
     * @return boolean
     */
    public function isBlank() : bool;

    /**
     * Returns TRUE if the data sheet has no sorters and FALSE otherwise.
     *
     * @return boolean
     */
    public function isUnsorted() : bool;
    
    /**
     * Returns TRUE if the sheet includes all data (is not paged) and FALSE otherwise.
     * 
     * @return bool
     */
    public function isUnpaged() : bool;

    public function getRowsLimit();

    public function setRowsLimit($value);

    public function getRowsOffset();

    public function setRowsOffset($value);

    /**
     * Merges the current data sheet with another one.
     * Values of the other sheet will overwrite values of identical columns of the current one!
     *
     * @param DataSheetInterface $other_sheet            
     */
    public function merge(DataSheetInterface $other_sheet);

    public function getMetaObjectRelationPath(MetaObjectInterface $related_object);

    /**
     * Clones the data sheet and returns the new copy.
     * The copy will point to the same meta object, but will
     * have separate columns, filters, aggregations, etc.
     *
     * @return DataSheetInterface
     */
    public function copy();

    /**
     *
     * @return string
     */
    public function getUidColumnName();

    /**
     *
     * @param string $value            
     * @return DataSheetInterface
     */
    public function setUidColumnName($value);

    /**
     *
     * @param DataColumnInterface $column            
     * @return DataSheetInterface
     */
    public function setUidColumn(DataColumnInterface $column);

    /**
     * Returns TRUE if all data in this sheet passes all validation schecks and FALSE otherwise.
     * Apart from automatic validation based on the meta model (e.g. data types, etc.), custom validators can be
     * easily added by creating object behaviours listening to validation events. If they detect invalid data,
     * they would only need to call data_mark_invalid() and the sheet will fail validation in any case.
     *
     * @triggers \exface\Core\Events\DataSheet\OnBeforeValidateDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnValidateDataEvent
     * 
     * @return bool
     */
    public function dataValidate() : bool;

    /**
     * Marks the data in this sheet as invalid causing the validation to fail in any case
     * 
     * @return DataSheetInterface
     */
    public function dataMarkInvalid();
    
    /**
     * Returns TRUE if at least one column has a footer and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasColumTotals();
    
    /**
     * 
     * @param ConditionalExpressionInterface $condition
     * @return DataSheetInterface
     */
    public function extract(ConditionalExpressionInterface $condition) : DataSheetInterface;
    
    /**
     * Sorts the current rows according to the given sorter list.
     * 
     * @param DataSorterListInterface $sorters
     * @return DataSheetInterface
     */
    public function sort(DataSorterListInterface $sorters) : DataSheetInterface;
}

?>