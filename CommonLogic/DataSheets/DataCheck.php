<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataCheckRuntimeError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\DataSheets\DataCheckNotApplicableError;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\DataSheets\DataSheetExtractError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;

/**
 * Standard implementation of DataCheckInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataCheck implements DataCheckInterface
{    
    use ImportUxonObjectTrait;
    
    private $errorText = null;
    
    private $conditionGroupUxon = null;
    
    private $workbench = null;
    
    private $onlyObjectAlias = null;
    
    private $onlyObject = null;
    
    private $applyToSubsheetsRelationPathString = null;
    
    private string $ifMissingColumn = self::MISSING_COLS_READ;

    /**
     *
     * @param WorkbenchInterface       $workbench
     * @param UxonObject               $uxon
     * @param MetaObjectInterface|null $onlyForObject
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon, MetaObjectInterface $onlyForObject = null)
    {
        $this->workbench = $workbench;
        $this->conditionGroupUxon = new UxonObject([
            'operator' => EXF_LOGICAL_AND
        ]);
        if ($onlyForObject !== null) {
            $this->onlyObject = $onlyForObject;
            $this->onlyObjectAlias = $onlyForObject->getAliasWithNamespace();
        }
        $this->importUxonObject($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataCheckInterface::check()
     */
    public function check(DataSheetInterface $sheet, ?LogBookInterface $logBook = null) : string
    {
        $badRowIdxs = $this->findViolations($sheet, $logBook);
        
        $errorText = $this->getErrorText($sheet);
        $errorCount = count($badRowIdxs);
        $explanation = 'Found ' . ($errorCount > 0 ? "**{$errorCount}**" : $errorCount) . ' match(es) for check `' . $this->__toString() . '`.';
        $logBook?->addLine($explanation);
        
        if (! empty($badRowIdxs)) {
            throw (new DataCheckFailedError($sheet, $errorText, null, null, $this, $badRowIdxs))->setUseExceptionMessageAsTitle(true);
        }
        return $explanation;
    }

    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isViolatedIn(DataSheetInterface $data, ?LogBookInterface $logBook = null) : bool
    {
        return empty($this->findViolations($data, $logBook)) === false;
    }
    
    /**
     * 
     * @see DataCheckInterface::findViolations()
     */
    public function findViolations(DataSheetInterface $data, ?LogBookInterface $logBook = null) : array
    {
        if (! $this->isApplicable($data)) {
            throw new DataCheckNotApplicableError($data, 'Data check not applicable to given data!', null, null, $this);
        }
        
        if (null !== $subsheetRelStr = $this->getApplyToSubsheetsOfRelation()) {
            $badIdxs = $this->findViolationsInSubsheets($data, $subsheetRelStr, $logBook);
        } else {
            $badIdxs = $this->findViolationsViaFilter($data, $this->getConditionGroup($data->getMetaObject()), $logBook);
        }
        return $badIdxs;
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @param ConditionGroupInterface $filter
     * @param LogBookInterface|null $logBook
     * @return int[]
     */
    protected function findViolationsViaFilter(DataSheetInterface $data, ConditionGroupInterface $filter, ?LogBookInterface $logBook = null) : array
    {
        $cols = $data->getColumns();
        switch ($this->getIfMissingColumns()) {
            // READ is the default behavior and works the same as though no behavior was defined.
            // Missing data will be read via `DataSheet::findRows($filter, true)`.
            case self::MISSING_COLS_READ:
                $readMissingData = true;
                break;
            // EMPTY will add any missing columns and fill them with their datatype equivalent of an empty value.
            case self::MISSING_COLS_EMPTY:
                $workbench = $this->getWorkbench();
                foreach ($this->findMissingColumns($data, $filter, false) as $missingCol) {
                    $col = $cols->addFromExpression($missingCol);
                    $expr = ExpressionFactory::createAsScalar($workbench, $col->getDataType()->format());
                    $col->setValuesByExpression($expr);
                }
                // At this point, all required columns are confirmed to be present, so we don't have to redo
                // the work in DataSheet::findRows().
                $readMissingData = false;
                break;
            // PASS will return an empty violations array, if any columns are missing. 
            case self::MISSING_COLS_PASS:
                if(!empty($this->findMissingColumns($data, $filter, true))) {
                    return [];
                }
                // At this point, all required columns are confirmed to be present, so we don't have to redo
                // the work in DataSheet::findRows().
                $readMissingData = false;
                break;
            default:
                throw new DataCheckRuntimeError($data,'Invalid value "' . $this->getIfMissingColumns() . '" for `if_missing_columns`!', null, null, $this);
        }
        
        try {
            return $data->findRows($filter, $readMissingData);
        } catch (DataSheetExtractError $e) {
            $logBook->addLine('**ERROR** filtering data to check via `' . $filter->__toString() . '`. Assuming check does not apply');
            $this->getWorkbench()->getLogger()->logException(new DataCheckRuntimeError($data, 'Cannot perform data check. ' . $e->getMessage(), null, $e, $this));
            return [];
        }
    }

    /**
     * Find any columns required by `$filter` that are not present in `$data`.
     * 
     * @param DataSheetInterface      $data
     * @param ConditionGroupInterface $filter
     * @param bool                    $returnOnFirst
     * If TRUE, this function will only return the first missing column it can find.
     * Note that the order in which columns are being checked is not reliable!
     * @return array
     */
    protected function findMissingColumns(
        DataSheetInterface $data, 
        ConditionGroupInterface $filter,
        bool $returnOnFirst
    ) : array
    {
        $inputCols = $data->getColumns();
        $result = [];
        
        foreach ($filter->getRequiredExpressions($data->getMetaObject()) as $expr) {
            foreach ($expr->getRequiredAttributes() as $attrAlias) {
                if (! $inputCols->getByExpression($attrAlias)) {
                    // Associative to prevent duplicate entries.
                    $result[$attrAlias] = $attrAlias;
                    if($returnOnFirst) {
                        return $result;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @param string $subsheetRelationPath
     * @param LogBookInterface|null $logBook
     * @return int[]
     */
    protected function findViolationsInSubsheets(DataSheetInterface $data, string $subsheetRelationPath, ?LogBookInterface $logBook = null) : array
    {
        $subsheetCol = $data->getColumns()->getByExpression($subsheetRelationPath);
        if (! $subsheetCol) {
            return $data->copy()->removeRows();
        }
        $innerCheckUxon = $this->exportUxonObject()->withPropertiesRemoved(['apply_to_subsheets_of_relation']);
        $innerCheck = new DataCheck($this->getWorkbench(), $innerCheckUxon);
        $badIdxs = [];
        foreach ($subsheetCol->getValues(false) as $rowNr => $sheetArr) {
            if (! $sheetArr) {
                continue;
            }
            
            $nestedSheet = DataSheetFactory::createFromAnything($this->getWorkbench(), $sheetArr);
            if ($nestedSheet === null || $nestedSheet->isEmpty(true) === true) {
                continue;
            }
            
            try {
                $innerCheck->check($nestedSheet, $logBook);
            } catch (DataCheckFailedError $e) {
                $badIdxs[] = $rowNr;
            }
        }
        
        return $badIdxs;
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isApplicable(DataSheetInterface $data) : bool
    {
        return $this->isApplicableToObject($data->getMetaObject());
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return bool
     */
    public function isApplicableToObject(MetaObjectInterface $object) : bool
    {
        if (null !== $onlyForObject = $this->getOnlyForObject()) {
            // A check is applicable if the input-object is the check-object is derived from it. This guarantees,
            // that the check only includes attribtues, that the input-object has.
            // For example, if we have a REPORT and an EXTENDED_REPORT based on it, a REPORT-check can be applied
            // to both, because an EXTENDED_REPORT is a REPORT too.
            if ($object->is($onlyForObject)) {
                return true;
            }

            // If it is the other way around, we can only use the check if it only requires inherited attributes!
            // In our example, an EXTENDED_REPORT-check can only be applied to a REPORT if it only requires
            // mutual attribtues
            if ($onlyForObject->is($object)) {
                foreach ($this->getConditionGroup($onlyForObject)->getRequiredExpressions() as $expr) {
                    foreach ($expr->getRequiredAttributes() as $attrAlias) {
                        // If the check requires at least one attribute, that is not present in the input-object,
                        // it is not applicable
                        if (! $object->hasAttribute($attrAlias)) {
                            return false;
                        }
                    }
                }
                // If the check only uses attributes inherited from the input-object - it is OK
                return true;
            }

            // If the objects are unrelated, we can still apply the check if it only requires static expressions
            foreach ($this->getConditionGroup($onlyForObject)->getRequiredExpressions() as $expr) {
                if (! $expr->isStatic()) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param DataSheetInterface $dataSheet
     * @return string|null
     */
    protected function getErrorText(DataSheetInterface $dataSheet) : ?string
    {
        // TODO allow placeholders from data in the error text
        return $this->errorText;
    }
    
    /**
     * The text to show if validation fails
     * 
     * @uxon-property error_text
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @param string $value
     * @return DataCheck
     */
    protected function setErrorText(string $value) : DataCheck
    {
        $this->errorText = $value;
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getConditionGroupUxon() : UxonObject
    {
        return $this->conditionGroupUxon;
    }
    
    /**
     * 
     * @param MetaObjectInterface $baseObject
     * @return ConditionGroupInterface
     */
    public function getConditionGroup(MetaObjectInterface $baseObject = null) : ConditionGroupInterface
    {
        if (null === $baseObject) {
            $baseObject = $this->getOnlyForObject();
        }
        try {
            return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->getConditionGroupUxon(), $baseObject);
        } catch (\Throwable $e) {
            throw new UnexpectedValueException('Cannot parse condition for data check "' . $this->errorText . '". ' . $e->getMessage(), null, $e);
        }
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function exportUxonObject()
    {
        $uxon = $this->getConditionGroupUxon();
        if ($this->errorText !== null) {
            $uxon->setProperty('error_text', $this->errorText);
        }        
        return $uxon;
    }

    /**
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass(): ?string
    {
        return null;
    }
    
    protected function getOnlyForObject() : ?MetaObjectInterface
    {
        if ($this->onlyObject === null) {
            if (null !== $alias = $this->getOnlyForObjectAlias()) {
                $this->onlyObject = MetaObjectFactory::createFromString($this->getWorkbench(), $alias);
            }
        }
        return $this->onlyObject;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getOnlyForObjectAlias() : ?string
    {
        return $this->onlyObjectAlias;
    }
    
    /**
     * Apply this check only if the data is based on a certain meta object.
     * 
     * This is especially useful for actions with multiple input mappers. Using
     * this property, you can selectively apply checks only to certain input
     * objects.
     * 
     * @uxon-property only_for_object
     * @uxon-type metamodel:object
     * @param string $value
     * @return DataCheck
     */
    protected function setOnlyForObject(string $value) : DataCheck
    {
        $this->onlyObjectAlias = $value;
        $this->onlyObject = null;
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * Conditions to check
     *
     * @uxon-property conditions
     * @uxon-type \exface\Core\CommonLogic\Model\Condition[]
     * @uxon-template [{"expression": "", "comparator": "", "value": ""}]
     *
     * @param UxonObject $uxon
     * @return DataCheck
     */
    protected function setConditions(UxonObject $uxon) : DataCheck
    {
        $this->conditionGroupUxon->setProperty('conditions', $uxon);
        return $this;
    }
    
    /**
     * Condition groups to check: e.g. ANDs inside an OR
     * 
     * @uxon-property condition_groups
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup[]
     * @uxon-template [{"operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return DataCheck
     */
    protected function setConditionGroups(UxonObject $uxon) : DataCheck
    {
        $this->conditionGroupUxon->setProperty('nested_groups', $uxon);
        return $this;
    }
    
    /**
     * Logical operator to connect conditions and nested groups
     * 
     * @uxon-property operator
     * @uxon-type [AND,OR,XOR]
     * @xuon-default AND
     * 
     * @param string $value
     * @return DataCheck
     */
    protected function setOperator(string $value) : DataCheck
    {
        $this->conditionGroupUxon->setProperty('operator', $value);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getApplyToSubsheetsOfRelation() : ?string
    {
        return $this->applyToSubsheetsRelationPathString;
    }
    
    /**
     * Check data in nested sheets within the given column instead of checking the main sheet
     * 
     * Note: if the main data sheet does not contain a column matching the provided reverse
     * relation, the check will not fail. No data is concidered not wrong.
     * 
     * @uxon-property apply_to_subsheets_of_relation
     * @uxon-type metamodel:relation
     * 
     * @param string $value
     * @return DataCheck
     */
    protected function setApplyToSubsheetsOfRelation(string $value) : DataCheck
    {
        $this->applyToSubsheetsRelationPathString = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Stringable::__toString()
     */
    public function __toString() : string
    {
        return $this->getConditionGroup()->__toString();
    }

    /**
     * @return string
     */
    public function getIfMissingColumns() : string
    {
        return $this->ifMissingColumn;
    }
    
    /**
     * Configure the behavior of this data check, in case one or more columns required by the check
     * are missing from the datasheet.
     * 
     * - **read**: Values from missing columns will be read from the database. (default)
     * - **empty**: Values from missing columns will be set to their datatype equivalent of an empty value (such as
     * NULL).
     * - **pass**: If not all columns required by this data check are present, the check will automatically pass (i.e.
     * will not be performed).
     * 
     * @uxon-property if_missing_columns
     * @uxon-type [read,empty,pass]
     * @uxon-tempalte {"if_missing_column":"read"}
     * 
     * @param string $value
     * @return $this
     */
    public function setIfMissingColumns(string $value) : DataCheck
    {
        if( $value !== self::MISSING_COLS_READ &&
            $value !== self::MISSING_COLS_EMPTY &&
            $value !== self::MISSING_COLS_PASS) {
            throw new InvalidArgumentException('Cannot set `if_missing_column`: "' . $value . '" is not a valid value!');
        }
        
        $this->ifMissingColumn = $value;
        return $this;
    }
}