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
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

/**
 * Internal data respresentation - a row-based table with filters, sorters, aggregators, etc.
 * 
 * Structure:
 * 
 * rowIdx |Column1|Column2|Column3|
 *      0 | value | value | value | \
 *      1 | value | value | value | > data rows: each one is an assoc array(column=>value)
 *      2 | value | value | value | /
 *      3 | total | total | total | \
 *      4 | total | total | total | / total rows: each one is an assoc array(column=>value)
 *
 * Rows are numbered sequentially. Inserting a row at a certain position will shift row numbers
 * starting from that position. Similarly, removing a row, will also reindex all rows following
 * it!
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataSheetInterface extends WorkbenchDependantInterface, iCanBeCopied, iCanBeConvertedToUxon, iCanGenerateDebugWidgets
{

    /**
     * Appends an array of rows to the data sheet.
     * 
     * Each row must be an assotiative array [ column_id => value ].
     * 
     * Missing columns will be automatically created if $auto_add_columns is not set to FALSE. If 
     * $merge_uid_dublicates is TRUE, given rows with UIDs already present in the sheet, will overwrite 
     * old rows instead of being added at the end of the sheet.
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
     * 
     * The row must be a non-empty assotiative array [ column_id => value ].
     * 
     * Missing columns will be automatically created if $auto_add_columns is not set to FALSE. If 
     * $merge_uid_dublicates is TRUE, given rows with UIDs already present in the sheet, will overwrite 
     * old rows instead of being added at the end of the sheet.
     * 
     * NOTE: Rows are numbered sequentially. Inserting a row at a certain position will shift row numbers
     * starting from that position. Row numbers, that are out of the sequence will be ignored: e.g. if
     * you try to add a row at position 2 to an empty sheet, it will be added at position 0, becase 2 is 
     * not a valid sequential position in this case.
     *
     * @param array $row            
     * @param boolean $merge_uid_dublicates            
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function addRow(array $row, bool $merge_uid_dublicates = false, bool $auto_add_columns = true, int $position = null) : DataSheetInterface;

    /**
     * Makes this data sheet LEFT OUTER JOIN the other data sheet ON $this.$left_key_column = $data_sheet.$right_key_column
     * All joined columns are prefixed with the $column_prefix.
     *
     * IDEA improve performance by checking, which data sheet has less rows and iterating through that one instead of alwasy the left one.
     * This would be especially effective if there is nothing to join...
     *
     * @param DataSheetInterface $otherSheet
     * @param string $leftKeyColName
     * @param string $rightKeyColName
     * @param string $relationPath
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function joinLeft(\exface\Core\Interfaces\DataSheets\DataSheetInterface $otherSheet, string $leftKeyColName = null, string $rightKeyColName = null, string $relationPath = '') : DataSheetInterface;

    /**
     * Imports data from matching columns of the given sheet.
     * If the given sheet has the same columns, as this one, their
     * values will be copied to this sheet. If this sheet has columns with formulas, they will get calculated
     * for the imported rows if `calculateFormulas` is `true`.
     *
     * @param DataSheetInterface $other_sheet            
     * @return DataSheetInterface
     */
    public function importRows(DataSheetInterface $other_sheet, bool $calculateFormulas);

    /**
     * Returns the values a column of the data sheet as an array
     * 
     * If $include_totals is set to ture, the total rows will be appended to the data rows
     *
     * @param string $column_name
     * @param boolean $include_totals
     * @return array
     */
    public function getColumnValues(string $column_name, bool $include_totals = false) : array;

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
    public function setColumnValues(string $column_name, $column_values, $totals_values = null) : DataSheetInterface;

    /**
     * 
     * @param string $column_name
     * @param int $row_number
     * @return mixed|NULL
     */
    public function getCellValue(string $column_name, int $row_number);

    /**
     * 
     * @param string $column_name
     * @param int $row_number
     * @param mixed $value
     * @return DataSheetInterface
     */
    public function setCellValue(string $column_name, int $row_number, $value) : DataSheetInterface;

    /**
     * 
     * @param string $column_name
     * @param int $row_number
     * @return mixed|NULL
     */
    public function getTotalValue(string $column_name, int $row_number);

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
     * Returns NULL if the data source cannot count at current conditions. The data
     * sources may distinguish between counting errors an silently refusing to count
     * in order to enable dynamic pagination.
     * 
     * Avoid calling dataCount() explicitly!!! Some data sources like large SQL tables 
     * or OLAP cubes in general have very poor counting performance. Instead use
     * countRowsInDataSource() and let the syste decide if a count operation is
     * really required!
     *  
     * @return int|NULL
     */
    public function dataCount() : ?int;

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
     * Replaces all rows matching current filters with data contained in this data sheet returning the number 
     * of rows changed in the data source.
     * 
     * Rows with matching UIDs are updated, new rows are created and those missing in the current data sheet 
     * will get deleted in the data source, unless $delete_missing_rows is set to FALSE.
     *
     * By default, the update operation will perform an update on all records matching the UIDs in this data sheet
     * - regardless of the filter. Thus, if replacing all attributes of an object, all attributes in the sheet 
     * will get updated - even if they currently belong to another object in the data source (thus they will get 
     * attached to the object we are replacing for). Set $update_by_uid_ignoring_filters to FALSE to use the filters 
     * in the update operation too. In the above example, this would mean that attributes, that currently belong to 
     * other objects will remain untouched although they are present in this sheet.
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
     * @param bool $cascading
     *          
     * @triggers \exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent
     * @triggers \exface\Core\Events\DataSheet\OnDeleteDataEvent
     * 
     * @return int
     */
    public function dataDelete(DataTransactionInterface $transaction = null, bool $cascading = true) : int;

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
     * Returns only those rows from the current sheet, that are not present in the other sheet provided.
     *
     * This method is mainly usefull to compare data sheets deemed to be identical - it compares
     * rows with the same row numbers.
     * 
     * Columns, attributes or expressions can be excluded from the comparison via `$exclude` argument.
     * Differences in the corresponding columns will be ignored!
     *
     * @param DataSheetInterface $otherSheet
     * @param DataColumnInterface[]|MetaAttributeInterface[]|ExpressionInterface[]|string[] $exclude
     * @return array
     */
    public function getRowsDiff(DataSheetInterface $otherSheet, array $exclude = []) : array;

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
     * Returns the rows containing column totals as assotiative arrays (similar to regular `getRows()`).
     * 
     * Each row contains only columns, that actually have totals (unless $onlyTotaledCols=false). Multiple total 
     * rows are returned if at least one column has multiple totals.
     * 
     * If the data sheet has no column totals, an empty array is returned.
     * 
     * @param bool $onlyTotaledCols
     * @return array [[ column_id => total value ], [ ... ], ...]
     */
    public function getTotalsRows(bool $onlyTotaledCols = true) : array;
    
    /**
     * Returns a specific totals row by index (starting with 0).
     * 
     * If the index is ommitted, the first totals row (with index 0) is returned.
     * 
     * By default, the totals
     * 
     * @param int $idx
     * @param bool $onlyTotaledCols
     * @return array|NULL
     */
    public function getTotalsRow(int $idx = 0, bool $onlyTotaledCols = true) : ?array;

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
     * Removes all or specified rows of the data sheet without changing anything in the column structure
     *
     * @param int[]|NULL $rowIndexes
     * @return DataSheetInterface
     */
    public function removeRows(array $rowIndexes = null);

    /**
     * Removes a single row of the data sheet.
     * 
     * NOTE: remaining rows will get reindexed: e.g. if you remove row 2 from 4,
     * the remaining rows will have the indexs 0, 1, 2 and not 0, 2, 3!
     * 
     * This important when removing multiple rows by traversing a precalculated 
     * array with row indexes. If a row with a lower index is removed first,
     * all the rows with higher numbers will get reindexed an the precalculated
     * row number will not match anymore. To avoid this, start with higher
     * row indexes:
     * 
     * ```
     *  $rowsNumbers = getRowsToRemove($dataSheet);
     *  rsort($rowNumbers); // Remove higher row number first!!!
     *  foreach ($rowNumbers as $r) {
     *      $dataSheet->removeRow($r);
     *  }
     * ```
     *
     * @param integer $row_number            
     * @return DataSheetInterface
     */
    public function removeRow(int $row_number) : DataSheetInterface;

    /**
     * Removes all rows with the given value in the UID column.
     * 
     * NOTE: this will reindex remaining rows in the data sheet!
     *
     * @param string $instance_uid
     * @return DataSheetInterface
     */
    public function removeRowsByUid($uid);

    /**
     * Removes all rows from the specified column.
     * 
     * If it is the only column in the row, the entire row will be removed.
     *
     * @param string $column_name            
     * @return DataSheetInterface
     */
    public function removeRowsForColumn($column_name);
    
    /**
     * Removes duplicate rows only leaving the first occurrence.
     * 
     * Returns the removed rows with their original indexes
     * 
     * @return array
     */
    public function removeRowDuplicates() : array;

    /**
     * Returns TRUE if the sheet currently does not have data (= no rows) and FALSE otherwise.
     * 
     * @param bool $checkValues
     * 
     * @return bool
     */
    public function isEmpty(bool $checkValues = false) : bool;

    /**
     * Returns TRUE if the data in the sheet is up to date and FALSE otherwise (= if the data needs to be loaded)
     *
     * @return boolean
     */
    public function isFresh() : bool;
    
    /**
     * Explicitly marks the sheet as fresh (TRUE) or not (FALSE).
     * 
     * @param bool $value
     * @return DataSheetInterface
     */
    public function setFresh(bool $value) : DataSheetInterface;

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
     * Returns TRUE if the sheet includes only part of the data in the data source and FALSE otherwise.
     * 
     * @return bool
     */
    public function isPaged() : bool;

    public function getRowsLimit() : ?int;

    public function setRowsLimit($value) : DataSheetInterface;

    public function getRowsOffset() : int;

    public function setRowsOffset(int $value) : DataSheetInterface;

    /**
     * Merges the current data sheet with another one.
     * 
     * If $overwriteValues=true, values of the other sheet will overwrite those of identical columns in 
     * the current sheet - otherwise current sheet values will prevale!
     *
     * @param DataSheetInterface $other_sheet            
     */
    public function merge(DataSheetInterface $other_sheet, bool $overwriteValues = true);

    public function getMetaObjectRelationPath(MetaObjectInterface $related_object);

    /**
     * Clones the data sheet and returns the new copy.
     * The copy will point to the same meta object, but will
     * have separate columns, filters, aggregations, etc.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy() : self;

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
     * Returns a new data sheet with the same columns, but only containing rows, that match the given filter
     * 
     * @param ConditionalExpressionInterface $condition
     * @param bool $readMissingData
     * @return DataSheetInterface
     */
    public function extract(ConditionalExpressionInterface $filter, bool $readMissingData = false) : DataSheetInterface;
    
    /**
     * Sorts the current rows using the sorters defined in the sheet or a given sorter list.
     * 
     * By default, all values of the sorted columns are normalized before sorting. This is
     * important for ceratain data types like dates or numbers. This behavior can be explicitly
     * disabled by passing `$normalizeValues = false` to the method.
     * 
     * @param DataSorterListInterface $sorters
     * @param bool $normalizeValues
     * @return DataSheetInterface
     */
    public function sort(DataSorterListInterface $sorters = null, bool $normalizeValues = true) : DataSheetInterface;
    
    /**
     * Returns TRUE if the data will be aggregated to a single line when loading.
     * 
     * This is the case, if all columns have an aggregator - even if there are
     * no aggregators explicitly defined for the sheet.
     * 
     * @return bool
     */
    public function hasAggregateAll() : bool;
    
    /**
     * Returns rows from the data sheet. Encrypted values are returned decrypted.
     * 
     * @return array
     */
    public function getRowsDecrypted($how_many = 0, $offset = 0) : array;
    
    /**
     * Returns TRUE if automatic sorting according to the metamodel is to be used and FALSE otherwise.
     * 
     * @return bool
     */
    public function getAutoSort() : bool;
    
    /**
     * Disable/enable automatic sorting according to the metamodel
     * 
     * @param bool $value
     * @return DataSheetInterface
     */
    public function setAutoSort(bool $value) : DataSheetInterface;
    
    /**
     * Substitues values in columns with DataType marked as sensitive with 'CENSORED'
     *
     * @return DataSheetInterface
     */
    public function getCensoredDataSheet() : DataSheetInterface;
}