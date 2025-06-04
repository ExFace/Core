<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\DataSheets\DataCollectorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataMapperRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\DataSheets\DataCollectorInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
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
    private MetaObjectInterface     $baseObject;
    private WorkbenchInterface      $workbench;
    private ?array                  $requiredExpressions = null;
    private array                   $addedExrepssions = [];
    /**
     * @var MetaAttributeInterface[]
     */
    private array                   $addedAttributes = [];
    /**
     * @var string[]
     */
    private array                   $addedAttributeAliases = [];

    public function __construct(MetaObjectInterface $object)
    {
        $this->baseObject = $object;
        $this->workbench = $object->getWorkbench();
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::collect()
     */
    public function collect(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : DataSheetInterface
    {
        $cols = $this->collectColumns($dataSheet);
        $firstCol = reset($cols);
        return $firstCol->getDataSheet();
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::collectColumns()
     */
    public function collectColumns(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null) : array
    {
        $cols = $this->getRequiredColumns($dataSheet);
        if ($cols === null) {
            $cols = $this->readColumnsFor($dataSheet, $logBook);
        }
        return $cols;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::getRequiredColumns()
     */
    public function getRequiredColumns(DataSheetInterface $dataSheet) : ?array
    {
        foreach ($this->getRequiredExpressions() as $expr) {
            if (! $col = $dataSheet->getColumns()->getByExpression($expr)) {
                return null;
            }
            $cols[$expr->__toString()] = $col;
        }
        return $cols;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::readFor()
     */
    public function readFor(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null): DataSheetInterface
    {
        $resultSheet = $dataSheet->extractSystemColumns();
        $this->enrich($resultSheet);
        return $resultSheet;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::readColumnsFor()
     */
    public function readColumnsFor(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null): array
    {
        $cols = [];
        $resultSheet = $dataSheet->extractSystemColumns();
        $resultSheet = $this->enrich($resultSheet);
        foreach ($this->getRequiredExpressions() as $expr) {
            $cols[$expr->__toString()] = $resultSheet->getColumns()->getByExpression($expr);
        }
        return $cols;
    }

    /**
     * {@inheritDoc}
     * @see DataCollectorInterface::enrich()
     */
    public function enrich(DataSheetInterface $dataSheet, ?LogBookInterface $logBook = null): DataSheetInterface
    {
        if ($dataSheet->hasUidColumn(true) && $dataSheet->isFresh()) {
            $dataSheet = $this->readMissingDataWithUid($dataSheet, $logBook);
        } else {
            $dataSheet = $this->readMissingDataWithoutUid($dataSheet, $logBook);
        }
        return $dataSheet;
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
            if ($dataSheet->getColumns()->getByExpression($expr)){
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
     * @param datasheetinter $dataSheet
     * @param LogBookInterface|null $logbook
     * @return DataSheetInterface
     */
    protected function readMissingDataWithoutUid(datasheetinter $dataSheet, ?LogBookInterface $logbook = null): DataSheetInterface
    {
        $refreshed = false;
        // The original from-data has no UIDs or was not fresh right from the beginning
        // See if any attributes required for the missing columns are related in the way described above
        // the if(). If so, load the data separately and put it into the from-sheet. This is mainly usefull
        // for formulas.
        $fromObj = $this->getFromMetaObject();
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
                $reqAttr = $fromObj->getAttribute($reqAlias);
                $reqRelPath = $reqAttr->getRelationPath();
                if ($reqRelPath->isEmpty()) {
                    continue;
                }
                // Find the last relation in the path, where there is a key column with values
                // in the current data.
                $reqRelKeyCol = null;
                $reqRelKeyColPath = null;
                $reqRelColPath = RelationPathFactory::createForObject($fromObj);
                $reqRelForwardOnly = true;
                foreach ($reqRelPath->getRelations() as $reqRel) {
                    if ($reqRel->isForwardRelation()) {
                        $reqRelColPath = $reqRelColPath->appendRelation($reqRel);
                        if (($keyCol = $dataSheet->getColumns()->getByExpression($reqRelColPath->toString())) && $keyCol->isEmpty(true) === false) {
                            $reqRelKeyCol = $keyCol;
                            $reqRelKeyColPath = $reqRelColPath;
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
                    $valCol = $reqRelSheet->getColumns()->addFromExpression(ExpressionFactory::createForObject($fromObj, $reqAlias)->rebase($reqRelKeyColPath->toString()));
                    $keyCol = $reqRelSheet->getColumns()->addFromAttribute($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute());
                    $reqRelSheet->getFilters()->addConditionFromValueArray($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute()->getAliasWithRelationPath(), $reqRelKeyCol->getValues(), ComparatorDataType::IN);
                    $reqRelSheet->dataRead();
                    foreach ($reqRelKeyCol->getValues() as $fromRowIdx => $key) {
                        $targetCol->setValue($fromRowIdx, $valCol->getValue($keyCol->findRowByValue($key)));
                        $refreshed = true;
                    }

                    $logbook?->addLine('Read ' . $reqRelSheet->countRows() . ' rows for columns related to mapped data (object "' . $reqRelSheet->getMetaObject()->getAliasWithNamespace() . '")', 1);
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
            foreach ($this->addedExrepssions as $expr) {
                if ($expr instanceof ExpressionInterface) {
                    $this->requiredExpressions[$expr->__toString()] = $expr;
                } else {
                    $this->requiredExpressions[$expr] = ExpressionFactory::createFromString($this->getWorkbench(), $expr, $this->baseObject);
                }
            }
        }
        return $this->requiredExpressions;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addExpression()
     */
    public function addExpression($expressionOrString): DataCollectorInterface
    {
        $this->requiredExpressions = null;
        $this->addedExrepssions[] = $expressionOrString;
        return $this;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addExpressions()
     */
    public function addExpressions(array $expressionsOrStrings) : DataCollectorInterface
    {
        $this->requiredExpressions = null;
        $this->addedExrepssions = array_merge($this->addedExrepssions, $expressionsOrStrings);
        return $this;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addAttribute()
     */
    public function addAttribute(MetaAttributeInterface $attribute): DataCollectorInterface
    {
        $this->requiredExpressions = null;
        $this->addedAttributes[] = $attribute;
        return $this;
    }

    /**
     * @inheritDoc
     * @see DataCollectorInterface::addAttributeAlias()
     */
    public function addAttributeAlias(string $alias): DataCollectorInterface
    {
        $this->requiredExpressions = null;
        $this->addedAttributeAliases[] = $alias;
        return $this;
    }

    protected function willReadMissingFromData() : bool
    {
        return true;
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