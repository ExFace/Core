<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\DataSheets\DataSheetDuplicatesError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;
use exface\Core\CommonLogic\DataSheets\Matcher\DataRowMatcher;
use exface\Core\CommonLogic\DataSheets\Matcher\MultiMatcher;
use exface\Core\Interfaces\DataSheets\DataMatcherInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;

/**
 * Behavior to prevent a creation of a duplicate dataset on create or update Operations.
 * 
 * It works by searching data in the data source for matches on `compare_attributes` on
 * every create or update operation. If matches are found, the behavior either throws 
 * an error, ignores the new data or performs an update on the existing data row - depending
 * on the properties `on_duplicate_multi_row` and `on_duplicate_single_row`.
 * 
 * By default an error message will be thrown if a duplicate is found. 
 * 
 * In addition to specifying the `compare_attributes`, you can also customize the filters
 * used to search the data source for potential duplicates: `compare_conditions`. These
 * custom filters are than combined with autogenerated filters for matching `compare_attributes`
 * using the `AND` operator.
 * 
 * You can customize the error message by setting `duplicate_error_code` (to load a message from
 * the messages model) or `duplicate_error_text` to specify a custom text directly without creating
 * a message in the metamodel.
 * 
 * ## Examples
 * 
 * ### Check duplicates over multiple attributes
 * 
 * Here is how duplicates of `exface.Core.USER_AUTHENTICATOR` core object are prevented.
 * 
 * ```
 * {
 *  "compare_attributes": [
 *      "USER",
 *      "USER_ROLE"
 *  ],
 *  "on_duplicate_multi_row": 'update'
 *  "on_duplicate_single_row": 'error'
 * }
 * 
 * ````
 * 
 * ### Use a custom filter to search for duplicates excluding canceled orders
 * 
 * In the following example, every prodcuction order position can be assigned a unique serial
 * numbler `SERIAL_NO`. Since a `SERIAL_NO` must be unique among all produced parts, it
 * cannot be assigned to any other non-cancelled positions. However, there may be positions
 * where the serial number is not assigned during production, but will be added later. Thus,
 * the following behavior configuration ensures, a `SERIAL_NO` is unique among all positions, 
 * where it has a value and that do not belong to a cancelled order.
 * 
 * ```
 *  {
 *      "compare_attributes": [
 *          "SERIAL_NO"
 *      ],
 *      "compare_with_conditions": {
 *          "operator": "AND",
 *          "conditions": [
 *              {"expression": "SERIAL_NO", "comparator": "!==", "value": "NULL"},
 *              {"expression": "PRODORDER__STATE", "comparator": "!==", "value": "CANCELED"}
 *          ]
 *      }
 *  }
 * 
 * ```
 * 
 */
class PreventDuplicatesBehavior extends AbstractBehavior
{
    const ON_DUPLICATE_ERROR = 'ERROR';
    
    const ON_DUPLICATE_UPDATE = 'UPDATE';
    
    const ON_DUPLICATE_IGNORE = 'IGNORE';
    
    const LOCATED_IN_EVENT_DATA = 'event_data';
    
    const LOCATED_IN_DATA_SOURCE = 'data_source';
    
    private $onDuplicateMultiRow = null;
    
    private $onDuplicateSingleRow = null;
    
    private $onDuplicateUpdateColumns = null;
    
    private $compareAttributeAliases = [];
    
    private $allowEmptyValuesForAttributeAliases = [];
    
    private $compareWithConditions = null;
    
    private $compareCaseSensitive = false;
    
    private $errorCode = null;
    
    private $errorText = null;
    
    private $ignoreDataSheets = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $priority = $this->getPriority();
        
        if ($priority === null) {
            foreach ($this->getObject()->getBehaviors() as $behavior) {
                if (($behavior instanceof DataModifyingBehaviorInterface) && $behavior->canAddColumnsToData() === true) {
                    $priority = 1 + ($priority === null ? $behavior->getPriority() : max($priority, $behavior->getPriority()));
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnBeforeCreate'], $priority);
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnBeforeUpdate'], $priority);
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnBeforeCreate']);
        
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnBeforeUpdate']);
        
        return $this;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @throws DataSheetDuplicatesError
     * @throws BehaviorRuntimeError
     * @return void
     */
    public function handleOnBeforeCreate(OnBeforeCreateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        if ($eventSheet->isEmpty(true)) {
            return;
        }
        
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->setIndentActive(1);
        
        // Ignore creates that do not contain all compare attributes
        foreach ($this->getCompareAttributeAliases() as $attrAlias) {
            if (! $eventSheet->getColumns()->getByAttribute($object->getAttribute($attrAlias))) {
                $logbook->addLine('Skip behavior as input data for compare attribute ' . $attrAlias . 'is missing.');
                return;
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        
        $mode = $this->getMode($eventSheet);
        $logbook->addLine('Received ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        $logbook->addLine('Running in `' . $mode . '` mode');
        $matcher = $this->getDuplicatesMatcher($eventSheet, $mode, $logbook, true);
        
        if (! $matcher->hasMatches()) {
            $logbook->addLine('No duplicates found');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        
        switch ($mode) {
            case self::ON_DUPLICATE_IGNORE:
                $eventSheet = $this->ignoreDuplicates($eventSheet, $matcher, $logbook);
                break;
            case self::ON_DUPLICATE_UPDATE:
                $eventSheet = $this->updateDuplicates($eventSheet, $matcher, $event->getTransaction(), $logbook);
                break;
            case self::ON_DUPLICATE_ERROR:
            default:
                throw $this->createDuplicatesError($eventSheet, $matcher, $logbook);
                
        }
        
        if ($eventSheet->isEmpty()) {
            $logbook->addLine('No rows left in original data, preventing default event logic!');
            $event->preventCreate();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
        return; 
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @throws DataSheetDuplicatesError
     * @return void
     */
    public function handleOnBeforeUpdate(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        if (in_array($eventSheet, $this->ignoreDataSheets)) {
            return;
        }
        
        if ($eventSheet->isEmpty(true)) {
            return;
        }
        
        // Ignore partial updates, that do not change compared attributes
        $foundCompareCols = false;
        foreach ($this->getCompareAttributeAliases() as $attrAlias) {
            if ($eventSheet->getColumns()->getByAttribute($object->getAttribute($attrAlias))) {
                $foundCompareCols = true;
                break;
            }
        }
        if ($foundCompareCols === false) {
            return;
        } 
        
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->setIndentActive(1);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        
        $mode = $this->getMode($eventSheet);
        $logbook->addLine('Received ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        $logbook->addLine('Running in `' . $mode . '` mode');
        $matcher = $this->getDuplicatesMatcher($eventSheet, $mode, $logbook, false);
        
        if (! $matcher->hasMatches()) {
            $logbook->addLine('No duplicates found');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        
        switch ($mode) {
                // This should never be reached, as the exceptions will be thrown earlier
            case self::ON_DUPLICATE_IGNORE:
                $eventSheet = $this->ignoreDuplicates($eventSheet, $matcher, $logbook);
                break;
            case self::ON_DUPLICATE_UPDATE:
                // When updating with on_duplicate_xxx_update, exact UID-matches must be ignored. Otherwise
                // there will be an infinite loop:
                // - create finds duplicate and orders and update inheriting the duplicates UID
                // - the following update will check AGAIN, find the same duplicate, order another
                // update, and so on.
                $eventSheet = $this->updateDuplicates($eventSheet, $matcher, $event->getTransaction(), $logbook);
                break;
            case self::ON_DUPLICATE_ERROR:
            default:
                throw $this->createDuplicatesError($eventSheet, $matcher, $logbook);
                
        }
        
        if ($eventSheet->isEmpty()) {
            $logbook->addLine('No rows left in original data, preventing default event logic!');
            $event->preventUpdate();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
        return; 
    }
    
    protected function getMode(DataSheetInterface $eventSheet) : string
    {
        $cnt = $eventSheet->countRows();
        switch ($cnt) {
            case 0:
            case 1: return $this->getOnDuplicateSingleRow();
            default: return $this->getOnDuplicateMultiRow();
        }
    }
    
    /**
     * 
     * @param DataSheetInterface $eventSheet
     * @param DataMatcherInterface $matcher
     * @param DataTransactionInterface $transaction
     * @param bool $ignoreUidMatches
     * @throws BehaviorRuntimeError
     * @return DataSheetInterface
     */
    protected function updateDuplicates(DataSheetInterface $eventSheet, MultiMatcher $matcher, DataTransactionInterface $transaction, BehaviorLogBook $logbook) : DataSheetInterface
    {
        $logbook->addSection('Updating duplicates');
        if (! $eventSheet->getMetaObject()->hasUidAttribute())  {
            throw new BehaviorRuntimeError($this, 'Cannot update duplicates of ' . $this->getObject()->__toString() . ': object has no UID column!', null, null, $logbook);
        }
        
        //copy the dataSheet and empty it
        $updateSheet = $eventSheet->copy();
        $updateSheet->removeRows();
        if (! $updateSheet->hasUidColumn()) {
            $uidCol = $updateSheet->getColumns()->addFromUidAttribute();
            $uidColName = $uidCol->getName();
        }
        $duplRowNos = $matcher->getMatchedRowIndexes();
        $logbook->addLine('Found duplicates for ' . count($duplRowNos) . ' rows: ' . implode(', ', $duplRowNos));
        $rowsToRemove = [];
        foreach ($duplRowNos as $duplRowNo) {
            // Don't bother about rows, that need to be removed anyway
            if (in_array($duplRowNo, $rowsToRemove)) {
                continue;
            }
            $row = $eventSheet->getRow($duplRowNo);
            
            // First check duplicates in the data source. There should be at most one and it must have a UID in order
            // to be updated.
            $matches = $matcher->getMatchesForRow($duplRowNo, self::LOCATED_IN_DATA_SOURCE);
            if (! empty($matches)) {
                if (count($matches) > 1) {
                    throw new BehaviorRuntimeError($this, 'Cannot update duplicates of ' . $this->getObject()->__toString() . ': multiple duplicates found in data source!', null, null, $logbook);
                }
                $match = $matches[0] ?? null;
                if($match->hasUid()) {
                    // If the event row does not have a UID value, it was intended to be created.
                    // But since there is a duplicate, it is now an update. In this case, we need
                    // to inherit system attributes from the duplicate! The UID is required to
                    // perform the update, but other things like the timestamp from the TimestampingBehavior
                    // should also be overwritten by values from the duplicate to be updated.
                    if (null === $row[$uidColName] ?? null) {
                        $matchedRow = $match->getMatchedRow();
                        foreach ($eventSheet->getMetaObject()->getAttributes()->getSystem() as $systemAttr) {
                            $row[$systemAttr->getAlias()] = $matchedRow[$systemAttr->getAlias()];
                        }
                    }
                    $updateSheet->addRow($row);
                    $rowsToRemove[] = $duplRowNo;
                } else {
                    throw new BehaviorRuntimeError($this, 'Cannot update duplicates of ' . $this->getObject()->__toString() . ': a duplicate was found, but it has no UID, so it cannot be updated!', null, null, $logbook);
                }
            }
            
            // For duplicates found within the event data, just keep the first one. So remove all other
            // (duplicate) rows in the sheet.
            $matches = $matcher->getMatchesForRow($duplRowNo, self::LOCATED_IN_EVENT_DATA);
            foreach ($matches as $match) {
                $rowsToRemove[] = $match->getMatchedRowIndex();
            }
        }
        
        if (! empty($rowsToRemove)) {
            $rowsToRemove = array_unique($rowsToRemove);
            $eventSheet->removeRows($rowsToRemove);
            $logbook->addLine('Removed ' . count($rowsToRemove) . ' rows from original data: ' . implode(', ', $rowsToRemove));
        } else {
            $logbook->addLine('No rows to remove');
        }
        
        if (! $updateSheet->isEmpty()) {
            $this->ignoreDataSheets[] = $updateSheet;
            $logbook->addLine('Updating ' . $updateSheet->countRows() . ' rows instead of original operation');
            $updateSheet->dataUpdate(false, $transaction);
        }
        
        return $eventSheet;
    }
    
    protected function ignoreDuplicates(DataSheetInterface $eventSheet, MultiMatcher $matcher, BehaviorLogBook $logbook) : DataSheetInterface
    {
        $logbook->addSection('Ignoring duplicates');
        $duplRowNos = $matcher->getMatchedRowIndexes();
        $logbook->addLine('Found duplicates for ' . count($duplRowNos) . ' rows: ' . implode(', ', $duplRowNos));
        $rowsToRemove = [];
        foreach ($duplRowNos as $duplRowNo) {
            // Don't bother about rows, that need to be removed anyway
            if (in_array($duplRowNo, $rowsToRemove)) {
                continue;
            }
            
            // First check duplicates in the data source. Remove this rows from the data source if it
            // has a match here.
            if (! empty($matcher->getMatchesForRow($duplRowNo, self::LOCATED_IN_DATA_SOURCE))) {
                $rowsToRemove[] = $duplRowNo;
            }
            
            // For duplicates found within the event data, just keep the first one. So remove all other
            // (duplicate) rows in the sheet.
            $matches = $matcher->getMatchesForRow($duplRowNo, self::LOCATED_IN_EVENT_DATA);
            foreach ($matches as $match) {
                $rowsToRemove[] = $match->getMatchedRowIndex();
            }
        }
        
        if (! empty($rowsToRemove)) {
            $rowsToRemove = array_unique($rowsToRemove);
            $eventSheet->removeRows($rowsToRemove);
            $logbook->addLine('Removed ' . count($rowsToRemove) . ' rows: ' . implode(', ', $rowsToRemove));
        } else {
            $logbook->addLine('No rows to remove');
        }
        
        return $eventSheet;
    }
    
    /**
     * 
     * @param DataSheetInterface $eventSheet
     * @return DataMatcherInterface
     */
    protected function getDuplicatesMatcher(DataSheetInterface $eventSheet, string $mode, BehaviorLogBook $logbook, bool $treatUidMatchesAsDuplicates = true) : DataMatcherInterface
    {   
        $eventDataCols = $eventSheet->getColumns();
        $logbook->addSection('Searching for potential duplicates');
        
        $compareCols = [];
        $missingCols = [];
        $missingAttrs = [];
        $logbook->addLine('Will compare these attributes: `' . implode('`, `', $this->getCompareAttributeAliases()) . '`');
        foreach ($this->getCompareAttributeAliases() as $attrAlias) {
            $attr = $this->getObject()->getAttribute($attrAlias);
            if ($col = $eventDataCols->getByAttribute($attr)) {
                $compareCols[] = $col;
            } else {
                $missingAttrs[] = $attr;
            }
        }
        
        if (empty($missingAttrs) === false) {
            $logbook->addLine('Missing attributes in original data:');
            if ($eventSheet->hasUidColumn(true) === false) {
                $logbook->addLine('Cannot read missing attributes because data has no UIDs!');
                throw new BehaviorRuntimeError($this, 'Cannot check for duplicates of ' . $this->getObject()->getName() . '" (alias ' . $this->getObject()->getAliasWithNamespace() . '): not enough data!', '7PNKJ50', null, $logbook);
            } 
            
            $eventRows = $eventSheet->getRows();
            $missingAttrSheet = DataSheetFactory::createFromObject($this->getObject());
            $missingAttrSheet->getFilters()->addConditionFromColumnValues($eventSheet->getUidColumn());
            $missingCols = [];
            foreach ($missingAttrs as $attr) {
                $logbook->addLine($attr->getAliasWithRelationPath(), 1);
                $missingCols[] = $missingAttrSheet->getColumns()->addFromAttribute($attr);
            }
            $missingAttrSheet->dataRead();
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
        
        $matcher = new MultiMatcher($mainSheet);
        
        // Extract rows from event data, that are relevant for duplicate search
        if ($this->hasCustomConditions()) {
            $customConditionsFilters = ConditionGroupFactory::createForDataSheet($mainSheet, $this->getCompareWithConditions()->getOperator());
            foreach ($this->getCompareWithConditions()->getConditions() as $cond) {
                if ($mainSheet->getColumns()->getByExpression($cond->getExpression())) {
                    $customConditionsFilters->addCondition($cond);
                }
            }
            $logbook->addLine('Removing non-relevant data via `compare_with_conditions`: ' . $customConditionsFilters->__toString());
            $mainSheet = $mainSheet->extract($customConditionsFilters);
        } else {
            $logbook->addLine('Will search for duplicates for all rows, no filtering required');
        }
        
        $logbook->addDataSheet('Data to compare', $mainSheet);
        
        // See if there are duplicates within the current set of data
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
                    throw new BehaviorRuntimeError($this, 'Cannot check for duplicates for ' . $this->getObject()->__toString() . ': no input data found for attribute "' . $col->getAttributeAlias() . '"!', null, null, $logbook);
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
                        (new BehaviorRuntimeError($this, 'Cannot check for duplicates for ' . $this->getObject()->__toString() . ': missing required value for attribute "' . $col->getAttributeAlias() . ' in row "' . $rowNo . '"!', null, null, $logbook)), // $previous
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
        $checkSheet->dataRead();
        
        $logbook->addDataSheet('Data in data source', $checkSheet);
        
        if ($checkSheet->isEmpty()) {
            $logbook->addLine('No potential duplicates found in data source');
            return $matcher;
        } else {
            $logbook->addLine($checkSheet->countRows() . ' potential duplicates found in data source according to the computed filters');
        }
        
        $dataSourceMatcher = new DataRowMatcher($mainSheet, $checkSheet, $compareCols, self::LOCATED_IN_DATA_SOURCE);
        if ($treatUidMatchesAsDuplicates === false) {
            $dataSourceMatcher->setIgnoreUidMatches(true);
        }
        $matcher->addMatcher($dataSourceMatcher);
        
        return $matcher;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param array[] $matcher
     * @return DataSheetDuplicatesError
     */
    protected function createDuplicatesError(DataSheetInterface $dataSheet, DataMatcherInterface $matcher, BehaviorLogBook $logbook) : DataSheetDuplicatesError
    {
        $logbook->addLine('Sending an error about the duplicates');
        $object = $dataSheet->getMetaObject();
        $labelAttributeAlias = $object->getLabelAttributeAlias();
        $rows = $dataSheet->getRows();
        $errorRowDescriptor = '';
        $errorMessage = '';
        $duplValues = [];
        $duplRowNos = $matcher->getMatchedRowIndexes();
        foreach ($duplRowNos as $duplRowNo) {
            $row = $rows[$duplRowNo];
            $value = strval($duplRowNo + 1);
            if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                $value .= " ({$row[$labelAttributeAlias]})";
            } 
            $duplValues[] = $value;
        }
        //remove duplicate values that were added by using the LabelAttributeAlias to create error values
        $duplValues = array_unique($duplValues);
        $errorRowDescriptor = implode(', ', $duplValues);
        $logbook->addLine('Found duplicates for ' . count($duplValues) . ' rows: ' . implode(', ', $duplRowNos));
        
        try {
            $customErrorText = $this->getDuplicateErrorText();
            if ($customErrorText !== null) {
                // TODO add placeholders for data!
                $errorMessage = $customErrorText;
            } else {
                $errorMessage = $this->translate('BEHAVIOR.PREVENTDUPLICATEBEHAVIOR.CREATE_DUPLICATES_FORBIDDEN_ERROR', ['%row%' => $errorRowDescriptor, '%object%' => '"' . $object->getName() . '"']);
            
            }
            $customErrorCode = $this->getDuplicateErrorCode();
            $ex = new DataSheetDuplicatesError($dataSheet, $errorMessage, $customErrorCode);
            if ($customErrorText !== null || $customErrorCode === null) {
                $ex->setUseExceptionMessageAsTitle(true);
            }
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $ex = new DataSheetDuplicatesError($dataSheet, 'Cannot update/create data, as it contains duplicates of already existing data!', $this->getDuplicateErrorCode());
        }
        
        return $ex;
    }
    
    /**
     * The attributes determining if a dataset is a duplicate.
     *
     * @uxon-property compare_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $arrayOrUxon
     * @return PreventDuplicatesBehavior
     */
    public function setCompareAttributes($arrayOrUxon) : PreventDuplicatesBehavior
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
     * @throws BehaviorConfigurationError
     * @return string[]
     */
    protected function getCompareAttributeAliases() : array
    {
        if (empty($this->compareAttributeAliases)) {
            throw new BehaviorConfigurationError($this, "No attributes were set in '{$this->getAlias()}' of the object '{$this->getObject()->getAlias()}' to determine if a dataset is a duplicate or not! Set atleast one attribute via the 'compare_attributes' uxon property!");
        }
        return $this->compareAttributeAliases;
    }
    
    
    protected function getCompareWithConditions() : ?ConditionGroupInterface
    {
        if ($this->compareWithConditions instanceof UxonObject) {
            $this->compareWithConditions = ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->compareWithConditions, $this->getObject());
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
     * @return PreventDuplicatesBehavior
     */
    protected function setCompareWithConditions(UxonObject $value) : PreventDuplicatesBehavior
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
    
    /**
     * Set what should happen if a duplicate is found in a multi row create operation.
     * To ignore duplicates set it to ´ignore´.
     * To update the existing duplicate data row instead of creating a new one set it to ´update´.
     * To show an error when duplicate is found set it to ´error´, that is the default behavior.
     * 
     * @uxon-property on_duplicate_multi_row
     * @uxon-type [error,ignore,update]
     * @uxon-default error
     * 
     * @param string $value
     * @return PreventDuplicatesBehavior
     */
    public function setOnDuplicateMultiRow (string $value) : PreventDuplicatesBehavior
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::ON_DUPLICATE_' . $value)) {
            $this->onDuplicateMultiRow = $value;
        } else {
            throw new BehaviorConfigurationError($this, 'Invalid behavior on duplicates "' . $value . '". Only ERROR, IGNORE and UPDATE are allowed!', '6TA2Y6A');
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getOnDuplicateMultiRow() : string
    {
        if ($this->onDuplicateMultiRow !== null) {
            return $this->onDuplicateMultiRow;
        }
        return self::ON_DUPLICATE_ERROR;
    }
    
    /**
     * Set what should happen if a duplicate is found in a single row create operation.
     * To ignore duplicates set it to ´ignore´.
     * To update the existing duplicate data row instead of creating a new one set it to ´update´.
     * To show an error when duplicate is found set it to ´error´, that is the default behavior.
     * 
     * @uxon-property on_duplicate_single_row
     * @uxon-type [error,ignore,update]
     * @uxon-default error
     * 
     * @param string $value
     * @return PreventDuplicatesBehavior
     */
    public function setOnDuplicateSingleRow(string $value) : PreventDuplicatesBehavior
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::ON_DUPLICATE_' . $value)) {
            $this->onDuplicateSingleRow = $value;
        } else {
            throw new BehaviorConfigurationError($this, 'Invalid behavior on duplicates "' . $value . '". Only ERROR, IGNORE and UPDATE are allowed!', '6TA2Y6A');
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getOnDuplicateSingleRow() : string
    {
        if ($this->onDuplicateSingleRow !== null) {
            return $this->onDuplicateSingleRow;
        }
        return self::ON_DUPLICATE_ERROR;
    }
    
    /**
     * Custom message code to use in case of errors
     * 
     * @uxon-property duplicate_error_code
     * @uxon-type string
     * 
     * @param string $code
     * @return PreventDuplicatesBehavior
     */
    public function setDuplicateErrorCode (string $code) : PreventDuplicatesBehavior
    {
        $this->errorCode = $code;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getDuplicateErrorCode() : ?string
    {
        return $this->errorCode;
    }
    
    /**
     * 
     * @return string
     */
    public function getDuplicateErrorText() : ?string
    {
        return $this->errorText;
    }
    
    /**
     * Custom text to use in error messages
     * 
     * @uxon-property duplicate_error_text
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @param string $value
     * @return PreventDuplicatesBehavior
     */
    protected function setDuplicateErrorText(string $value) : PreventDuplicatesBehavior
    {
        $this->errorText = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $messageId
     * @param array $placeholderValues
     * @param float $pluralNumber
     * @return string
     */
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
    
    /**
     * 
     * @return bool
     */
    protected function getCompareCaseSensitive() : bool
    {
        return $this->compareCaseSensitive;
    }
    
    /**
     * Set to TRUE for case sensitive string comparison
     * 
     * @uxon-property compare_case_sensitive
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return PreventDuplicatesBehavior
     */
    protected function setCompareCaseSensitive(bool $value) : PreventDuplicatesBehavior
    {
        $this->compareCaseSensitive = $value;
        return $this;
    }
    
    protected function getOnDuplicateUpdateColumns() : array
    {
        return $this->onDuplicateUpdateColumns;
    }
    
    /**
     * 
     * @param array $value
     * @return PreventDuplicatesBehavior
     */
    protected function setOnDuplicateUpdateColumns(array $value) : PreventDuplicatesBehavior
    {
        $this->onDuplicateUpdateColumns = $value;
        return $this;
    }
}