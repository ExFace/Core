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
use exface\Core\Factories\DataSheetFactory;

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
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->conditionGroupUxon = new UxonObject([
            'operator' => EXF_LOGICAL_AND
        ]);
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
            throw (new DataCheckFailedError($sheet, $error, null, null, $this))->setUseExceptionMessageAsTitle(true);
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
        $filter = ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->getConditionGroupUxon(), $data->getMetaObject());
        
        $missingExprs = [];
        foreach ($filter->getConditionsRecursive() as $cond) {
            if (! $data->getColumns()->getByExpression($cond->getExpression())) {
                $missingExprs[] = $cond->getExpression();
            }
        }
        if (! empty($missingExprs)) {
            if ($data->hasUidColumn(true)) {
                $missingSheet = DataSheetFactory::createFromObject($data->getMetaObject());
                $missingSheet->getColumns()->addFromUidAttribute();
                foreach ($missingExprs as $expr) {
                    $missingSheet->getColumns()->addFromExpression($expr);
                }
                $missingSheet->getFilters()->addConditionFromColumnValues($data->getUidColumn());
                $missingSheet->dataRead();
                $checkSheet = $data->copy();
                $checkSheet->joinLeft($missingSheet, $checkSheet->getUidColumnName(), $missingSheet->getUidColumnName());
            } else {
                throw new DataCheckNotApplicableError($data, 'Cannot validate data: information required for conditions is not available in the data sheet!');
            }
        } else {
            $checkSheet = $data;
        }
        
        return $checkSheet->extract($filter);
    }
    
    /**
     * 
     * @param DataSheetInterface $data
     * @return bool
     */
    public function isApplicable(DataSheetInterface $data) : bool
    {
        if (null !== $object = $this->getOnlyForObject()) {
            return $data->getMetaObject()->is($object);
        }
        return true;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return bool
     */
    public function isApplicableToObject(MetaObjectInterface $object) : bool
    {
        if (null !== $object = $this->getOnlyForObject()) {
            return $object->is($object);
        }
        return true;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getErrorText() : ?string
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
        if (null !== $alias = $this->getOnlyForObjectAlias()) {
            return $this->getWorkbench()->model()->getObject($alias);
        }
        return null;
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
}