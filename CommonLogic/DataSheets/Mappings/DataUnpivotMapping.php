<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataMapperConfigurationError;
use exface\Core\Factories\FormulaFactory;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Tansforms selected columns to rows.
 * 
 * Things to keep in mind:
 * 
 * - Unpivoting will increase the number of rows if more than one column are being transposed. There
 * will be no obvious connection between the rows before and after the process.
 * - If you need to transpose only certain columns, use `column_to_column_mapping`s for the other ones.
 * 
 * ## Example
 * 
 * The following example will transform this
 * 
 * | Col1 | Col2 | Col3 | Col4 |
 * | ---- | ---- | ---- | ---- |
 * | V011 | V012 | V013 | V014 |
 * | V021 | V022 | V023 | V024 |
 * 
 * to this
 * 
 * | Col1 | Col2 | Labels | Values |
 * | ---- | ---- | ------ | ------ |
 * | V011 | V012 |  Col3  |  V013  |
 * | V011 | V012 |  Col3  |  V014  |
 * | V021 | V022 |  Col4  |  V023  |
 * | V021 | V022 |  Col4  |  V024  |
 * 
 * using the following mapper configuration
 * 
 * ```
 * {
 *     "from_object_alias": "suedlink.KMTS.StageCablePRY",
 *     "column_to_column_mappings": [
 *       {
 *         "from": "Col1",
 *         "to": "Col1"
 *       },
 *       {
 *         "from": "Col2",
 *         "to": "Col2"
 *       },
 *     ],
 *     "unpivot_mappings": [{
 *          "from_columns": [
 *              "Col3",
 *              "Col4"
 *          ],
 *          "to_labels_column": "Labels",
 *          "to_values_column": "Values"
 *     }]
 * }
 * 
 * ```
 * 
 * ## Configuration
 * 
 * - `from_columns` - list of column expressions to unpivot (if you know all of them)
 * - `from_column_calculation` - formula to calcuate possible column expressions if they are dynamic - e.g. 
 * to `=Lookup()` them in master data
 * - `to_labels_column` - expression of the to-object to place the labels (column expression)
 * - `to_values_column` - expression of the to-object to place the cell values
 * 
 * @see DataColumnMappingInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataUnpivotMapping extends AbstractDataSheetMapping
{
    private $fromColsStrings = [];
    
    private $fromColsExprs = null;
    
    private $toColForLabels = null;
    
    private $toLabelMappings = [];
    
    private $toColForValues = null;
    
    private $ignoreEmptyValues = false;
    
    private $ignoreEmptyValuesInColumns = [];

    private $ignoreIfMissingFromColumn = false;
    
    /**
     * Array of columns to be transposed
     * 
     * @uxon-property from_columns
     * @uxon-type metamodel:expression[]
     * @uxon-template [""]
     * 
     * @param UxonObject|array $expressions
     * @throws DataMappingConfigurationError
     * @return DataUnpivotMapping
     */
    protected function setFromColumns($expressions) : DataUnpivotMapping
    {
        switch (true) {
            case $expressions instanceof UxonObject:
                $array = $expressions->toArray();
                break;
            case is_array($expressions):
                $array = $expressions;
                break;
            default:
                throw new DataMappingConfigurationError($this, 'Unpivot configuration property `to_labels_mapping` must be an array!');
        }
        
        $this->fromColsExprs = null;
        $this->fromColsStrings = $array;
        return $this;
    }

    /**
     * Calculate from column names using a formula (use comma as delimiter!)
     * 
     * @uxon-property from_columns_calculation
     * @uxon-type metamodel:formula
     * @uxon-template =
     * 
     * @param string $formula
     * @return DataUnpivotMapping
     */
    protected function setFromColumnsCalculation(string $formula) : DataUnpivotMapping
    {
        $expr = FormulaFactory::createFromString($this->getWorkbench(), $formula);
        $list = $expr->evaluate();
        $array = explode(',', $list);
        $array = array_map('trim', $array);
        return $this->setFromColumns($array);
    }

    /**
     * 
     * @return ExpressionInterface[]
     */
    protected function getFromColumnsExpressions() : array
    {
        if ($this->fromColsExprs === null) {
            $this->fromColsExprs = [];
            foreach ($this->fromColsStrings as $exprString) {
                $this->fromColsExprs[] = ExpressionFactory::createFromString($this->getWorkbench(), $exprString, $this->getMapper()->getFromMetaObject());
            }
        }
        return $this->fromColsExprs;
    }
    
    /**
     * 
     * @return ExpressionInterface
     */
    protected function getToLabelsColumnExpression() : ExpressionInterface
    {
        if (! $this->toColForLabels instanceof ExpressionInterface) {
            $this->toColForLabels = ExpressionFactory::createFromString($this->getWorkbench(), $this->toColForLabels, $this->getMapper()->getToMetaObject());
        }
        return $this->toColForLabels;
    }
    
    /**
     * Name of the data column to place the `to_label_value` in
     * 
     * @uxon-property to_label_column
     * @uxon-type metamodel:expression
     * 
     * @param string $colName
     * @return DataUnpivotMapping
     */
    protected function setToLabelsColumn(string $colName) : DataUnpivotMapping
    {
        $this->toColForLabels = $colName;
        return $this;
    }

    /**
     * 
     * @return ExpressionInterface
     */
    protected function getToValuesColumnExpression() : ExpressionInterface
    {
        if (! $this->toColForValues instanceof ExpressionInterface) {
            $this->toColForValues = ExpressionFactory::createFromString($this->getWorkbench(), $this->toColForValues, $this->getMapper()->getToMetaObject());
        }
        return $this->toColForValues;
    }
    
    /**
     * Name of the data column to place the values in
     * 
     * @uxon-property to_values_column
     * @uxon-type metamodel:expression
     * 
     * @param string $colName
     * @return DataUnpivotMapping
     */
    protected function setToValuesColumn(string $colName) : DataUnpivotMapping
    {
        $this->toColForValues = $colName;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getToLabelsMapping() : array
    {
        return $this->toLabelMappings;
    }
    
    /**
     * Maps column names to desired label-column values
     * 
     * @uxon-property to_label_mapping
     * @uxon-type object
     * @uxon-template {"": ""}
     * 
     * @param UxonObject|array $value
     * @throws DataMappingConfigurationError
     * @return DataUnpivotMapping
     */
    protected function setToLabelsMapping($value) : DataUnpivotMapping
    {
        switch (true) {
            case $value instanceof UxonObject:
                $array = $value->toArray();
                break;
            case is_array($value):
                $array = $value;
                break;
            default:
                throw new DataMappingConfigurationError($this, 'Unpivot configuration property `to_labels_mapping` must be an array!');
        }
        $this->toLabelMappings = $array;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $toExprLabels = $this->getToLabelsColumnExpression();
        $toExprValues = $this->getToValuesColumnExpression();
        $toColLabels = $toSheet->getColumns()->addFromExpression($toExprLabels);
        $toColValues = $toSheet->getColumns()->addFromExpression($toExprValues);
        
        // If the from sheet is empty, unpivoting it wont change anything.
        // Data column references should not result in errors if the data sheet is completely empty
        // Otherwise input-mappers would always produce errors on empty input data!
        if ($fromSheet->isEmpty()) {
            return $toSheet;
        }

        if ($logbook !== null) $logbook->addLine('Unpivoting `' . implode('`, `', $this->fromColsStrings) . ' to `' . $toExprLabels->__toString() . '` and `' . $toExprValues->__toString() . '`');
        if ($logbook !== null) $logbook->addIndent(+1);

        $toRowsBefore = ! $toSheet->isEmpty() ? $toSheet->getRows() : [[]];
        $toRowsAfter = [];
        $ignoreEmpty = $this->getIgnoreEmptyValues();
        foreach ($this->getFromColumnsExpressions() as $fromExpr) {
            switch (true) {
                // Constants and static formulas
                case $fromExpr->isStatic():
                // Formulas with data
                case $fromExpr->isFormula():
                    $dataType = $fromExpr->getDataType();
                    $values = $fromExpr->evaluate($fromSheet);
                    $labelValue = $fromExpr->__toString();
                    break;
                // Data column references
                case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                    $dataType = $fromCol->getDataType();
                    $values = $fromCol->getValues(false);
                    $labelValue = $fromExpr->__toString();
                    break;
                // If not enough data, but explicitly configured to ignore it, exit here
                case $this->getIgnoreIfMissingFromColumn() === true && ($fromExpr->isMetaAttribute() || $fromExpr->isFormula() || $fromExpr->isUnknownType()):
                    if ($logbook !== null) $logbook->addLine('Ignoring `' . $fromExpr->__toString() . '` because `ignore_if_missing_from_column` is `true` and no from-data was found.');
                    continue 2;
                // Otherwise error
                default:
                    if ($fromExpr->isMetaAttribute()) {
                        throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot map from attribute "' . $fromExpr->__toString() . '" in a column-to-column mapping: there is no matching column in the from-data and it cannot be loaded automatically (e.g. because the from-object ' . $fromSheet->getMetaObject() .' has no UID attribute)!', '7H6M243');
                    }
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $fromExpr->__toString() . '" as from-expression in a column-to-column mapping: only data column names, constants and formulas allowed!', '7H6M243');
            }
            
            foreach ($toRowsBefore as $i => $toRow) {
                $val = $values[$i];
                if ($ignoreEmpty && $dataType->isValueEmpty($val)) {
                    continue;
                }
                if (in_array($fromExpr->__toString(), $this->ignoreEmptyValuesInColumns) && $dataType->isValueEmpty($val)) {
                    continue;
                }
                $toRow[$toColLabels->getName()] = $labelValue;
                $toRow[$toColValues->getName()] = $val;
                $toRowsAfter[] = $toRow;
            }
        }

        if ($logbook !== null) $logbook->addLine('Transformed **' . $toSheet->countRows() . ' rows into ' . count($toRowsAfter) . '** unpivoted rows.');
        if ($logbook !== null) $logbook->addIndent(-1);

        $toSheet->removeRows()->addRows($toRowsAfter);
        
        return $toSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return $this->getFromColumnsExpressions();
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIgnoreEmptyValues() : bool
    {
        return $this->ignoreEmptyValues;
    }
    
    /**
     * Set to TRUE to NOT create new rows if the `from`-value is empty.
     * 
     * You can also turn this feature on for specific from-columns only by using
     * `ignore_empty_values_in_columns`.
     * 
     * @uxon-property ignore_empty_values
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return DataUnpivotMapping
     */
    protected function setIgnoreEmptyValues(bool $value) : DataUnpivotMapping
    {
        $this->ignoreEmptyValues = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getIgnoreEmptyValuesInColumns() : array
    {
        return $this->ignoreEmptyValuesInColumns;
    }
    
    /**
     * Do not create rows for empty values in the listed from-column only.
     * 
     * You can also turn enable this filter for all columns using `ignore_empty_values`.
     * 
     * @uxon-property ignore_empty_values_in_columns
     * @uxon-type metamodel:expression[]
     * @uxon-template [""]
     * 
     * @param UxonObject|string[] $value
     * @throws DataMapperConfigurationError
     * @return DataUnpivotMapping
     */
    protected function setIgnoreEmptyValuesInColumns($value) : DataUnpivotMapping
    {
        switch (true) {
            case $value instanceof UxonObject:
                $array = $value->toArray();
                break;
            case is_array($value):
                $array = $value;
                break;
            default:
                throw new DataMapperConfigurationError($this, 'Invalid value for `ignore_empty_values_in_columns`: expection an array of expressions!');
        }
        $this->ignoreEmptyValuesInColumns = $array;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function getIgnoreIfMissingFromColumn() : bool
    {
        return $this->ignoreIfMissingFromColumn;
    }

    /**
     * Set to TRUE if this mapping is only to be applied if there is a corresponding from-data
     * 
     * By default the mapping will result in an error if the from-data does not have the 
     * required data.
     * 
     * @uxon-property ignore_if_missing_from_column
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return DataColumnMapping
     */
    protected function setIgnoreIfMissingFromColumn(bool $trueOrFalse) : DataUnpivotMapping
    {
        $this->ignoreIfMissingFromColumn = $trueOrFalse;
        return $this;
    }
}