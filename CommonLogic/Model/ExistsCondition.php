<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Returns TRUE or FALSE depending on whether any data could be found for the provided data sheet
 *
 * @author Andrej Kabachnik
 *        
 */
class ExistsCondition implements ConditionalExpressionInterface
{
    use ImportUxonObjectTrait;

    private WorkbenchInterface $workbench;
    private UxonObject $uxon;
    private UxonObject|null $dataSheetUxon = null;
    private bool $invert = false;
    private DataSheetInterface|null $dataSheet = null;
    private DataSheetInterface|null $baseSheet = null;

    /**
     * @deprecated use ConditionFactory instead
     *
     * @param Workbench $workbench
     * @param UxonObject $uxon
     * @param bool $ifNotExists
     */
    public function __construct(Workbench $workbench, UxonObject $uxon, bool $ifNotExists = false)
    {
        $this->workbench = $workbench;
        $this->uxon = $uxon;
        $this->importUxonObject($uxon);
        $this->invert = $ifNotExists;
    }

    /**
     * @inheritDoc
     */
    public function evaluate(DataSheetInterface $dataSheet = null, int $rowIdx = null): bool
    {
        if ($dataSheet === null || $dataSheet->isEmpty()) {
            return false;
        }
        $json = $this->getFiltersTemplate();
        $phs = StringDataType::findPlaceholders($json);
        $phsCols = [];
        $needMoreData = false;
        foreach ($phs as $ph) {
            if (! $col = $dataSheet->getColumns()->getByExpression($ph)) {
               $needMoreData = true;
               $phsCols = [];
               break;
            }
            $phsCols[$ph] = $col;
        }
        if ($needMoreData === true) {
            $inputData = $dataSheet->extractSystemColumns();
            if ($dataSheet->hasUidColumn(true)) {
                $inputData->getFilters()->addConditionFromColumnValues($inputData->getUidColumn());
            }
            foreach ($phs as $ph) {
                $phsCols[$ph] = $inputData->getColumns()->addFromExpression($ph, null, true);
            }
            $inputData->dataRead();
        } else {
            $inputData = $dataSheet;
        }

        if ($this->baseSheet === null) {
            $base = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->dataSheetUxon);

            // Add column required for further logic
            if ($base->getMetaObject()->hasUidAttribute()) {
                $base->getColumns()->addFromUidAttribute();
            }
            if ($base->getMetaObject()->hasLabelAttribute()) {
                $base->getColumns()->addFromLabelAttribute();
            }

            // See if we can read data once only and then filter in-memory
            $staticFilters = $this->extractStaticFilters($base, $base->getFilters());
            $base->setFilters($staticFilters);

            // TODO there surely will be cases, when it is not a good idea to cache the
            // lookup-sheet. But how to detect them?
            $cacheable = true;

            // If we can cache values, read once and peform all filtering in-memory
            if ($cacheable === true) {
                $base->dataRead();
            }

            $this->baseSheet = $base;
        }

        if ($rowIdx !== null) {
            $inputRow = $inputData->getRow($rowIdx);
            $exists = $this->evaluateExistsForRow($inputRow, $this->baseSheet, $json, $phsCols);
        } else {
            $exists = true;
            foreach ($inputData->getRows() as $inputRow) {
                if (false === $this->evaluateExistsForRow($inputRow, $this->baseSheet, $json, $phsCols, ! $cacheable)) {
                    $exists = false;
                    break;
                }
            }
        }

        return $this->invert === false ? $exists : ! $exists;
    }

    /**
     * @param array $inputRow
     * @param DataSheetInterface $lookupSheet
     * @param string $filterTpl
     * @param array $placeholdersToColumns
     * @return bool
     */
    protected function evaluateExistsForRow(array $inputRow, DataSheetInterface $lookupSheet, string $filterTpl, array $placeholdersToColumns, bool $readData) : bool
    {
        $phVals = [];
        foreach ($placeholdersToColumns as $ph => $phCol) {
            $phVals[$ph] = $inputRow[$phCol->getName()];
        }
        $filtersJson = StringDataType::replacePlaceholders($filterTpl, $phVals);
        $filters = ConditionGroupFactory::createFromUxon($this->getWorkbench(), UxonObject::fromJson($filtersJson), $lookupSheet->getMetaObject());
        if ($readData === true) {
            $filteredSheet = $lookupSheet->copy();
            $filteredSheet->setFilters($filters);
            $filteredSheet->dataRead();
        } else {
            $filteredSheet = $lookupSheet->extract($filters);
        }
        return $filteredSheet->isEmpty() === false;
    }

    /**
     * @param DataSheetInterface $dataSheet
     * @param ConditionGroupInterface $conditionGroup
     * @return ConditionGroupInterface
     */
    protected function extractStaticFilters(DataSheetInterface $dataSheet, ConditionGroupInterface $conditionGroup) : ConditionGroupInterface
    {
        $newGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), $conditionGroup->getOperator(), $dataSheet->getMetaObject());
        foreach ($conditionGroup->getConditions() as $condition) {
            if (empty(StringDataType::findPlaceholders($condition->getValue()))) {
                $newGrp->addCondition($condition);
            }
            if (! $dataSheet->getColumns()->getByExpression($condition->getExpression())) {
                $dataSheet->getColumns()->addFromExpression($condition->getExpression());
            }
        }
        foreach ($conditionGroup->getNestedGroups() as $nestedGroup) {
            if ($nestedGroup->getOperator() === EXF_LOGICAL_AND) {
                $newGrp->addNestedGroup($this->extractStaticFilters($nestedGroup, $dataSheet->getMetaObject()));
            } else {
                foreach ($nestedGroup->getConditionsRecursive() as $condition) {
                    if (!$dataSheet->getColumns()->getByExpression($condition->getExpression())) {
                        $dataSheet->getColumns()->addFromExpression($condition->getExpression());
                    }
                }
            }
        }
        return $newGrp;
    }

    /**
     * Data sheet to check if data exists
     *
     * @uxon-property data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "filters": {"operator": "AND", "conditions": [{"expression": "", "comparator": "==", "value": ""}]}}
     *
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setDataSheet(UxonObject $uxon) : ExistsCondition
    {
        $this->dataSheetUxon = $uxon;
        $this->dataSheet = null;
        $this->baseSheet = null;
        return $this;
    }

    /**
     *
     * @return DataSheetInterface
     */
    protected function getDataSheet() : DataSheetInterface
    {
        if ($this->dataSheet === null) {
            $this->dataSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->dataSheetUxon);
        }
        return $this->dataSheet;
    }

    /**
     * {@inheritDoc}
     * @see ConditionalExpressionInterface::toConditionGroup()
     */
    public function toConditionGroup(): ConditionGroupInterface
    {
        $conditionGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND);
        $conditionGroup->addExistsCondition($this);
        return $conditionGroup;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $dataSheet = $this->getDataSheet();
        return 'Exists ' . $dataSheet->getMetaObject() . ' with ' . $dataSheet->getFilters()->__toString();
    }

    /**
     * {@inheritDoc}
     * @see ConditionalExpressionInterface::isEmpty()
     */
    public function isEmpty(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchInterface::getRequiredExpressions()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * {@inheritDoc}
     * @see iCanBeConvertedToUxon::getRequiredExpressions()
     */
    public function exportUxonObject()
    {
        return $this->uxon;
    }

    /**
     * {@inheritDoc}
     * @see iCanBeCopied::getRequiredExpressions()
     */
    public function copy(): \exface\Core\Interfaces\iCanBeCopied
    {
        return new self($this->workbench, $this->uxon->copy());
    }

    /**
     * @return string
     */
    protected function getFiltersTemplate() : string
    {
        $filtersUxon = $this->dataSheetUxon->getProperty('filters');
        if ($filtersUxon === null || $filtersUxon->isEmpty()) {
            throw new InvalidArgumentException('Cannot evaluate EXISTS condition: no filters defined');
        }

        return $filtersUxon->toJson();
    }

    /**
     * {@inheritDoc}
     * @see ConditionalExpressionInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(?MetaObjectInterface $object = null) : array
    {
        $phs = StringDataType::findPlaceholders($this->getFiltersTemplate());
        $phs = array_unique($phs);
        $exprs = [];
        foreach ($phs as $ph) {
            $exprs[] = ExpressionFactory::createFromString($this->getWorkbench(), $ph, $object);
        }
        return $exprs;
    }
}