<?php
namespace exface\Core\Interfaces\Formulas;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

interface FormulaInterface extends ExfaceClassInterface
{

    /**
     * Parses the the arguments for this function.
     * Each argument
     * is an ExFace expression, which in turn can be another function,
     * a reference, a constant - whatever. We generally instatiate
     * expression objects for the arguments together with the function
     * and not while applying the function to data, because argument types
     * do not change depending on the contents of cells of data_sheets.
     * It is faster to create the respective expressions here and just
     * evaluate them, when really running the function.
     *
     * @param
     *            array arguments
     * @return void
     */
    public function init(array $arguments);

    /**
     * Evaluates the function based on a given data sheet and the coordinates
     * of a cell (data functions are only applicable to specific cells!)
     * This method is called for every row of a data sheet, while the function
     * is mostly defined for an entire column, so we try to do as little as possible
     * here: evaluate each argument's expression and call the run() method with
     * the resulting values. At this point all arguments are ExFace expressions
     * already. They where instantiated together with the function.
     *
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet            
     * @param string $column_name            
     * @param int $row_number            
     * @return mixed
     */
    public function evaluate(DataSheetInterface $data_sheet, $column_name, $row_number);

    /**
     * Returns the data sheet, the formula is being run on
     *
     * @return DataSheetInterface
     */
    public function getDataSheet();

    /**
     * Returns the data type, that the formula will produce
     *
     * @return DataTypeInterface
     */
    public function getDataType();

    /**
     * Returns the column name of the data sheet column currently being processed
     *
     * @return string
     */
    public function getCurrentColumnName();
    
    /**
     * Returns the column of the data sheet, that the formula is being applied to
     * 
     * @return DataColumnInterface
     */
    public function getCurrentColumn();

    /**
     * Returns the row number in the data sheet currently being processed.
     *
     * @return integer
     */
    public function getCurrentRowNumber();
}
