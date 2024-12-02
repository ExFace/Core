<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
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
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     * @param MetaObjectInterface $onlyForObject
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
    public function check(DataSheetInterface $sheet) : DataSheetInterface
    {
        $badData = $this->findViolations($sheet);
        if (! $badData->isEmpty()) {
            $error = $this->getErrorText($badData);
            throw (new DataCheckFailedError($sheet, $error, null, null, $this, $badData))->setUseExceptionMessageAsTitle(true);
        }
        return $sheet;
    }

    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isViolatedIn(DataSheetInterface $data) : bool
    {
        return $this->findViolations($data)->isEmpty() === false;
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @return DataSheetInterface
     */
    public function findViolations(DataSheetInterface $data) : DataSheetInterface
    {
        if (! $this->isApplicable($data)) {
            throw new DataCheckNotApplicableError($data, 'Data check not applicable to given data!', null, null, $this);
        }
        
        if (null !== $subsheetRelStr = $this->getApplyToSubsheetsOfRelation()) {
            $badData = $this->findViolationsInSubsheets($data, $subsheetRelStr);
        } else {
            $badData = $this->findViolationsViaFilter($data, $this->getConditionGroup($data->getMetaObject()));
        }
        return $badData;
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @param ConditionGroupInterface $filter
     * @throws DataCheckNotApplicableError
     * @return DataSheetInterface
     */
    protected function findViolationsViaFilter(DataSheetInterface $data, ConditionGroupInterface $filter) : DataSheetInterface
    {
        try {
            return $data->extract($filter, true);
        } catch (DataSheetExtractError $e) {
            // Since the data extraction failed, we can assume that the check does not apply.
            return $data->copy()->removeRows();
        }
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @param string $subsheetRelationPath
     * @return DataSheetInterface
     */
    protected function findViolationsInSubsheets(DataSheetInterface $data, string $subsheetRelationPath) : DataSheetInterface
    {
        $subsheetCol = $data->getColumns()->getByExpression($subsheetRelationPath);
        if (! $subsheetCol) {
            return $data->copy()->removeRows();
        }
        $innerCheckUxon = $this->exportUxonObject()->withPropertiesRemoved(['apply_to_subsheets_of_relation']);
        $innerCheck = new DataCheck($this->getWorkbench(), $innerCheckUxon);
        $badRows = [];
        foreach ($subsheetCol->getValues(false) as $rowNr => $sheetArr) {
            if (! $sheetArr) {
                continue;
            }
            
            $nestedSheet = DataSheetFactory::createFromAnything($this->getWorkbench(), $sheetArr);
            if ($nestedSheet === null || $nestedSheet->isEmpty(true) === true) {
                continue;
            }
            
            try {
                $innerCheck->check($nestedSheet);
            } catch (DataCheckFailedError $e) {
                $badRows[] = $rowNr;
            }
        }
        
        $badData = $data->copy()->removeRows();
        foreach ($badRows as $rowNr) {
            $badData->addRow($data->getRow($rowNr), false, false, $rowNr);
        }
        return $badData;
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataCheckInterface::getErrorText()
     */
    public function getErrorText(DataSheetInterface $badData = null) : ?string
    {
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
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->getConditionGroupUxon(), $baseObject);
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
     * 
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