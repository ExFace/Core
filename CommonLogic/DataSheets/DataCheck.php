<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataCheckRuntimeError;
use exface\Core\Exceptions\UnexpectedValueException;
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
        try {
            return $data->findRows($filter, true);
        } catch (DataSheetExtractError $e) {
            $logBook->addLine('**ERROR** filtering data to check via `' . $filter->__toString() . '`. Assuming check does not apply');
            $this->getWorkbench()->getLogger()->logException(new DataCheckRuntimeError($data, 'Cannot perform data check. ' . $e->getMessage(), null, $e, $this));
            return [];
        }
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
            return $object->is($onlyForObject);
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
}