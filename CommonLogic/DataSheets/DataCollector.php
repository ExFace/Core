<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\DataSheets\DataCollectorRuntimeError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\DataSheets\DataCollectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Collects required data for rows of a given data sheet
 *
 * @see DataCollectorInterface
 */
class DataCollector implements DataCollectorInterface
{
    private WorkbenchInterface      $workbench;
    private MetaObjectInterface     $baseObject;
    private ?DataSheetInterface     $baseSheet = null;
    private ?DataSheetInterface     $resultSheet = null;
    private ?array                  $resultCols = null;
    private ?array                  $requiredExpressions = null;
    private array                   $addedExpressions = [];
    
    private bool                    $readMissingData = true;
    private bool                    $ignoreUnreadable = false;

    /**
     * @var MetaAttributeInterface[]
     */
    private array                   $addedAttributes = [];
    /**
     * @var string[]
     */
    private array                   $addedAttributeAliases = [];

    /**
     * @param MetaObjectInterface $object
     */
    public function __construct(MetaObjectInterface $object)
    {
        $this->baseObject = $object;
        $this->workbench = $object->getWorkbench();
    }

    /**
     * Returns a data collector instance, that includes all expressions to be read for the given condition group
     * 
     * The collector will include all non-constant expressions required to evaluate the condition group - that is
     * all expressions, that need to be read from a data source or evaluated somehow. Constants or empty expressions
     * will be ignored as they do not need to be read.
     * 
     * This is handy to quickly make sure, there is enough data to evaluate a condition group - e.g. for
     * `DataSheet::extract()` or just condition checks.
     * 
     * @param ConditionGroupInterface $conditionGroup
     * @param MetaObjectInterface $baseObject
     * @return DataCollectorInterface
     */
    public static function fromConditionGroup(ConditionGroupInterface $conditionGroup, MetaObjectInterface $baseObject): DataCollectorInterface
    {
        $collector = new self($baseObject);
        foreach($conditionGroup->getRequiredExpressions($baseObject) as $expr) {
            if ($expr->isConstant() || $expr->isEmpty()) {
                continue;
            }
            $collector->addExpression($expr);
        }
        return $collector;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::collectFrom()
     */
    public function collectFrom(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : DataCollectorInterface
    {
        if ($this->baseSheet !== null) {
            return $this->copy()->collectFrom($dataSheet, $logBook);
        }
        $this->reset($dataSheet);
        $missingExprs = $this->getMissingExpressions($dataSheet);
        switch (true) {
            case empty($missingExprs):
                $this->resultSheet = $dataSheet;
                break;
            case $dataSheet->hasUidColumn(true):
                $this->resultSheet = $dataSheet->extractSystemColumns();
                $this->readMissingDataWithUid($this->resultSheet, $logBook);
                break;
            default:
                $this->resultSheet = $dataSheet->copy();
                $this->readMissingDataWithoutUid($this->resultSheet, $logBook);
                break;
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::enrich()
     */
    public function enrich(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : DataCollectorInterface
    {
        if ($this->baseSheet !== null) {
            return $this->copy()->enrich($dataSheet, $logBook);
        }
        $this->reset($dataSheet);
        $missingExprs = $this->getMissingExpressions($dataSheet);
        switch (true) {
            case empty($missingExprs):
                $this->resultSheet = $dataSheet;
                break;
            case $dataSheet->hasUidColumn(true) && $dataSheet->isFresh():
                $this->resultSheet = $this->readMissingDataWithUid($dataSheet, $logBook);
                break;
            default:
                $this->resultSheet = $this->readMissingDataWithoutUid($dataSheet, $logBook);
                break;
        }
        return $this;
    }

    /**
     * @return DataSheetInterface
     */
    public function getRequiredData(): DataSheetInterface
    {
        if ($this->resultSheet === null) {
            throw new RuntimeException('Cannot call getRequiredData() on a data collector before collect() or enrich()');
        }
        return $this->resultSheet;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::getRequiredColumns()
     */
    public function getRequiredColumns() : array
    {
        if ($this->resultCols === null) {
            $ignoreMissing = $this->willIgnoreUnreadableColumns();
            $resultSheet = $this->getRequiredData();
            foreach ($this->getRequiredExpressions() as $expr) {
                if ($ignoreMissing === false && ! $col = $resultSheet->getColumns()->getByExpression($expr)) {
                    throw new DataCollectorRuntimeError($resultSheet, 'Missing "' . $expr->__toString() . '" in data of ' . $resultSheet->getMetaObject()->__toString());
                }
                $cols[$expr->__toString()] = $col;
            }
            $this->resultCols = $cols;
        }
        return $this->resultCols;
    }

    /**
     * @param DataSheetInterface $baseSheet
     * @return DataCollectorInterface
     */
    protected function reset(?DataSheetInterface $baseSheet = null) : DataCollectorInterface
    {
        $this->resultSheet = null;
        $this->resultCols = null;
        $this->baseSheet = $baseSheet;
        return $this;
    }

    /**
     * @return DataCollectorInterface
     */
    protected function copy() : DataCollectorInterface
    {
        $clone = clone $this;
        $clone->baseSheet = null;
        $clone->resultSheet = null;
        $clone->requiredExpressions = null;
        return $clone;
    }

    /**
     * @param DataSheetInterface $dataSheet
     * @return array|null
     */
    protected function getMissingExpressions(DataSheetInterface $dataSheet) : ?array
    {
        $missing = [];
        foreach ($this->getRequiredExpressions() as $expr) {
            if (! $dataSheet->getColumns()->getByExpression($expr)) {
                $missing[] = $expr;
            }
        }
        return $missing;
    }

    /**
     * @param DataSheetInterface $dataSheet
     * @param LogBookInterface|null $logbook
     * @return DataSheetInterface
     */
    protected function readMissingDataWithUid(DataSheetInterface $dataSheet, ?LogBookInterface $logbook = null) : DataSheetInterface
    {
        $additionSheet = null;
        // See if any required columns are missing in the original data sheet. If so, add empty
        // columns and also create a separate sheet for reading missing data.
        $addedCols = [];
        $addedExprs = [];
        $effectedFormulas = [];
        $refreshed = false;
        foreach ($this->getRequiredExpressions() as $expr) {
            // Skip the column if it already exists in the from-sheet
            if ($dataSheet->getColumns()->getByExpression($expr)) {
                continue;
            }
            // Otherwise we need a separate data sheet to read the required data.
            // Can't use the from-sheet itself as reading might overwrite other
            // values set by the user!
            // So create an extra copy of the sheet and remove any columns except
            // for the UID, that (as we know from above) hase meaningful values.
            if ($additionSheet === null) {
                $additionSheet = $dataSheet->copy();
                foreach ($additionSheet->getColumns() as $col) {
                    if ($col !== $additionSheet->getUidColumn()) {
                        $additionSheet->getColumns()->remove($col);
                    }
                }
            }
            $addedExprs[] = $expr->__toString();
            // If the new expression is a formula, remember it to update its calculation
            // after the mapping
            if ($expr->isFormula()) {
                $effectedFormulas[] = $expr;
            }
            // Now add readable stuff required for the expression to the addition sheet.
            // But only if it does not exist yet in the from sheet as this would overwrite
            // values set by the user!
            // DO NOT add the expression itself as it might be a formula, that requires
            // other columns from the from-sheet, that may not be available in the sheet
            // with additional values. Formula will be recalculated later.
            foreach ($expr->getRequiredAttributes() as $exprReqStr) {
                $exprReq = ExpressionFactory::createForObject($dataSheet->getMetaObject(), $exprReqStr);
                if ($dataSheet->getColumns()->getByExpression($exprReq)){
                    continue;
                }
                if ($exprReq->isMetaAttribute() && $exprReq->getAttribute()->isReadable()) {
                    $addedCols[] = $additionSheet->getColumns()->addFromExpression($exprReq);
                }
            }
            // Add the expression to the from-sheet. This will mark it as not fresh, which
            // is important below.
            // TODO it does not feel right to change the from-sheet in a mapper. Maybe the
            // columns shoud be removed at some later point of time?
            $dataSheet->getColumns()->addFromExpression($expr);
        }

        if (! empty($addedCols)) {
            $logbook?->addLine('Found ' . count($addedCols) . ' columns to read for the mapper: `' . implode('`, `', $addedExprs) . '`', 1);
        } else {
            $logbook?->addLine('All columns required for mapping found in from-data', 1);
        }

        // If columns were added to the original sheet, that need data to be loaded,
        // use the additional data sheet to load the data. This makes sure, the values
        // in the original sheet (= the input values) are not overwrittten by the read
        // operation.
        if (! $dataSheet->isFresh() && $this->willReadMissingFromData() === true){
            // Don't read anything if the object is not readable at all.
            if ($dataSheet->getMetaObject()->isReadable() === false) {
                $logbook?->addLine('**WARNING:** it seems, the from-data as not fresh and needs to be read, but the object ' . $dataSheet->getMetaObject()->__toString() . ' is explicitly marked as not readable. The data will not be refreshed!');
                return $dataSheet;
            }

            $additionSheet->getFilters()->addConditionFromColumnValues($dataSheet->getUidColumn());
            $additionSheet->dataRead();

            $logbook?->addLine('Read ' . $additionSheet->countRows() . ' rows filtered by ' . $dataSheet->getUidColumn()->getName(), 1);

            $uidCol = $dataSheet->getUidColumn();
            $uidColName = $uidCol->getName();
            foreach ($additionSheet->getColumns() as $addedCol) {
                foreach ($additionSheet->getRows() as $row) {
                    $uid = $row[$uidColName];
                    $rowNo = $uidCol->findRowByValue($uid);
                    if ($uid === null || $rowNo === false) {
                        throw new DataCollectorRuntimeError($dataSheet, 'Cannot load additional data in preparation for mapping! Trying to read ' . $addedCol->getName());
                    }
                    // Only set cell values if the column is an added column
                    // or the column does not exist yet in the original data sheet.
                    // It is important to check both because formula might lead to more columns being added.
                    if (in_array($addedCol, $addedCols, true) || $dataSheet->getColumns()->getByExpression($addedCol->getExpressionObj()) === FALSE) {
                        $dataSheet->setCellValue($addedCol->getName(), $rowNo, $row[$addedCol->getName()]);
                        $refreshed = true;
                    }
                }
            }
            // Recalculate all formulas, that rely on the newly added columns
            foreach ($effectedFormulas as $expr) {
                $dataSheet->getColumns()->getByExpression($expr)->setValuesByExpression($expr);
            }
        }

        // Make sure the data is marked as fresh now to prevent further unneeded refreshes
        if ($refreshed === true) {
            $dataSheet->setFresh(true);
        }

        return $dataSheet;
    }

    /**
     * @param DataSheetInterface $dataSheet
     * @param LogBookInterface|null $logbook
     * @return DataSheetInterface
     */
    protected function readMissingDataWithoutUid(DataSheetInterface $dataSheet, ?LogBookInterface $logbook = null): DataSheetInterface
    {
        $ignoreMissing = $this->willIgnoreUnreadableColumns();
        $refreshed = false;
        // The original from-data has no UIDs or was not fresh right from the beginning
        // See if any attributes required for the missing columns are related in the way described above
        // the if(). If so, load the data separately and put it into the from-sheet. This is mainly usefull
        // for formulas.
        foreach ($this->getRequiredExpressions() as $expr) {
            if ($dataSheet->getColumns()->getByExpression($expr)) {
                continue;
            }
            foreach ($expr->getRequiredAttributes() as $reqAlias) {
                // Only process requried attribute aliases, that are not present as columns yet and
                // have a non-empty relation path consisting only of forward relations
                if ($dataSheet->getColumns()->getByExpression($reqAlias)) {
                    continue;
                }
                $reqAggr = DataAggregation::getAggregatorFromAlias($this->workbench, $reqAlias);
                $reqAttr = $this->baseObject->getAttribute($reqAlias);
                $reqRelPath = $reqAttr->getRelationPath();
                if ($reqRelPath->isEmpty()) {
                    if ($ignoreMissing === false) {
                        throw new DataCollectorRuntimeError($dataSheet, 'Cannot read missing data "' . $expr->__toString() . '" for ' . $dataSheet->getMetaObject()->__toString());
                    }
                    continue;
                }
                // Find the last relation in the path, where there is a key column with values
                // in the current data.
                $reqRelKeyCol = null;
                $reqRelKeyColPath = null;
                $reqRelColPath = RelationPathFactory::createForObject($this->baseObject);
                $reqRelForwardOnly = true;
                foreach ($reqRelPath->getRelations() as $reqRel) {
                    if ($reqRel->isForwardRelation() || $reqAggr) {
                        $reqRelColPath = $reqRelColPath->appendRelation($reqRel);
                        if (($keyCol = $dataSheet->getColumns()->getByExpression($reqRelColPath->toString())) && $keyCol->isEmpty(true) === false) {
                            $reqRelKeyCol = $keyCol;
                            $reqRelKeyColPath = $reqRelColPath->copy();
                        }
                    } else {
                        // If there are backwards-relations in the path, jus skip the whole thing,
                        // maybe some other parts of the code will deal with it.
                        $reqRelForwardOnly = false;
                        break;
                    }
                }
                // If we have found a target, read data for it
                // IDEA collect all missing data based on the same object and read it at once instead of
                // reading data for each missing column separately.
                if ($reqRelForwardOnly === true && $reqRelKeyCol !== null) {
                    $targetCol = $dataSheet->getColumns()->addFromExpression($reqAlias);
                    $reqRelSheet = DataSheetFactory::createFromObject($reqRelKeyColPath->getEndObject());
                    $valCol = $reqRelSheet->getColumns()->addFromExpression(ExpressionFactory::createForObject($this->baseObject, $reqAlias)->rebase($reqRelKeyColPath->toString()));
                    $keyCol = $reqRelSheet->getColumns()->addFromAttribute($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute());
                    $reqRelSheet->getFilters()->addConditionFromValueArray($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute()->getAliasWithRelationPath(), $reqRelKeyCol->getValues(), ComparatorDataType::IN);
                    $reqRelSheet->dataRead();
                    foreach ($reqRelKeyCol->getValues() as $fromRowIdx => $key) {
                        $targetCol->setValue($fromRowIdx, $valCol->getValue($keyCol->findRowByValue($key)));
                        $refreshed = true;
                    }

                    $logbook?->addLine('Read ' . $reqRelSheet->countRows() . ' rows for columns related to mapped data (object "' . $reqRelSheet->getMetaObject()->getAliasWithNamespace() . '")', 1);
                } else {
                    if ($ignoreMissing === false) {
                        throw new DataCollectorRuntimeError($dataSheet, 'Cannot read missing data "' . $expr->__toString() . '" for ' . $dataSheet->getMetaObject()->__toString());
                    }
                }

            } // END foreach ($expr->getRequiredAttributes())
        } // END foreach($map->getRequiredExpressions($dataSheet))

        // Make sure the data is marked as fresh now to prevent further unneeded refreshes
        if ($refreshed === true) {
            $dataSheet->setFresh(true);
        }

        return $dataSheet;
    }

    /**
     * @inheritDoc
     */
    public function getRequiredExpressions(): array
    {
        if ($this->requiredExpressions === null) {
            foreach ($this->addedAttributeAliases as $attributeAlias) {
                $this->requiredExpressions[$attributeAlias] = ExpressionFactory::createFromString($this->workbench, $attributeAlias, $this->baseObject);
            }
            foreach ($this->addedAttributes as $attribute) {
                $this->requiredExpressions[$attribute->getAliasWithRelationPath()] = ExpressionFactory::createFromAttribute($attribute);
            }
            foreach ($this->addedExpressions as $expr) {
                if ($expr instanceof ExpressionInterface) {
                    $this->requiredExpressions[$expr->__toString()] = $expr;
                } else {
                    $this->requiredExpressions[$expr] = ExpressionFactory::createFromString($this->getWorkbench(), $expr, $this->baseObject);
                }
            }
        }
        return $this->requiredExpressions ?? [];
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addExpression()
     */
    public function addExpression($expressionOrString): DataCollectorInterface
    {
        $this->requiredExpressions = null;
        if (! in_array($expressionOrString, $this->addedExpressions,true)) {
            $this->addedExpressions[] = $expressionOrString;
        }
        return $this;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addExpressions()
     */
    public function addExpressions(array $expressionsOrStrings) : DataCollectorInterface
    {
        $this->reset();
        $this->addedExpressions = array_merge($this->addedExpressions, $expressionsOrStrings);
        $this->addedExpressions = array_unique($this->addedExpressions);
        return $this;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addAttribute()
     */
    public function addAttribute(MetaAttributeInterface $attribute): DataCollectorInterface
    {
        $this->reset();
        if (! in_array($attribute, $this->addedAttributes, true)) {
            $this->addedAttributes[] = $attribute;
        }
        return $this;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addAttributeAlias()
     */
    public function addAttributeAlias(string $alias): DataCollectorInterface
    {
        $this->reset();
        if (! in_array($alias, $this->addedAttributeAliases, true)) {
            $this->addedAttributeAliases[] = $alias;
        }
        return $this;
    }

    protected function willReadMissingFromData() : bool
    {
        return $this->readMissingData;
    }

    public function setReadMissingData(bool $trueOrFalse) : DataCollectorInterface
    {
        $this->readMissingData = $trueOrFalse;
        return $this;
    }

    protected function willIgnoreUnreadableColumns() : bool
    {
        return $this->ignoreUnreadable;
    }

    public function setIgnoreUnreadableColumns(bool $trueOrFalse) : DataCollectorInterface
    {
        $this->ignoreUnreadable = $trueOrFalse;
        return $this;
    }

    /**
     * @inheritDoc
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}