<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\EntityListFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataSheetDiffError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DataSheetDataType;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataSheets\DataSheetInvalidValueError;

class DataColumn implements DataColumnInterface
{
    const COLUMN_NAME_VALIDATOR = '[^A-Za-z0-9_]';

    // Properties, _not_ to be dublicated on copy()
    private $data_sheet = null;

    // Properties, to be dublicated on copy()
    private $name = null;
    
    private $title = null;

    private $attribute_alias = null;

    private $hidden = false;

    private $data_type = null;

    private $fresh = false;

    private $totals = null;

    private $ignore_fixed_values = false;

    /** @var ExpressionInterface */
    private $expression = null;

    /** @var Formula */
    private $formula = null;

    private $writable = null;

    function __construct($expression, DataSheetInterface $data_sheet, $name = '')
    {
        $this->data_sheet = $data_sheet;
        $this->setExpression($expression);
        $this->setName($name ? $name : $this->getExpressionObj()->toString());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getExpressionObj()
     */
    public function getExpressionObj()
    {
        if ($this->expression === null || $this->expression->isEmpty()) {
            if ($this->attribute_alias) {
                $exface = $this->getWorkbench();
                $this->expression = ExpressionFactory::createFromString($exface, $this->getAttributeAlias(), $this->getMetaObject());
            }
        }
        // Make sure, there is always a meta object in the expression. For some reason, this is not always the case.
        // IDEA this check can be removed, once meta object have become mandatory for expressions (planned in distant future)
        if (! $this->expression->getMetaObject()) {
            $this->expression->setMetaObject($this->getMetaObject());
        }
        return $this->expression;
    }

    /**
     * The expression to fill this column with values
     * 
     * @uxon-property expression
     * @uxon-type metamodel:expression
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setExpression()
     */
    public function setExpression($expression_or_string)
    {
        if (($expression_or_string instanceof ExpressionInterface) === false) {
            $exface = $this->getWorkbench();
            $expression = ExpressionFactory::createFromString($exface, $expression_or_string, $this->getMetaObject());
        } else {
            $expression = $expression_or_string;
            $exprObj = $expression->getMetaObject();
            $thisObj = $this->getMetaObject();
            if ($exprObj === null) {
                $expression->setMetaObject($thisObj);
            } elseif (! $thisObj->is($exprObj)) {
                throw new DataSheetRuntimeError($this->getDataSheet(), 'Cannot add expression "' . $expression->__toString() . '" based on object ' . $exprObj->__toString() . ' to data sheet of ' . $thisObj->__toString() . '!');
            }
        }
        
        $this->expression = $expression;
        $this->data_type = null;
        
        if ($expression->isMetaAttribute()) {
            $this->setAttributeAlias($expression->toString());
        }
        
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getDataSheet()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getDataSheet()
     */
    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setDataSheet()
     */
    public function setDataSheet(DataSheetInterface $data_sheet)
    {
        $this->data_sheet = $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * The name of this column
     * 
     * @uxon-property name
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setName()
     */
    public function setName($value, $keep_values = false)
    {
        // If we need to keep values and the column is being renamed (in contrast to being created the first time),
        // remember the current values a clear them from the data sheet
        if ($keep_values && ! is_null($this->name)) {
            $old_values = $this->getValues(false);
            $this->removeRows();
        }
        
        // Set the new column name
        $this->name = static::sanitizeColumnName($value);
        
        // If we need to keep values and the column had some previously, restore them.
        if ($keep_values && count($old_values) > 0) {
            $this->setValues($old_values);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getHidden()
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setHidden()
     */
    public function setHidden($value)
    {
        $this->hidden = BooleanDataType::cast($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getDataType()
     */
    public function getDataType()
    {
        if (null === $this->data_type) {
            // Determine the data type from the columns expression.
            // However, attributes need some special treatment as we need to detect columns
            // with subsheets - that is, attributes, that represent a reverse relation.
            if (null !== $attribute_alias = $this->getAttributeAlias()) {
                // If the column's alias expression is actually a reverse relation, it must
                // contain a subsheet because a reverse relation is not an attribute of it's
                // left object and, thus, cannot contain scalar values.
                if ($this->getMetaObject()->hasRelation($attribute_alias) === true && $this->getMetaObject()->getRelation($attribute_alias)->isReverseRelation() === true){
                    $this->data_type = DataTypeFactory::createFromPrototype($this->getWorkbench(), DataSheetDataType::class);
                } else {
                    try {
                        $this->data_type = $this->getExpressionObj()->getDataType();
                    } catch (MetaAttributeNotFoundError $e) {
                        // ignore expressions with invalid attribute aliases
                    }
                }
            } else {
                $this->data_type = $this->getExpressionObj()->getDataType(); 
            }

            // If the data type could not be determined from the expression, set the default
            // data type - string.
            if ($this->data_type === null) {
                $this->data_type = DataTypeFactory::createBaseDataType($this->getWorkbench());
            }
        }
        return $this->data_type;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setDataType()
     */
    public function setDataType($data_type_or_string)
    {
        if ($data_type_or_string) {
            if ($data_type_or_string instanceof AbstractDataType) {
                $this->data_type = $data_type_or_string;
            } else {
                $exface = $this->getWorkbench();
                $this->data_type = DataTypeFactory::createFromString($exface, $data_type_or_string);
            }
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getAttribute()
     */
    public function getAttribute()
    {
        if ($this->isAttribute()) {
            return $this->getMetaObject()->getAttribute($this->getAttributeAlias());
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getValues()
     */
    public function getValues($include_totals = false)
    {
        return $this->getDataSheet()->getColumnValues($this->getName(), $include_totals);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getValuesNormalized()
     */
    public function getValuesNormalized() : array
    {
        $type = $this->getDataType();
        $vals = [];
        foreach ($this->getValues(false) as $rowIdx => $val) {
            try {
                $vals[$rowIdx] = $type->parse($val);
            } catch (DataTypeValidationError $e) {
                throw new DataSheetInvalidValueError($this->getDataSheet(), null, null, $e, $this, [$rowIdx]);
            }
        }
        return $vals;
    }

    /**
     *
     * @deprecated use getValue() instead!
     */
    public function getCellValue($row_number)
    {
        return $this->getDataSheet()->getCellValue($this->getName(), $row_number);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getValue()
     */
    public function getValue(int $rowNumber)
    {
        return $this->getDataSheet()->getCellValue($this->getName(), $rowNumber);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getValueByUid()
     */
    public function getValueByUid(string $uidValue)
    {
        return $this->getValue($this->getDataSheet()->getUidColumn()->findRowByValue($uidValue));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setValues()
     */
    public function setValues($column_values, $totals_values = null)
    {
        $this->getDataSheet()->setColumnValues($this->getName(), $column_values, $totals_values);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setValuesByExpression()
     */
    public function setValuesByExpression(ExpressionInterface $expression, $overwrite = true)
    {
        // Don't do anything, if there are no rows - nothing to calculate!
        if ($this->getDataSheet()->isEmpty()) {
            return $this;
        }
        // If there are rows, but this column is empty, or we will be overwriting - calculate
        if ($overwrite || $this->isEmpty()) {
            $this->setValues($expression->evaluate($this->getDataSheet()));
        } else {
            foreach ($this->getValues(false) as $row => $val) {
                if ($val !== null && $val !== '') {
                    $this->setValue($row, $expression->evaluate($this->getDataSheet(), $row));
                }
            }
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isFresh()
     */
    public function isFresh()
    {
        return $this->fresh;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setFresh()
     */
    public function setFresh($value)
    {
        $this->fresh = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::copy()
     */
    public function copy() : self
    {
        $copy = clone $this;
        if ($expr = $this->getExpressionObj()) {
            $copy->setExpression($expr->copy());
        }
        if ($this->formula !== null) {
            $copy->setFormula($this->formula->copy());
        }
        return $copy;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $arr = [];
        
        // Allways export some basic properties of the column first
        $arr['name'] = $this->getName();
        
        if ($this->getHidden()) {
            $arr['hidden'] = $this->getHidden();
        }
        
        if ($this->hasTotals()) {
            $arr['totals'] = $this->getTotals()->exportUxonObject()->toArray();
        }
        
        if ($this->isAttribute()) {
            // If it contains an attribute, it will be enough to export it's alias and every thing
            // else only if it differs from attribute data
            $arr['attribute_alias'] = $this->attribute_alias;
            
            if ($this->getAttribute()->getDataType() !== $this->getDataType()) {
                $arr['data_type'] = $this->getDataType()->getAliasWithNamespace();
            }
            
            if ($this->formula !== null && $this->getAttribute()->getFormula() !== $this->formula) {
                $arr['formula'] = $this->formula->toString();
            }
        } else {
            // If it's not an attribute, export everything
            $arr['expression'] = $this->getExpressionObj()->toString();
            $arr['data_type'] = $this->getDataType()->getAliasWithNamespace();
        
            if ($this->formula !== null) {
                $arr['formula'] = $this->formula->toString();
            }
        }
        
        return new UxonObject($arr);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isAttribute()
     */
    public function isAttribute() : bool
    {
        return $this->getAttributeAlias() ? true : false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isFormula()
     */
    public function isFormula() : bool
    {
        return $this->formula !== null || $this->getExpressionObj()->isFormula(); 
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isCalculated()
     */
    public function isCalculated() : bool
    {
        return $this->isFormula() || $this->getExpressionObj()->isConstant();
    }
    
    public function isStatic() : bool
    {
        return $this->getExpressionObj()->isStatic();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $this->setHidden($uxon->getProperty('hidden'));
        if ($uxon->hasProperty('data_type')) {
            $this->setDataType($uxon->getProperty('data_type'));
        }
        $this->setFormula($uxon->getProperty('formula'));
        $this->setAttributeAlias($uxon->getProperty('attribute_alias'));
        if ($uxon->hasProperty('totals')) {
            foreach ($uxon->getProperty('totals') as $u) {
                $total = DataColumnTotalsFactory::createFromUxon($this, $u);
                $this->getTotals()->add($total);
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::findRowByValue()
     */
    public function findRowByValue($cell_value, $case_sensitive = false)
    {
        $result = false;
        if ($case_sensitive) {
            $result = array_search($cell_value, $this->getValues(false));
        } else {
            foreach ($this->getValues(false) as $row_nr => $row_val) {
                if (strcasecmp($cell_value, $row_val) === 0) {
                    $result = $row_nr;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::findRowsByValue()
     */
    public function findRowsByValue($cell_value, $case_sensitive = false)
    {
        $result = array();
        if ($case_sensitive) {
            $result = array_keys($this->getValues(false), $cell_value);
        } else {
            foreach ($this->getValues(false) as $row_nr => $row_val) {
                if (strcasecmp($cell_value ?? '', $row_val ?? '') === 0) {
                    $result[] = $row_nr;
                }
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::diffValues()
     */
    public function diffValues(DataColumnInterface $another_column)
    {
        return array_diff($this->getValues(false), $another_column->getValues(false));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::diffRows()
     */
    public function diffRows(DataColumnInterface $another_column)
    {
        $result = array();
        foreach ($this->getValues(false) as $row_nr => $val) {
            // Compare with `!=` to ignore the differences between `1` and `"1"` and similar.
            if ($another_column->getCellValue($row_nr) != $val) {
                $result[$row_nr] = $val;
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::diffValuesByUid()
     */
    public function diffValuesByUid(DataColumnInterface $another_column)
    {
        $result = array();
        $this_uid_column = $this->getDataSheet()->getUidColumn();
        $other_uid_column = $another_column->getDataSheet()->getUidColumn();
        if (! $this_uid_column || ! $other_uid_column) {
            throw new DataSheetDiffError($this->getDataSheet(), 'Cannot diff rows by uid for column "' . $this->getName() . '": no UID column found in data sheet!', '6T5UUOI');
        }
        if ($this_uid_column->isEmpty() || $other_uid_column->isEmpty()) {
            throw new DataSheetDiffError($this->getDataSheet(), 'Cannot diff rows by uid for column "' . $this->getName() . '": the UID column has no data!', '6T5UUOI');
        }
        foreach ($this->getValues(false) as $row_nr => $val) {
            $uid = $this_uid_column->getCellValue($row_nr);
            $otherVal = $another_column->getCellValue($other_uid_column->findRowByValue($uid));
            if ($another_column->getDataType()) {
                $otherVal = $another_column->getDataType()::cast($otherVal);
            }
            $thisVal = $val;
            if ($this->getDataType()) {
                $thisVal = $this->getDataType()::cast($val);
            }
            if (mb_strtolower($otherVal ?? '') !== mb_strtolower($thisVal ?? '')) {
                $result[$uid] = $val;
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getFormula()
     */
    public function getFormula()
    {
        return $this->formula ?? ($this->getExpressionObj()->isFormula() ? $this->getExpressionObj() : null);
    }

    /**
     * Make column values be calculated via formula: e.g. `=NOW()` - even if the expression of the column points to an attribute!
     * 
     * This will make the column a calculated column - similarly to a column with a formula in its expression.
     * However, this separate property allows to use an attribute alias as expression and still use a formula
     * to calculate values, so these calculated values will be saved to the attribute when the data is written
     * to the data source. In a sence, this is an alternative to data mappers, that could map a formula-column
     * to an attribute column.
     * 
     * @uxon-property formula
     * @uxon-type metamodel:formula
     * @uxon-template =
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setFormula()
     */
    public function setFormula($expression_or_string) : DataColumn
    {
        if ($expression_or_string) {
            if ($expression_or_string instanceof ExpressionInterface) {
                $expression = $expression_or_string;
            } else {
                $exface = $this->getWorkbench();
                $expression = ExpressionFactory::createFromString($exface, $expression_or_string);
            }
            if (! $expression->isConstant() && ! $expression->isFormula() && ! $expression->isReference()) {
                throw new DataSheetRuntimeError($this->getDataSheet(), 'Invalid formula "' . $expression->toString() . 'given to data sheet column "' . $this->getName() . '"!', '6T5UW0E');
            }
            $this->formula = $expression;
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getAttributeAlias()
     */
    public function getAttributeAlias()
    {
        if (is_null($this->attribute_alias)) {
            if ($this->expression && $this->getExpressionObj()->isMetaAttribute()) {
                $this->attribute_alias = $this->getExpressionObj()->toString();
            }
        }
        return $this->attribute_alias;
    }

    /**
     * Bind the column to an attribute of the meta object
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setAttributeAlias()
     */
    public function setAttributeAlias($value)
    {
        $this->attribute_alias = $value;
        $this->data_type = null;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getTotals()
     */
    public function getTotals()
    {
        if (is_null($this->totals)){
            $this->totals = EntityListFactory::createWithEntityFactory($this->getWorkbench(), $this, 'DataColumnTotalsFactory');
        }
        return $this->totals;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::hasTotals()
     */
    public function hasTotals(){
        if (! is_null($this->totals) && ! $this->getTotals()->isEmpty()){
            return true;
        }
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isEmpty()
     */
    public function isEmpty($check_values = false)
    {
        if ($check_values) {
            foreach ($this->getValues(true) as $val) {
                if (! is_null($val) && $val !== '') {
                    return false;
                }
            }
            return true;
        } elseif (count($this->getValues(true)) > 0) {
            return false;
        }
            
        return true;
    }

    public static function sanitizeColumnName($string)
    {
        $name = preg_replace('/' . self::COLUMN_NAME_VALIDATOR . '/', '_', $string ?? '');
        return $name;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setValuesFromDefaults()
     */
    public function setValuesFromDefaults(bool $leaveNoEmptyValues = true) : DataColumnInterface
    {
        if ($this->getExpressionObj()->isMetaAttribute() === false || ! $attr = $this->getAttribute()) {
            return $this;
        }
        $fixedEx = $attr->getFixedValue();
        $defaultEx = $attr->getDefaultValue();
        $sheet = $this->getDataSheet();
        
        if ($fixedEx && $this->getIgnoreFixedValues() === false) {
            // Fixed values MUST be calculated unless this feature is explicitly disabled for the column
            foreach ($this->getValues(false) as $rowIdx => $val) {
                $this->setValue($rowIdx, $fixedEx->evaluate($sheet, $rowIdx));
            }
        }
        
        // After fixed values were calculated (which theoretically could also lead to empty values!), we
        // will proceed with calculating default values for empty cells
        $missingInRowIdxs = [];
        foreach ($this->getValues(false) as $rowIdx => $val) {
            if ($val === null || $val === '') {
                if ($attr->getDefaultValue()) {
                    $this->setValue($rowIdx, $defaultEx->evaluate($sheet, $rowIdx));
                } elseif ($leaveNoEmptyValues === true) {
                    // If a value is still empty and we do not want it to be so - throw an error!
                    $missingInRowIdxs[] = $rowIdx;
                }
            }
        }
        if (! empty($missingInRowIdxs)) {
            throw new DataSheetMissingRequiredValueError($sheet, null, null, null, $this, $missingInRowIdxs);
        }
        
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setValue()
     */
    public function setValue($row_number, $value)
    {
        $this->getDataSheet()->setCellValue($this->getName(), $row_number, $value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setValueOnAllRows()
     */
    public function setValueOnAllRows($value, bool $overwrite = true) : DataColumnInterface
    {
        foreach ($this->getDataSheet()->getRows() as $row_number => $val) {
            if ($overwrite === true || $val === null || $val === '') {
                $this->setValue($row_number, $value);
            }
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getIgnoreFixedValues()
     */
    public function getIgnoreFixedValues() : bool
    {
        return $this->ignore_fixed_values;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setIgnoreFixedValues()
     */
    public function setIgnoreFixedValues(bool $value) : DataColumnInterface
    {
        $this->ignore_fixed_values = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::removeRows()
     */
    public function removeRows()
    {
        $this->getDataSheet()->removeRowsForColumn($this->getName());
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::aggregate()
     */
    public function aggregate($aggregatorOrString = null)
    {
        if ($aggregatorOrString === null) {
            // If no aggregator is specified, see if we can guess one
            switch (true) {
                // If the column has totals, use the aggregator of the first total
                case $this->hasTotals():
                    $aggregator = $this->getTotals()->getFirst()->getAggregator();
                    break;
                // If the column is bound to an attribute, use it's default aggregate function
                case $this->getExpressionObj()->isMetaAttribute():
                    $aggregator = new Aggregator($this->getWorkbench(), $this->getAttribute()->getDefaultAggregateFunction());
                default:
                    throw new DataSheetRuntimeError($this->getDataSheet(), 'Cannot aggregte values of column "' . $this->getName() . '": no aggregator specified!', '6T5UXLD');
            }
        } elseif ($aggregatorOrString instanceof AggregatorInterface) {
            $aggregator = $aggregatorOrString;
        } else {
            $aggregator = new Aggregator($this->getWorkbench(), $aggregatorOrString);
        }
        
        // If using a LIST-aggregator without a delimiter parameter, replace the aggregator with one
        // that uses the value list delimiter of this column's attribute.
        if (($aggregator->is(AggregatorFunctionsDataType::LIST_ALL) || $aggregator->is(AggregatorFunctionsDataType::LIST_DISTINCT)) && $aggregator->hasArguments() === false) {
            if ($attr = $this->getAttribute()) {
                $aggregator = new Aggregator($this->getWorkbench(), $aggregator->getFunction(), [$attr->getValueListDelimiter()]);
            }
        }
        
        try {
            return ArrayDataType::aggregateValues($this->getValues(false), $aggregator);
        } catch (\Throwable $e) {
            throw new DataSheetRuntimeError($this->getDataSheet(), 'Cannot aggregate values of column "' . $this->getName() . '" of a data sheet of "' . $this->getMetaObject()->getAliasWithNamespace() . '": unknown aggregator function "' . $aggregator . '"!', '6T5UXLD', $e);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getMetaObject()
     */
    public function getMetaObject()
    {
        return $this->getDataSheet()->getMetaObject();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getAggregator()
     */
    public function getAggregator() : ?AggregatorInterface
    {
        if ($this->isAttribute() === true && $aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $this->getAttributeAlias())) {
            return $aggr;
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::hasAggregator()
     */
    public function hasAggregator() : bool
    {
        return $this->getAggregator() !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::validateValues()
     */
    public function normalizeValues()
    {
        $parsedVals = [];
        foreach ($this->getValues(false) as $val) {
            $parsedVals[] = $this->getDataType()->parse($val);
        }
        $this->setValues($parsedVals);
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::hasEmptyValues()
     */
    public function hasEmptyValues() : bool
    {
        foreach ($this->getValues(false) as $val) {
            if ($val === null || $val === '') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::findEmptyRows()
     */
    public function findEmptyRows() : array
    {
        $rowNos = [];
        foreach ($this->getValues(false) as $rowNo => $val) {
            if ($val === null || $val === '') {
                $rowNos[] = $rowNo;
            }
        }
        return $rowNos;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getTitle()
     */
    public function getTitle(): ?string
    {
        if ($this->title === null) {
            switch (true) {
                case $this->isAttribute():
                    return $this->getAttribute()->getName();
                case $this->isCalculated():
                    return $this->getExpressionObj()->__toString();
            }
        }
        return $this->title;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setTitle()
     */
    public function setTitle(string $string): DataColumnInterface
    {
        $this->title = $string;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isReadable()
     */
    public function isReadable() : bool
    {
        switch (true) {
            case $this->isAttribute():
                return $this->getAttribute()->isReadable();
            case $this->isFormula():
                $formula = $this->getExpressionObj();
                foreach ($formula->getRequiredAttributes() as $attrAlias) {
                    if (! $this->getMetaObject()->hasAttribute($attrAlias) || $this->getMetaObject()->getAttribute($attrAlias)->isReadable()) {
                        return false;
                    }
                }
                return true;
            case $this->isStatic():
            case $this->isEmpty():
                return true;
        }
        
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setWritable()
     */
    public function setWritable(bool $trueOrFalse) : DataColumnInterface
    {
        $this->writable = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::isWritable()
     */
    public function isWritable() : bool
    {
        return $this->writable ?? $this->isAttribute() && $this->getAttribute()->isWritable();
    }
}