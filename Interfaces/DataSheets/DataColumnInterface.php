<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;

interface DataColumnInterface extends iCanBeConvertedToUxon, iCanBeCopied
{

    /**
     *
     * @param ExpressionInterface|string $expression            
     * @param string $name            
     * @param DataSheetInterface $data_sheet            
     */
    function __construct($expression, $name = '', DataSheetInterface $data_sheet);

    /**
     *
     * @return \exface\Core\Interfaces\Model\ExpressionInterface
     */
    public function getExpressionObj();

    /**
     *
     * @param ExpressionInterface|string $expression_or_string
     */
    public function setExpression($expression_or_string);

    /**
     * Returns the data sheet, which the column belongs to
     *
     * @return DataSheetInterface
     */
    public function getDataSheet();

    /**
     * Updates the parent data sheet by the given one.
     * This can be used to copy columns back and forth, but must be handeld with CAUTION, because it only copies the column, not the values!!!
     *
     * @param DataSheetInterface $data_sheet            
     */
    public function setDataSheet(DataSheetInterface $data_sheet);

    /**
     */
    public function getName();

    /**
     *
     * @param string $value            
     * @param boolean $keep_values            
     */
    public function setName($value, $keep_values = false);

    public function getHidden();

    /**
     *
     * @param boolean $value            
     */
    public function setHidden($value);

    /**
     *
     * @return ExpressionInterface
     */
    public function getFormatter();

    /**
     *
     * @param ExpressionInterface|string $expression            
     */
    public function setFormatter($expression);

    /**
     *
     * @return AbstractDataType
     */
    public function getDataType();

    /**
     *
     * @param AbstractDataType|string $data_type_or_string            
     */
    public function setDataType($data_type_or_string);

    /**
     * Returns the attribute in this column if the column represents a single attribute.
     * Returns FALSE if the column
     * represents anything else, like a forumula, a constant, etc.
     *
     * @throws MetaAttributeNotFoundError if it is expected to be an attribute, but is not found for the object of the column
     * @return MetaAttributeInterface
     */
    public function getAttribute();

    /**
     * Returns an array of values of this data sheet column.
     * This is just a shortcut to DataSheet->getColumnValues()
     *
     * @param boolean $include_totals            
     * @return array
     */
    public function getValues($include_totals = false);

    /**
     * Returns the value of the given row within this column
     *
     * @param integer $row_number            
     * @return mixed
     */
    public function getCellValue($row_number);

    /**
     *
     * @param array $column_values            
     * @param array $totals_values            
     * @return DataColumnInterface
     */
    public function setValues($column_values, $totals_values = null);

    /**
     * Sets the values of the column by evaluating the given expression for this column
     *
     * @param ExpressionInterface $expression            
     * @return @return DataColumnInterface
     */
    public function setValuesByExpression(ExpressionInterface $expression, $overwrite = true);

    /**
     *
     * @return boolean
     */
    public function isFresh();

    /**
     *
     * @param boolean $value            
     */
    public function setFresh($value);

    /**
     * Clones the column and returns the new copy
     *
     * @return DataColumnInterface
     */
    public function copy();

    /**
     * Returns the sequential number of the first row, that contains the given value or FALSE if none of the
     * cells of this column match the value.
     * It's a shortcut to getting the first element of find_rows_by_value(),
     * which is very handy for searching the UID column (which should not contain multiple rows with the same value).
     *
     * @param string $cell_value            
     * @param boolean $case_sensitive            
     * @return integer
     */
    public function findRowByValue($cell_value, $case_sensitive = false);

    /**
     * Returns an array of sequential numbers of all rows, that contain the given value.
     * If the value could not be
     * found, the returned array will be empty.
     *
     * If a single matching row is expected, find_row_by_value() is a better choice. It will return the row number
     * of the first match right away instead of an array.
     *
     * @see find_row_by_value()
     *
     * @param string $cell_value            
     * @param boolean $case_sensitive            
     * @return int[]
     */
    public function findRowsByValue($cell_value, $case_sensitive = false);
    
    /**
     * Returns an array with indexes of rows, where the column has empty values (i.e. NULL or '').
     * 
     * Will return an empty array for empty columns!
     * 
     * @return int[]
     */
    public function findEmptyRows() : array;

    /**
     * Returns an array with all values of this column, which are not present in another one.
     * It does not matter,
     * if the values are in corresponding rows or not, so there is no need to take care of sorting rows.
     *
     * NOTE: The keys of the returned array are not the row numbers!
     * This method only relies on the values ignoring the row numbers. Thus, if this column has value A in row 1
     * and value B in row 2 and the other column hast value B in row 1 and value A in row 2, the diff will return
     * an empty array, because both values are present in both columns (however in different rows).
     * If you need to compare the columns per-row, use diff_rows() instead.
     *
     * @param DataColumnInterface $another_column            
     * @return array
     */
    public function diffValues(DataColumnInterface $another_column);

    /**
     * Returns an array with all values of this column, that are different from values of the other in rows with
     * corresponding UIDs.
     * NOTE: The keys of the returned array are the UIDs of the diff rows
     * Similarly to diff_values() this method does not pay attention to the order of rows in both columns.
     *
     * @param DataColumnInterface $another_column            
     * @return array
     */
    public function diffValuesByUid(DataColumnInterface $another_column);

    /**
     * Returns an array with with all values of this column, which are not present in the same row of another one.
     * NOTE: The keys of the returned array are the row numbers of this column
     * In contrast to diff_values(), this method compares the column per row.
     *
     * @param DataColumnInterface $another_column            
     * @return array
     */
    public function diffRows(DataColumnInterface $another_column);

    /**
     *
     * @return ExpressionInterface
     */
    public function getFormula();

    /**
     *
     * @param string|ExpressionInterface $expression_or_string            
     */
    public function setFormula($expression_or_string);

    public function getAttributeAlias();

    public function setAttributeAlias($value);

    /**
     * Retruns a list with all total row functions for this column
     *
     * @return DataColumnTotalsListInterface
     */
    public function getTotals();
    
    /**
     * Returns TRUE if the column has at least one total function and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasTotals();

    /**
     * Returns FALSE if the column contains at least one data row (with non-empty values 
     * if $check_values is TRUE) and TRUE otherwise.
     *
     * @param boolean $check_values
     * @return boolean
     */
    public function isEmpty($check_values = false);
    
    /**
     * Returns TRUE if the column has at least one empty value (i.e. NULL or '')
     * 
     * @return bool
     */
    public function hasEmptyValues() : bool;

    /**
     * Applies default and fixed values defined in the meta model to this column.
     *
     * @param bool $leaveNoEmptyValues
     * @throws \RuntimeException if $leaveNoEmptyValues == true AND a value could not be filled
     * @return DataColumnInterface
     */
    public function setValuesFromDefaults(bool $leaveNoEmptyValues = true) : DataColumnInterface;

    /**
     *
     * @param integer $row_number            
     * @param string $value            
     */
    public function setValue($row_number, $value);
    
    /**
     * Replaces the value of this column in all rows by the given one.
     * 
     * @param mixed $value
     * @return DataColumnInterface
     */
    public function setValueOnAllRows($value) : DataColumnInterface;

    /**
     * Returns TRUE if setting fixed values from the meta model is disabled for this column and FALSE otherwise
     *
     * @return boolean
     */
    public function getIgnoreFixedValues() : bool;

    /**
     * Prevents setting fixed values based on expressions in the meta model for this column if set to TRUE
     *
     * @param boolean $value            
     * @return \exface\Core\Interfaces\DataSheets\DataColumnInterface
     */
    public function setIgnoreFixedValues(bool $value) : DataColumnInterface;

    /**
     * Removes all rows from this column, thus making it empty
     *
     * @return DataColumnInterface
     */
    public function removeRows();

    /**
     * Returns the aggregation result for all current values of the column.
     * 
     * The aggregation is applied in-memory to the currently loaded values. No operation is performed
     * on the data source!
     * 
     * If no aggregator is specified, the a default will be determined as follows:
     * - If the column has totals (e.g. a sum in the footer), the aggregator of the first total will
     * be applied.
     * - If the column is bound to a meta attribute, the attribute's default aggregate function will
     * be used.
     * - Otherwise a DataSheetRuntimeError will be thrown.
     * 
     * If LIST-aggregators are used on a column bound to a meta attribute, the default value list
     * delimiter will be used (unless a delimiter is specified explicitly in the aggregator)
     *
     * @param AggregatorInterface|string $aggregator 
     * @throws DataSheetRuntimeError           
     * @return string|float
     */
    public function aggregate($aggregatorOrString = null);

    /**
     * Returns the meta object of this data column
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMetaObject();
    
    /**
     * Returns TRUE if this column shows a meta attribute and FALSE otherwise (e.g. formula, etc.)
     * 
     * @return boolean
     */
    public function isAttribute() : bool;
    
    /**
     * 
     * Returns TRUE if this column shows a formula and FALSE otherwise
     * 
     * @return boolean
     */
    public function isFormula() : bool;
    
    /**
     * Returns TRUE if this is a calculated column - that is, it's data does not (only) come from a data source.
     * 
     * @return boolean
     */
    public function isCalculated() : bool;
    
    /**
     * Returns TRUE if the value of this column does not depend on any data (e.g. is a fixed string, number or a static formula) and FALSE otherwise.
     * 
     * @return bool
     */
    public function isStatic() : bool;
    
    /**
     * Returns the aggregator defined for this column or NULL if it is not aggregated
     * 
     * @return AggregatorInterface|NULL
     */
    public function getAggregator() : ?AggregatorInterface;
    
    /**
     * Returns TRUE if an aggregator is defined for this column (i.e. attribute_alias:SUM)
     * 
     * @return bool
     */
    public function hasAggregator() : bool;
    
    /**
     * Replaces all values in the column by their normalized versions according to the column's data type.
     * 
     * @throws DataTypeCastingError
     * @throws DataTypeValidationError
     * 
     * @return void
     */
    public function normalizeValues();
}