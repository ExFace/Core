<?php namespace exface\Core\Interfaces\DataSheets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\Model\DataTypes\AbstractDataType;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Exceptions\DataSheetException;
use exface\Core\Interfaces\DataSheets\DataColumnTotalsListInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\DataTypes\Boolean;
use exface\Core\Interfaces\iCanBeCopied;

interface DataColumnInterface extends iCanBeConvertedToUxon, iCanBeCopied {
	
	/**
	 * 
	 * @param unknown $expression
	 * @param string $name
	 * @param DataSheetInterface $data_sheet
	 */
	function __construct($expression, $name='', DataSheetInterface &$data_sheet);
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Expression
	 */
	public function get_expression_obj();
	
	/**
	 * 
	 * @param Expression | string $expression_or_string
	 */
	public function set_expression($expression_or_string);
	
	/**
	 * Returns the data sheet, which the column belongs to
	 * @return DataSheetInterface
	 */
	public function get_data_sheet();
	
	/**
	 * Updates the parent data sheet by the given one. This can be used to copy columns back and forth, but must be handeld with CAUTION, because it only copies the column, not the values!!!
	 * @param DataSheet $data_sheet
	 */
	public function set_data_sheet(DataSheetInterface &$data_sheet);
	
	/**
	 * 
	 */
	public function get_name();
	
	/**
	 * 
	 * @param string $value
	 */
	public function set_name($value);
	
	public function get_hidden();
	
	/**
	 * 
	 * @param boolean $value
	 */
	public function set_hidden($value);
	
	/**
	 * @return Expression
	 */
	public function get_formatter();
	
	/**
	 * 
	 * @param Expression $expression
	 */
	public function set_formatter($expression);
	
	/**
	 * @return AbstractDataType
	 */
	public function get_data_type();
	
	/**
	 *
	 * @param AbstractDataType|string $data_type_or_string
	 */
	public function set_data_type($data_type_or_string);
	
	/**
	 * Returns the attribute in this column if the column represents a single attribute. Returns FALSE if the column
	 * represents anything else, like a forumula, a constant, etc.
	 * 
	 * @return Attribute|boolean
	 */
	public function get_attribute();   
	
	/**
	 * Returns an array of values of this data sheet column. This is just a shortcut to DataSheet->get_column_values()
	 * @param boolean $include_totals
	 * @return array
	 */
	public function get_values($include_totals = false);
	
	/**
	 * Returns the value of the given row within this column
	 * @param integer $row_number
	 * @return mixed
	 */
	public function get_cell_value($row_number);
	
	/**
	 * 
	 * @param array $column_values
	 * @param array $totals_values
	 */
	public function set_values($column_values, $totals_values = null);
	
	/**
	 * Sets the values of the column by evaluating the given expression for this column
	 * @param expression $expression
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 */
	public function set_values_by_expression(Expression $expression);
	
	/**
	 * @return boolean
	 */
	public function is_up_to_date();
	
	/**
	 * 
	 * @param boolean $value
	 */
	public function set_up_to_date($value);
	
	/**
	 * Clones the column and returns the new copy
	 * @return DataColumn
	 */
	public function copy();
	
	/**
	 * Returns the sequential number of the row, that contains the given value or FALSE if none of the cells of this column match the value.
	 * @param string $cell_value
	 * @return integer
	 */
	public function find_row_by_value($cell_value);
	
	/**
	 * Returns an array with all values of this column, which are not present in another one. 
	 * NOTE: The keys of the returned array are not the row numbers!
	 * This method only relies on the values ignoring the row numbers. Thus, if this column has value A in row 1 
	 * and value B in row 2 and the other column hast value B in row 1 and value A in row 2, the diff will return 
	 * an empty array, because both values are present in both columns (however in different rows). 
	 * If you need to compare the columns per-row, use diff_rows() instead.
	 * @param DataColumn $another_column
	 * @return array
	 */
	public function diff_values(DataColumnInterface $another_column);
	
	/**
	 * Returns an array with all values of this column, that are different from values of the other in rows with
	 * corresponding UIDs. 
	 * NOTE: The keys of the returned array are the UIDs of the diff rows
	 * Similarly to diff_values() this method does not pay attention to the order of rows in both columns.
	 * @param DataColumnInterface $another_column
	 * @return array
	 */
	public function diff_values_by_uid(DataColumnInterface $another_column);
	
	/**
	 * Returns an array with with all values of this column, which are not present in the same row of another one.
	 * NOTE: The keys of the returned array are the row numbers of this column
	 * In contrast to diff_values(), this method compares the column per row.
	 * @param DataColumnInterface $another_column
	 * @return array
	 */
	public function diff_rows(DataColumnInterface $another_column);
	
	/**
	 * @return Expression
	 */
	public function get_formula();
	
	/**
	 * 
	 * @param string|Expression $expression_or_string
	 * @throws DataSheetException
	 * @return DataColumnInterface
	 */
	public function set_formula($expression_or_string);
	
	public function get_attribute_alias();
	
	public function set_attribute_alias($value);
	
	/**
	 * Retruns a list with all total row functions for this column
	 * @return DataColumnTotalsListInterface
	 */
	public function get_totals();
	
	/**
	 * Returns TRUE if the column contains at least one data row and FALSE otherwise
	 * @return boolean
	 */
	public function is_empty();
	
	/**
	 * Applies default and fixed values defined in the meta model to this column.
	 * @throws \exface\Core\Exceptions\DataSheetException
	 * @return DataColumnInterface
	 */
	public function set_values_from_defaults();
	
	/**
	 * 
	 * @param integer $row_number
	 * @param string $value
	 */
	public function set_value($row_number, $value);
 
}