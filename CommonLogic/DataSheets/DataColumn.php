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
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\BooleanDataType;

class DataColumn implements DataColumnInterface
{

    const COLUMN_NAME_VALIDATOR = '[^A-Za-z0-9_]';

    // Properties, _not_ to be dublicated on copy()
    private $data_sheet = null;

    // Properties, to be dublicated on copy()
    private $name = null;

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

    /** @var ExpressionInterface */
    private $formatter = null;

    function __construct($expression, $name = '', DataSheetInterface $data_sheet)
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
        if (is_null($this->expression) || $this->expression->isEmpty()) {
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
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setExpression()
     */
    public function setExpression($expression_or_string)
    {
        if (! ($expression_or_string instanceof ExpressionInterface)) {
            $exface = $this->getWorkbench();
            $expression = ExpressionFactory::createFromString($exface, $expression_or_string, $this->getMetaObject());
        } else {
            $expression = $expression_or_string;
        }
        
        $this->expression = $expression;
        
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
     *
     * {@inheritdoc}
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
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getFormatter()
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setFormatter()
     */
    public function setFormatter($expression)
    {
        if (! ($expression instanceof ExpressionInterface)) {
            $expression = $this->getWorkbench()->model()->parseExpression($expression);
        }
        $this->formatter = $expression;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getDataType()
     */
    public function getDataType()
    {
        if (is_null($this->data_type)) {
            if ($attribute_alias = $this->getAttributeAlias()) {
                try {
                    return $this->getMetaObject()->getAttribute($attribute_alias)->getDataType();
                } catch (MetaAttributeNotFoundError $e) {
                    // ignore expressions with invalid attribute aliases
                }
            }
            $this->data_type = DataTypeFactory::createBaseDataType($this->getWorkbench());
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
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getCellValue()
     */
    public function getCellValue($row_number)
    {
        return $this->getDataSheet()->getCellValue($this->getName(), $row_number);
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
        if ($overwrite || $this->isEmpty()) {
            $this->setValues($expression->evaluate($this->getDataSheet(), $this->getName()));
        } else {
            foreach ($this->getValues(false) as $row => $val) {
                if (! is_null($val) && $val !== '') {
                    $this->setValue($row, $expression->evaluate($this->getDataSheet(), $this->getName(), $row));
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
    public function copy()
    {
        $copy = clone $this;
        if ($this->getExpressionObj()) {
            $copy->setExpression($this->getExpressionObj()->copy());
        }
        if ($this->getFormula()) {
            $copy->setFormula($this->getFormula()->copy());
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
            $arr['totals'] = $this->getTotals()->exportUxonObject();
        }
        
        if ($this->isAttribute()) {
            // If it contains an attribute, it will be enough to export it's alias and every thing
            // else only if it differs from attribute data
            $arr['attribute_alias'] = $this->attribute_alias;
            
            if ($this->getAttribute()->getDataType() !== $this->getDataType()) {
                $arr['data_type'] = $this->getDataType()->getAliasWithNamespace();
            }
            
            if ($this->getAttribute()->getFormula() !== $this->getFormula()) {
                $arr['formula'] = $this->getFormula()->toString();
            }
        } else {
            // If it's not an attribute, export everything
            $arr['expression'] = $this->getExpressionObj()->toString();
            $arr['data_type'] = $this->getDataType()->getAliasWithNamespace();
        
            if ($this->formula) {
                $arr['formula'] = $this->getFormula()->toString();
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
        return is_null($this->formula) || $this->formula === '' ? false : true; 
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
                if (strcasecmp($cell_value, $row_val) === 0) {
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
            if ($another_column->getCellValue($row_nr) !== $val) {
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
            if ($another_column->getCellValue($other_uid_column->findRowByValue($uid)) !== $val) {
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
        return $this->formula;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setFormula()
     */
    public function setFormula($expression_or_string)
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
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setAttributeAlias()
     */
    public function setAttributeAlias($value)
    {
        $this->attribute_alias = $value;
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
        $name = preg_replace('/' . self::COLUMN_NAME_VALIDATOR . '/', '_', $string);
        return $name;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setValuesFromDefaults()
     */
    public function setValuesFromDefaults()
    {
        $attr = $this->getAttribute();
        // If there is already a column for the required attribute, check, if it has values for all rows
        foreach ($this->getValues(false) as $row_id => $val) {
            if (is_null($val) || $val === '') {
                if ($attr->getFixedValue()) {
                    $this->setValue($row_id, $attr->getFixedValue()->evaluate($this->getDataSheet(), $this->getName(), $row_id));
                } elseif ($attr->getDefaultValue()) {
                    $this->setValue($row_id, $attr->getDefaultValue()->evaluate($this->getDataSheet(), $this->getName(), $row_id));
                } else {
                    throw new DataSheetRuntimeError($this->getDataSheet(), 'Cannot fill column with default values ' . $this->getMetaObject()->getName() . ': attribute ' . $attr->getName() . ' not set in row ' . $row_id . '!', '6T5UX3Q');
                }
            }
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
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::getIgnoreFixedValues()
     */
    public function getIgnoreFixedValues()
    {
        return $this->ignore_fixed_values;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::setIgnoreFixedValues()
     */
    public function setIgnoreFixedValues($value)
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
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnInterface::aggregate()
     */
    public function aggregate(AggregatorInterface $aggregator)
    {
        $result = '';
        $values = $this->getValues(false);
        try {
            $result = static::aggregateValues($values, $aggregator);
        } catch (\Throwable $e) {
            throw new DataSheetRuntimeError($this->getDataSheet(), 'Cannot aggregate over column "' . $this->getName() . '" of a data sheet of "' . $this->getMetaObject()->getAliasWithNamespace() . '": unknown aggregator function "' . $aggregator . '"!', '6T5UXLD', $e);
        }
        return $result;
    }

    /**
     * Reduces the given array of values to a single value by applying the given aggregator.
     * If no aggregator is specified, returns the first value.
     *
     * @param array $row_array  
     * @param AggregatorInterface $aggregator          
     * @return array
     */
    public static function aggregateValues(array $row_array, AggregatorInterface $aggregator = null)
    {
        $func = $aggregator->getFunction();
        $args = $aggregator->getArguments();
        
        $output = '';
        switch ($func->getValue()) {
            case AggregatorFunctionsDataType::LIST_ALL:
                $output = implode(($args[0] ? $args[0] : ', '), $row_array);
                break;
            case AggregatorFunctionsDataType::LIST_DISTINCT:
                $output = implode(($args[0] ? $args[0] : ', '), array_unique($row_array));
                break;
            case AggregatorFunctionsDataType::MIN:
                $output = count($row_array) > 0 ? min($row_array) : 0;
                break;
            case AggregatorFunctionsDataType::MAX:
                $output = count($row_array) > 0 ? max($row_array) : 0;
                break;
            case AggregatorFunctionsDataType::COUNT:
                $output = count($row_array);
                break;
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                $output = count(array_unique($row_array));
                break;
            case AggregatorFunctionsDataType::SUM:
                $output = array_sum($row_array);
                break;
            case AggregatorFunctionsDataType::AVG:
                $output = count($row_array) > 0 ? array_sum($row_array) / count($row_array) : 0;
                break;
            default:
                throw new UnexpectedValueException('Unsupported aggregator function "' . $func . '"!');
        }
        return $output;
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
}