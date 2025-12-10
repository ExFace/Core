<?php

namespace exface\Core\CommonLogic\DataSheets\Matcher;

use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\CommonLogic\Security\Authorization\DataAuthorizationPoint;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\DataSheets\DataMatcherError;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataMatcherInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\TwoSheetMatcherInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class DuplicatesMatcher implements DataMatcherInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;

    const LOCATED_IN_EVENT_DATA = 'event_data';
    const LOCATED_IN_DATA_SOURCE = 'data_source';
    
    private DataSheetInterface $dataSheet;
    private ?string $name = null;
    private DataLogBookInterface $logbook;
    private bool $treatUidMatchesAsDuplicates = true;
    
    private ?DataMatcherInterface $innerMatcher = null;
    
    private array $compareAttributeAliases = [];
    private UxonObject|ConditionGroupInterface|null $compareWithConditions = null;

    public function __construct(DataSheetInterface $dataSheet, string $name = null, ?DataLogBookInterface $logbook = null)
    {
        $this->dataSheet = $dataSheet;
        $this->name = $name;
        $this->logbook = $logbook ?? new DataLogBook($name);
    }
    
    protected function getInnerMatcher() : MultiMatcher
    {
        if ($this->innerMatcher === null) {
            $this->innerMatcher = $this->initMatcher();
        }
        return $this->innerMatcher;
    }

    protected function initMatcher() : DataMatcherInterface
    {
        $eventSheet = $this->dataSheet;
        $obj = $eventSheet->getMetaObject();
        $logbook = $this->logbook;
        $treatUidMatchesAsDuplicates = $this->treatUidMatchesAsDuplicates;
            
        $eventDataCols = $eventSheet->getColumns();
        $logbook->addSection('Searching for potential duplicates');

        $logbook->addDataSheet('Event data', $eventSheet);

        $compareCols = [];
        $missingCols = [];
        $missingAttrs = [];
        $logbook->addLine('Will compare these attributes: `' . implode('`, `', $this->getCompareAttributeAliases()) . '`');
        foreach ($this->getCompareAttributeAliases() as $attrAlias) {
            $attr = $obj->getAttribute($attrAlias);
            if ($col = $eventDataCols->getByAttribute($attr)) {
                $compareCols[] = $col;
            } else {
                $missingAttrs[] = $attr;
            }
        }

        $logbook->addIndent(+1);
        if (empty($missingAttrs) === false) {
            $logbook->addLine('Missing attributes in original data: `' . implode('`, `', $missingAttrs) . '`');
            if ($eventSheet->hasUidColumn(true) === false) {
                $logbook->addLine('Cannot read missing attributes because data has no UIDs!');
                throw new DataMatcherError($this, 'Cannot check for duplicates of ' . $obj->__toString() . '): not enough data!', '7PNKJ50', null, $logbook);
            }

            ## TODO #DataCollector to be used here
            $eventRows = $eventSheet->getRows();
            $missingAttrSheet = DataSheetFactory::createFromObject($obj);
            $missingAttrSheet->getFilters()->addConditionFromColumnValues($eventSheet->getUidColumn());
            foreach ($missingAttrs as $attr) {
                $logbook->addLine($attr->getAliasWithRelationPath(), 1);
                $missingCols[] = $missingAttrSheet->getColumns()->addFromAttribute($attr);
            }
            $missingAttrSheet = $this->readBypassingDataAuthorization($missingAttrSheet);
            $logbook->addLine('Read ' . $missingAttrSheet->countRows() . ' rows to get values of missing attributes', 1);

            $uidColName = $eventSheet->getUidColumnName();
            foreach ($eventRows as $rowNo => $row) {
                foreach ($missingCols as $missingCol) {
                    $eventRows[$rowNo][$missingCol->getName()] = $missingCol->getValueByUid($row[$uidColName]);
                }
            }

            $mainSheet = $eventSheet->copy()->removeRows()->addRows($eventRows);
            $compareCols = array_merge($compareCols, $missingCols);
        } else {
            $logbook->addLine('All required columns found in original data');
            $mainSheet = $eventSheet;
        }
        $logbook->addIndent(-1);

        $matcher = new MultiMatcher($mainSheet);

        // Extract rows from event data, that are relevant for duplicate search
        if ($this->hasCustomConditions()) {
            $customConditionsFilters = ConditionGroupFactory::createForDataSheet($mainSheet, $this->getCompareWithConditions()->getOperator());
            foreach ($this->getCompareWithConditions()->getConditions() as $cond) {
                if ($mainSheet->getColumns()->getByExpression($cond->getExpression())) {
                    $customConditionsFilters->addCondition($cond);
                } else {
                    $logbook->addLine('Ignoring condition: ´'  . $customConditionsFilters->__toString() . '´ in `compare_with_conditions` because required column is NOT part of event data sheet!');
                }
            }
            $logbook->addLine('Removing non-relevant data via `compare_with_conditions`: ' . $customConditionsFilters->__toString());
            $mainSheet = $mainSheet->extract($customConditionsFilters);
        } else {
            $logbook->addLine('Will search for duplicates for all rows, no filtering required');
        }

        $logbook->addDataSheet('Data to compare', $mainSheet);

        // See if there are duplicates within the current set of data
        $logbook->addIndent(+1);
        switch ($mainSheet->countRows()) {
            case 0:
                $logbook->addLine('Data to compare is empty - no need to search for duplicates');
                return $matcher;
            case 1:
                $logbook->addLine('1 row requires duplicates check - will search for duplicates in data source only');
                break;
            default:
                $logbook->addLine($mainSheet->countRows() . ' rows require duplicates check - will search for duplicates among these rows and in data source');
                $selfMatcher = new DataRowMatcher($mainSheet, $mainSheet, $compareCols, self::LOCATED_IN_EVENT_DATA);
                //$selfMatcher->setIgnoreUidMatches(true);
                $matcher->addMatcher($selfMatcher);
                break;
        }

        // Create a data sheet to search for possible duplicates
        $checkSheet = DataSheetFactory::createFromObject($eventSheet->getMetaObject());
        // Add system attributes in case we are going to update
        $checkSheet->getColumns()->addFromSystemAttributes();
        // Only include the compare-columns to speed up reading
        foreach ($compareCols as $col) {
            $checkSheet->getColumns()->addFromExpression($col->getExpressionObj());
        }

        // Add custom filters if defined
        if (null !== $customFilters = $this->getCompareWithConditions()) {
            $checkSheet->getFilters()->addNestedGroup($customFilters);
        }

        // To get possible duplicates transform every row in event data sheet into a filter for 
        // the check sheet
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $checkSheet->getMetaObject());
        foreach ($mainSheet->getRows() as $rowNo => $row) {
            $rowFilterGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $checkSheet->getMetaObject());
            foreach (array_merge($compareCols, $missingCols) as $col) {
                if (! array_key_exists($col->getName(), $row)) {
                    throw new DataMatcherError($this, 'Cannot check for duplicates for ' . $obj->__toString() . ': no input data found for attribute "' . $col->getAttributeAlias() . '"!', null, null, $logbook);
                }
                $value = $row[$col->getName()];

                if (($value === null || $value === '') && $col->getAttribute()->isRequired()) {
                    // Throw a DataSheetMissingRequiredValueError here because it has a cool message
                    // generator based on column/rows, which is very user friendly. The actual behavior
                    // exception will still be visible in the logs.
                    throw new DataSheetMissingRequiredValueError(
                        $eventSheet, // $dataSheet
                        null, // $message - empty to make exception autogenerate one
                        null, // $alias
                        (new DataMatcherError($this, 'Cannot check for duplicates for ' . $obj->__toString() . ': missing required value for attribute "' . $col->getAttributeAlias() . ' in row "' . $rowNo . '"!', null, null, $logbook)), // $previous
                        $col, // $column
                        $col->findEmptyRows() // $rowNumbers
                    );
                }
                $rowFilterGrp->addConditionFromString($col->getAttributeAlias(), ($value === '' || $value === null ? EXF_LOGICAL_NULL : $value), ComparatorDataType::EQUALS);
            }
            $orFilterGroup->addNestedGroup($rowFilterGrp);
        }
        $checkSheet->getFilters()->addNestedGroup($orFilterGroup);

        // Read the data with the applied filters
        $checkSheet = $this->readBypassingDataAuthorization($checkSheet);

        $logbook->addDataSheet('Data in data source', $checkSheet);

        if ($checkSheet->isEmpty()) {
            $logbook->addLine('No potential duplicates found in data source');
            return $matcher;
        } else {
            $logbook->addLine($checkSheet->countRows() . ' potential duplicate(s) found in data source according to the computed filters');
        }
        $logbook->addIndent(-1);

        $dataSourceMatcher = new DataRowMatcher($mainSheet, $checkSheet, $compareCols, self::LOCATED_IN_DATA_SOURCE);
        if ($treatUidMatchesAsDuplicates === false) {
            $dataSourceMatcher->setIgnoreUidMatches(true);
        }
        $matcher->addMatcher($dataSourceMatcher);

        return $matcher;
    }

    /**
     * Returns a match collection containing information about rows of the main sheet to be updated
     * 
     * The match collection contains the main sheet (the one passed to the DuplicatesMatcher) and an extracted
     * update-sheet ready to be updated (already including filled UID and system columns). Each match in the
     * collection connects a row of the main sheet with the corresponding row of the extracted update-sheet.
     * 
     * @param DataLogBookInterface $logbook
     * @return TwoSheetMatcherInterface
     */
    public function getMatchesToUpdate(DataLogBookInterface $logbook) : TwoSheetMatcherInterface
    {
        $mainSheet = $this->dataSheet;
        if (! $mainSheet->getMetaObject()->hasUidAttribute())  {
            throw new DataMatcherError($this, 'Cannot update duplicates of ' . $mainSheet->getMetaObject()->__toString() . ': object has no UID column!', null, null, $logbook);
        }
        
        $matcher = $this->getInnerMatcher();

        //copy the dataSheet and empty it
        $updateSheet = $mainSheet->copy();
        $updateSheet->removeRows();
        if (! $updateSheet->hasUidColumn()) {
            $uidCol = $updateSheet->getColumns()->addFromUidAttribute();
            $uidColName = $uidCol->getName();
        } else {
            $uidCol = $updateSheet->getUidColumn();
            $uidColName = $uidCol->getName();
        }
        $duplRowNos = $matcher->getMatchedRowIndexes();
        $logbook->addLine('Found duplicates for ' . count($duplRowNos) . ' rows: row number(s) ' . implode(', ', $duplRowNos));
        $logbook->addIndent(+1);
        $rowsHandled = [];
        $updateSheetRowIdxToEventRowIdx = [];
        foreach ($duplRowNos as $duplRowNo) {
            // Don't bother about rows, that need to be removed anyway
            if (in_array($duplRowNo, $rowsHandled)) {
                continue;
            }
            $row = $mainSheet->getRow($duplRowNo);

            // First check duplicates in the data source. There should be at most one and it must have a UID in order
            // to be updated.
            $matches = $matcher->getMatchesForRow($duplRowNo, self::LOCATED_IN_DATA_SOURCE);
            if (! empty($matches)) {
                $match = $matches[0];
                if (count($matches) > 1) {
                    throw new DataMatcherError($this, 'Cannot update duplicates of ' . $mainSheet->getMetaObject()->__toString() . ': multiple duplicates found in data source for row ' . $match->getMatchedPointer()->getRowNumber() + 1 . '!', null, null, $logbook, $match);
                }
                if($match->hasUid()) {
                    // If the event row does not have a UID value, it was intended to be created.
                    // But since there is a duplicate, it is now an update. In this case, we need
                    // to inherit system attributes from the duplicate! The UID is required to
                    // perform the update, but other things like the timestamp from the TimestampingBehavior
                    // should also be overwritten by values from the duplicate to be updated.
                    // NOTE: treat NULL and '' as empty here, not just NULL alone!
                    if ('' === ($row[$uidColName] ?? '')) {
                        $matchedRow = $match->getMatchedRow();
                        foreach ($mainSheet->getMetaObject()->getAttributes()->getSystem() as $systemAttr) {
                            $row[$systemAttr->getAlias()] = $matchedRow[$systemAttr->getAlias()];
                        }
                    }
                    $updateSheet->addRow($row, false, true);
                    $updateSheetRowIdxToEventRowIdx[] = $duplRowNo;
                    $rowsHandled[] = $duplRowNo;
                } else {
                    throw new DataMatcherError($this, 'Cannot update duplicates of ' . $mainSheet->getMetaObject()->__toString() . ': a duplicate for row ' . $match->getMatchedPointer()->getRowNumber() + 1 . ' was found, but it has no UID, so it cannot be updated!', null, null, $logbook, $match);
                }
            }

            // For duplicates found within the event data, just keep the first one. So remove all other
            // (duplicate) rows in the sheet.
            $matches = $matcher->getMatchesForRow($duplRowNo, self::LOCATED_IN_EVENT_DATA);
            foreach ($matches as $match) {
                $rowsHandled[] = $match->getMatchedRowIndex();
            }
        }

        $matchCollection = new DataRowMatchCollection($mainSheet, $updateSheet);
        if (! $updateSheet->isEmpty()) {
            // Do the extracting
            $logbook->addLine('Found ' . $updateSheet->countRows() . ' update-rows in original data');
            foreach ($updateSheetRowIdxToEventRowIdx as $iUpdate => $iMain) {
                $matchCollection->addMatchingRows($iMain, $iUpdate);
            }
        } else {
            $logbook->addLine('No update-rows in original data');
        }

        $logbook->addIndent(-1);
        return $matchCollection;
    }

    /**
     * @return int[]
     */
    public function getRowIndexesToUpdate(): array
    {
        return $this->getMatchedRowIndexes();
    }

    /**
     * @return int[]
     */
    public function getRowIndexesToCreate(): array
    {
        $allIndexes = array_keys($this->getMainDataSheet()->getRows());
        return array_diff($allIndexes, $this->getRowIndexesToUpdate());
    }

    /**
     * The attributes determining if a dataset is a duplicate.
     *
     * @uxon-property compare_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $arrayOrUxon
     * @return DuplicatesMatcher
     */
    public function setCompareAttributes($arrayOrUxon) : DuplicatesMatcher
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->compareAttributeAliases = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->compareAttributeAliases = $arrayOrUxon;
        }
        return $this;
    }

    /**
     *
     * @throws DataMatcherError
     * @return string[]
     */
    protected function getCompareAttributeAliases() : array
    {
        if (empty($this->compareAttributeAliases)) {
            throw new DataMatcherError($this, "No attributes were set in '{$this->getAlias()}' of the object '{$this->getMainDataSheet()->getMetaObject()->getAlias()}' to determine if a dataset is a duplicate or not! Set atleast one attribute via the 'compare_attributes' uxon property!");
        }
        return $this->compareAttributeAliases;
    }


    protected function getCompareWithConditions() : ?ConditionGroupInterface
    {
        if ($this->compareWithConditions instanceof UxonObject) {
            $this->compareWithConditions = ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->compareWithConditions, $this->getMainDataSheet()->getMetaObject());
        }
        return $this->compareWithConditions;
    }

    /**
     * Custom filters to use to look for potential duplicates
     *
     * @uxon-property compare_with_conditions
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}
     *
     * @param UxonObject $value
     * @return DuplicatesMatcher
     */
    protected function setCompareWithConditions(UxonObject $value) : DuplicatesMatcher
    {
        $this->compareWithConditions = $value;
        return $this;
    }

    /**
     *
     * @return bool
     */
    protected function hasCustomConditions() : bool
    {
        return $this->compareWithConditions !== null;
    }
    
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->dataSheet->getWorkbench();
    }

    /**
     * Reads the given data sheet without applying data authorization policies
     *
     * This is important for the DuplicatesMatcher because duplicates must be prevented even if the
     * current user is not allowed to see all the data! The behavior must throw an error even if the
     * user would not see the potential duplicate if reading the data regularly.
     *
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    protected function readBypassingDataAuthorization(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        if ($this->getWorkbench()->isInstalled() === false) {
            return $dataSheet;
        }
        try {
            $dataAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(DataAuthorizationPoint::class);
        } catch (throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            return $dataSheet;
        }
        $wasDisabled = $dataAP->isDisabled();
        $dataAP->setDisabled(true);
        try {
            $dataSheet->dataRead();
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $dataAP->setDisabled($wasDisabled);
        }
        return $dataSheet;
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMainDataSheet()
     */
    public function getMainDataSheet(): DataSheetInterface
    {
        return $this->getInnerMatcher()->getMainDataSheet();
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getName()
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    
    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::hasMatches()
     */
    public function hasMatches(): bool
    {
        return $this->getInnerMatcher()->hasMatches();
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMatches()
     */
    public function getMatches(string $matcherName = null): array
    {
        return $this->getInnerMatcher()->getMatches($matcherName);
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMatchesForRow()
     */
    public function getMatchesForRow(int $mainSheetRowIdx, string $matcherName = null): array
    {
        return $this->getInnerMatcher()->getMatchesForRow($mainSheetRowIdx, $matcherName);
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMatchedRowIndexes()
     */
    public function getMatchedRowIndexes(): array
    {
        return $this->getInnerMatcher()->getMatchedRowIndexes();
    }

    /**
     * {@inheritDoc}
     * @see iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        return $uxon;
    }
}