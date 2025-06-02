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

    private WorkbenchInterface      $workbench;
    private UxonObject              $uxon;
    private UxonObject|null         $dataSheetUxon = null;
    private bool                    $invert = false;
    private DataSheetInterface|null $dataSheet = null;
    private DataSheetInterface|null $baseSheet = null;
    private bool                    $baseSheetCacheable = false;
    private ?string                 $baseSheetLabelColName = null;
    private array                   $decisionsHistory = [];

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
                $col = $base->getColumns()->addFromUidAttribute();
                $this->baseSheetLabelColName = $col->getName();
            }
            if ($base->getMetaObject()->hasLabelAttribute()) {
                $base->getColumns()->addFromLabelAttribute();
                $this->baseSheetLabelColName = $col->getName();
            }

            // See if we can read data once only and then filter in-memory
            $staticFilters = $this->extractStaticFilters($base, $base->getFilters());
            $base->setFilters($staticFilters);

            // TODO there surely will be cases, when it is not a good idea to cache the
            // lookup-sheet. But how to detect them?
            $this->baseSheetCacheable = true;

            // If we can cache values, read once and peform all filtering in-memory
            if ($this->baseSheetCacheable === true) {
                $base->dataRead();
            }

            $this->baseSheet = $base;
        }

        if ($rowIdx !== null) {
            $inputRow = $inputData->getRow($rowIdx);
            $existingData = $this->getExistingDataForRow($inputRow, $this->baseSheet, $json, $phsCols);
            $exists = ! $existingData->isEmpty();
        } else {
            $exists = true;
            foreach ($inputData->getRows() as $inputRow) {
                $existingData = $this->getExistingDataForRow($inputRow, $this->baseSheet, $json, $phsCols, ! $this->baseSheetCacheable);
                if ($existingData->isEmpty()) {
                    $exists = false;
                    break;
                }
            }
        }

        $this->decisionsHistory[$this->getDecisionHistoryKey($dataSheet, $rowIdx)] = $existingData;
        return $this->invert === false ? $exists : ! $exists;
    }

    /**
     * Returns an explanation about the evaluation of this condition for the given data - for debug purposes
     *
     * TODO add explain() to other conditional expressions?
     *
     * @param DataSheetInterface|null $dataSheet
     * @param int|null $rowIdx
     * @return string
     */
    public function explain(DataSheetInterface $dataSheet = null, int $rowIdx = null) : string
    {
        $historyKey = $this->getDecisionHistoryKey($dataSheet, $rowIdx);
        $existingData = $this->decisionsHistory[$historyKey] ?? null;
        if ($existingData === null) {
            return 'Condition not yet evaluated';
        }
        if ($existingData->isEmpty()) {
            return 'No data found for `' . $existingData->getFilters()->__toString() . '`';
        }
        if ($this->baseSheetLabelColName !== null && $col = $existingData->getColumns()->get($this->baseSheetLabelColName)) {
            $vals = $col->getValues();
            $vals = array_unique($vals);
            return 'Found ' . $existingData->getMetaObject()->getName() . ' `' . implode('`, `', $vals) . '` via `' . $existingData->getFilters()->__toString() . '`';
        }
        return 'Found ' . $existingData->countRows() . ' data rows for `' . $existingData->getFilters()->__toString() . '`';
    }

    /**
     *
     * @param array $inputRow
     * @param DataSheetInterface $lookupSheet
     * @param string $filterTpl
     * @param array $placeholdersToColumns
     * @return DataSheetInterface
     */
    protected function getExistingDataForRow(array $inputRow, DataSheetInterface $lookupSheet, string $filterTpl, array $placeholdersToColumns, bool $readData) : DataSheetInterface
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
            // Overwrite the filters after extracting just to improve explain() output
            $filteredSheet->setFilters($filters);
        }
        return $filteredSheet;
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

    protected function getDecisionHistoryKey(?DataSheetInterface $dataSheet = null, ?int $rowIdx = null) : string
    {
        return ($dataSheet === null ? '' : spl_object_id($dataSheet)) . ':' . $rowIdx ?? '';
    }
}