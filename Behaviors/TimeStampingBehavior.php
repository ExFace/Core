<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\Exceptions\Behaviors\ConcurrentWriteError;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Events\Model\OnMetaAttributeModelValidatedEvent;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\DataTypes\DataSheetDataType;
use exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface;
use exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Tracks time and users that created/changed objects and prevents concurrent writes comparing the update-times.
 * 
 * Automatically saves the current date and time and the user in specified attributes of
 * the object. 
 * 
 * If `updated_on_attribute_alias` is set, conflict detection (optimistic locking) will be performed
 * automatically. The attribute will be marked as "system" causing it's value to be loaded automatically
 * in all widgets, that can perform actions. This ensures, any action has the last update time in it's
 * input data. The behavior than hooks into every update operation on the data source and checks if the 
 * current updated-on in the data source is another value than that of the input data (= the object was 
 * modified in the mean time). If so, an error is thrown.
 * 
 * The optimistic locking can be explicitly disabled by setting `check_for_conflicts_on_update` to `false`.
 * 
 * ## Known limitations
 * 
 * The TimeStampingBehavior is a great help as it can be used to implemnt optimistic locking extremely
 * genericly, but this approach has some limitations:
 * 
 * - concurrency detection is limited for updates with nested data (see property
 * `check_for_conflicts_not_mandatory_for_subsheets` for details)
 * - concurrency detection is limited for mass-updates via filters
 * 
 * ## Examples
 * 
 * ### Just track the last updated timestamp and prevent concurrent writes
 * 
 * ```
 * {
 *  "updated_on_attribute_alias": "UPDATED_ON"
 * }
 * 
 * ```
 * 
 * ### Track times and users
 * 
 * ```
 * {
 *  "created_on_attribute_alias": "CREATED_ON",
 *  "created_by_attribute_alias": "CREATED_BY",
 *  "updated_on_attribute_alias": "MODIFIED_ON",
 *  "updated_by_attribute_alias": "MODIFIED_BY"
 * }
 * 
 * ```
 * 
 * ### Track usernames instead of UIDs
 * 
 * In this example, there is no `updated_on_attribute_alias` so we need to turn off
 * conflict checks - otherwise there will be an error on model validation!
 * 
 * ```
 * {
 *  "created_by_attribute_alias": "CREATED_BY",
 *  "created_by_value_user_attribute_alias": "USERNAME",
 *  "updated_by_attribute_alias": "MODIFIED_BY",
 *  "updated_by_value_user_attribute_alias": "USERNAME",
 *  "check_for_conflicts_on_update": false
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class TimeStampingBehavior extends AbstractBehavior implements DataModifyingBehaviorInterface
{

    private $createdOnAttributeAlias = null;
    
    private $createdByAttributeAlias = null;
    
    private $createdByValueUserAttributeAlias = null;

    private $updatedOnAttributeAlias = null;
    
    private $updatedByAttributeAlias = null;
    
    private $updatedByValueUserAttributeAlias = null;

    private $checkForConflictsOnUpdate = true;
    
    private $checkForConflictsNotMandatoryForSubsheets = true;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        if ($this->hasCreatedByAttribute()) {
            $this->getCreatedByAttribute()
            ->setFixedValue(null)
            ->setDefaultValue(null)
            ->setRequired(false);
        }
        if ($this->hasCreatedOnAttribute()) {
            $this->getCreatedOnAttribute()
            ->setFixedValue(null)
            ->setDefaultValue(null)
            ->setRequired(false);
        }
        if ($this->hasUpdatedByAttribute()) {
            $this->getUpdatedByAttribute()
            ->setFixedValue(null)
            ->setDefaultValue(null)
            ->setRequired(false);
        }
        if ($this->hasUpdatedOnAttribute()) {
            $this->getUpdatedOnAttribute()
            ->setFixedValue(null)
            ->setDefaultValue(null)
            ->setRequired(false);
            if ($this->getCheckForConflictsOnUpdate()) {
                $this->getUpdatedOnAttribute()->setSystem(true)->setDefaultAggregateFunction('MAX');
            }
        }
        
        return parent::register();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $prio = $this->getPriority();
        $this->getWorkbench()->eventManager()
            ->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onCreateSetValues'], $prio)
            ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onUpdateSetValuesAndCheckConflicts'], $prio)
            ->addListener(OnMetaAttributeModelValidatedEvent::getEventName(), [$this, 'onAttributeValidatedDisableFields'], $prio);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()
            ->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onCreateSetValues'])
            ->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onUpdateSetValuesAndCheckConflicts'])
            ->removeListener(OnMetaAttributeModelValidatedEvent::getEventName(), [$this, 'onAttributeValidatedDisableFields']);
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    protected function getCreatedOnAttributeAlias() : ?string
    {
        return $this->createdOnAttributeAlias;
    }

    /**
     * Alias of the attribute, where the creation time is saved.
     * 
     * @uxon-property created_on_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return \exface\Core\Behaviors\TimeStampingBehavior
     */
    public function setCreatedOnAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->createdOnAttributeAlias = $value;
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    protected function getUpdatedOnAttributeAlias() : ?string
    {
        return $this->updatedOnAttributeAlias;
    }

    /**
     * Alias of the attribute, where the last update time is saved.
     *
     * @uxon-property updated_on_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return \exface\Core\Behaviors\TimeStampingBehavior
     */
    public function setUpdatedOnAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->updatedOnAttributeAlias = $value;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function getCheckForConflictsOnUpdate() : bool
    {
        return $this->checkForConflictsOnUpdate;
    }

    /**
     * Set to FALSE to disable automatic race condition prevention.
     * 
     * This option requires `updated_on_attribute_alias`!
     *
     * @uxon-property check_for_conflicts_on_update
     * @uxon-type bool
     * @uxon-default true
     *
     * @param string $value
     * @return \exface\Core\Behaviors\TimeStampingBehavior
     */
    public function setCheckForConflictsOnUpdate(bool $value) : TimeStampingBehavior
    {
        $this->checkForConflictsOnUpdate = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasCreatedOnAttribute() : bool
    {
        return $this->createdOnAttributeAlias !== null;
    }

    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface
     */
    public function getCreatedOnAttribute() : MetaAttributeInterface
    {
        if (! $this->hasCreatedOnAttribute()) {
            throw new BehaviorConfigurationError($this, 'Property `created_on_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getCreatedOnAttributeAlias());
    }
    
    /**
     * 
     * @return bool
     */
    public function hasUpdatedOnAttribute() : bool
    {
        return $this->updatedOnAttributeAlias !== null;
    }

    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface|NULL
     */
    public function getUpdatedOnAttribute() : ?MetaAttributeInterface
    {
        if (! $this->hasUpdatedOnAttribute()) {
            throw new BehaviorConfigurationError($this, 'Property `updated_on_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getUpdatedOnAttributeAlias());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('created_on_attribute_alias', $this->getCreatedOnAttributeAlias());
        $uxon->setProperty('updated_on_attribute_alias', $this->getUpdatedOnAttributeAlias());
        $uxon->setProperty('created_by_attribute_alias', $this->getCreatedOnAttributeAlias());
        $uxon->setProperty('updated_by_attribute_alias', $this->getUpdatedOnAttributeAlias());
        $uxon->setProperty('check_for_conflicts_on_update', $this->getCheckForConflictsOnUpdate());
        return $uxon;
    }
    
    /**
     * 
     * @param DataSheetInterface $sheet
     * @param MetaAttributeInterface $attribute
     * @param mixed $normalizedValue
     * @param bool $overwrite
     * 
     * @return void
     */
    protected function setAttributeValues(DataSheetInterface $sheet, MetaAttributeInterface $attribute, $normalizedValue, bool $overwrite = true)
    {
        if (! ($col = $sheet->getColumns()->getByAttribute($attribute))) {
            $col = $sheet->getColumns()->addFromAttribute($attribute);
        }
        $col->setValueOnAllRows($normalizedValue, $overwrite);
        return;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @return void
     */
    public function onCreateSetValues(OnBeforeCreateDataEvent $event)
    {
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $now = DateTimeDataType::now();
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        if ($this->hasCreatedOnAttribute()) {
            $this->setAttributeValues($data_sheet, $this->getCreatedOnAttribute(), $now);
        }
        if ($this->hasUpdatedOnAttribute()) {
            $this->setAttributeValues($data_sheet, $this->getUpdatedOnAttribute(), $now);
        }
        if ($this->hasCreatedByAttribute()) {
            if ($userValAttr = $this->getCreatedByValueUserAttributeAlias()) {
                $userVal = $user->getAttribute($userValAttr);
            } else {
                $userVal = $user->getUid();
            }
            $this->setAttributeValues($data_sheet, $this->getCreatedByAttribute(), $userVal);
        }
        if ($this->hasUpdatedByAttribute()) {
            if ($userValAttr = $this->getUpdatedByValueUserAttributeAlias()) {
                $userVal = $user->getAttribute($userValAttr);
            } else {
                $userVal = $user->getUid();
            }
            $this->setAttributeValues($data_sheet, $this->getUpdatedByAttribute(), $userVal);
        }
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @return void
     */
    public function onUpdateSetValuesAndCheckConflicts(OnBeforeUpdateDataEvent $event)
    {
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        if ($this->getCheckForConflictsOnUpdate()) {
            $this->onUpdateCheckForConflicts($event);
        }
        
        $now = DateTimeDataType::now();
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        if ($this->hasUpdatedOnAttribute()) {
            $this->setAttributeValues($data_sheet, $this->getUpdatedOnAttribute(), $now);
        }
        if ($this->hasUpdatedByAttribute()) {
            if ($userValAttr = $this->getUpdatedByValueUserAttributeAlias()) {
                $userVal = $user->getAttribute($userValAttr);
            } else {
                $userVal = $user->getUid();
            }
            $this->setAttributeValues($data_sheet, $this->getUpdatedByAttribute(), $userVal);
        }
    }
    
    /**
     * 
     * @param OnMetaAttributeModelValidatedEvent $event
     * @return void
     */
    public function onAttributeValidatedDisableFields(OnMetaAttributeModelValidatedEvent $event) {
        $eventAttr = $event->getAttribute();
        if ( ! (
               ($this->hasCreatedByAttribute() && $eventAttr->isExactly($this->getCreatedByAttribute()))
            || ($this->hasCreatedOnAttribute() && $eventAttr->isExactly($this->getCreatedOnAttribute()))
            || ($this->hasUpdatedByAttribute() && $eventAttr->isExactly($this->getUpdatedByAttribute()))
            || ($this->hasUpdatedOnAttribute() && $eventAttr->isExactly($this->getUpdatedOnAttribute()))
            )) {
            return;
        }
        
        $widget = $event->getMessageList()->getParent();
        $disabledDefaultValue = false;
        $disabledFixedValue = false;
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        foreach ($widget->getChildrenRecursive() as $child) {
            if ($child instanceof iShowSingleAttribute) {
                if ($child->getAttributeAlias() === 'DEFAULT_VALUE') {
                    $child->setValue(null);
                    $child->setDisabled(true);
                    $child->setHint($translator->translate('BEHAVIOR.ALL.PROPERTY_HINT_AUTOGENERATED_BY', ['%behavior%' => $this->getAlias()]) . "\n" . $child->getHint());
                    $disabledDefaultValue = true;
                } elseif ($child->getAttributeAlias() === 'FIXED_VALUE') {
                    $child->setValue(null);
                    $child->setDisabled(true);
                    $child->setHint($translator->translate('BEHAVIOR.ALL.PROPERTY_HINT_AUTOGENERATED_BY', ['%behavior%' => $this->getAlias()]) . "\n"  . $child->getHint());
                    $disabledFixedValue = true;
                }
            }
            if ($disabledDefaultValue && $disabledFixedValue) {
                break;
            }
        }
    }

    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * 
     * @throws DataSheetColumnNotFoundError
     * @throws BehaviorRuntimeError
     * @throws ConcurrentWriteError
     * 
     * @return void
     */
    public function onUpdateCheckForConflicts(OnBeforeUpdateDataEvent $event)
    {
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->addDataSheet('Input data', $data_sheet);
        $logbook->addLine('Received input data with ' . $data_sheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        
        // Check if the updated_on column is present in the sheet
        /* @var $update_times string[] array with an updated-on for every row number in the original data sheet */
        $update_times = [];
        switch (true) {
            case $updated_column = $data_sheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute());
                $update_times = $updated_column->getValues();
                $logbook->addLine('Column "' . $updated_column->getName() . '" found with ' . count($update_times) . ' values');
                break;
            case $data_sheet instanceof DataSheetSubsheetInterface && $this->getCheckForConflictsNotMandatoryForSubsheets():
                // If we have a subsheet without it's own updated-on column, see if the parent sheet has one
                $logbook->addLine('Subsheet detected');
                $parentSheet = $data_sheet->getParentSheet();
                $logbook->addDataSheet('Subsheet parent data', $parentSheet);
                $parentObj = $parentSheet->getMetaObject();
                $logbook->addLine('Parent object is ' . $parentObj->__toString(), 1);
                foreach ($parentObj->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class) as $parentBeh) {
                    /* @var $parentBeh \exface\Core\Behaviors\TimeStampingBehavior */
                    $logbook->addLine('Parent behavior ' . $parentBeh->__toString(), 1);
                    if ($parentBeh->hasUpdatedOnAttribute() && $parentBeh->getCheckForConflictsOnUpdate() === true) {
                        $logbook->addLine('Parent behavior has `updated_on_attribute` and checks for conflicts on update - everything is fine');
                        return;
                    }
                    
                    /* IDEA check the parent sheet for an updated-column here. This did not work right away though
                     * because the time of the subrows differs slightly from that on the main row.
                    if ($parentBeh->hasUpdatedOnAttribute() && $parentUpdatedCol = $parentSheet->getColumns()->getByAttribute($parentBeh->getUpdatedOnAttribute())) {
                         $update_times = [];
                         foreach ($data_sheet->getJoinKeyColumnOfSubsheet()->getValues() as $rowNo => $parentKeyVal) {
                             $parentRowNo = $data_sheet->getJoinKeyColumnOfParentSheet()->findRowByValue($parentKeyVal);
                             $update_times[$rowNo] = $parentUpdatedCol->getValue($parentRowNo);
                         }
                         break 2; // break the switch()
                     }
                     */
                }
                // Don't break here! Let the exception happen!
            default:
                throw new BehaviorRuntimeError($this, 'Cannot check for potential update conflicts in TimeStamping behavior: column "' . $this->getUpdatedOnAttributeAlias() . '" not found in given data sheet!', '7FDSVFK', null, $logbook);
        }
        $update_cnt = count($update_times);
        $logbook->addLine('Input data has ' . $update_cnt . ' rows.');
        
        $conflict_rows = array();
        // See, if the UndoAction is performed currently. It needs special treatment
        $current_action = $this->getCurrentAction();
        if ($current_action instanceof iUndoActions) {
            $logbook->addLine('Undo-action detected: skipping conflict check!');
            // FIXME To check for conflicts when performing and undo, we need to see, if the timestamp changed
            // since the undone action had been performed. The current problem is, however, that we do not store
            // the resulting data sheet of actions in the action history. So for now, undo will work without any
            // timestamp check. The probability of conflicts within the 3-5 seconds, when the undo link is displayed
            // is very small. Still, this really needs to be fixed!
        } else {
            // Check the current update timestamp in the data source
            $check_sheet = $this->readCurrentData($data_sheet, $logbook);
            $logbook->addSection('Comparing timestamps');
            $check_column = $check_sheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute());
            $check_cnt = count($check_column->getValues());
            $logbook->addLine('Found ' . $check_cnt . ' update time values', 1);
            
            // There are different types of updates to handle differently
            switch (true) {
                // A regular update via UID would result in the same number of rows in update data and current data
                case $check_cnt === $update_cnt:
                // An update via UID with create-if-no-UID could also result in less current rows than updated ones (1)
                // Same could apply to a mass-update via filters if no current rows found - but that is not an issues
                // since it would not actually do anything (2)
                case $check_cnt < $update_cnt && $event->getCreateIfUidNotFound():
                    if ($check_cnt === $update_cnt) {
                        $logbook->addLine('Check mode: regular update line-by-line', 1);
                    } else {
                        $logbook->addLine('Check mode: update via UID with create-if-no-UID (because number of rows is different and create-if-no-UID is on', 1);
                    }
                    $uidCol = $data_sheet->getUidColumn();
                    foreach ($update_times as $data_sheet_row_nr => $updated_val) {
                        $rowUid = $uidCol->getCellValue($data_sheet_row_nr);
                        // If no UID is found in the original data
                        if (empty($rowUid)) {
                            if ($event->getCreateIfUidNotFound()) {
                                // see case (1) above
                                continue;
                            } elseif ($check_cnt === 0) {
                                // see case (2) above
                                continue;
                            } else {
                                // Very strange - should not happen :)
                                throw new BehaviorRuntimeError($this, 'Cannot check for concurrent writes: row count mismatch!', '6T6I04D', null, $logbook);
                            }
                        }
                        
                        // If we have a UID, look for conflicts!
                        $check_sheet_row_nr = $check_sheet->getUidColumn()->findRowByValue($rowUid);
                        $check_val = $check_column->getCellValue($check_sheet_row_nr);
                        $updated_date = new \DateTime($updated_val);
                        $check_date = new \DateTime($check_val);
                        if ($updated_date != $check_date) {
                            $conflict_rows[] = $data_sheet_row_nr;
                            $logbook->addLine("**Conflict** found: input row {$data_sheet_row_nr} ({$updated_val}) <=> check row {$check_sheet_row_nr} ({$check_val})", 2);
                        }
                    }
                    break;
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate via Knopf, mehrerer Objekte ueber Knopf mit Filtern
                case $check_cnt > 1 && $update_cnt == 1:
                    $logbook->addLine('Check mode: update by filters (single input row and multiple check rows)', 1);
                    $updated_val = $update_times[0];
                    $check_val = $check_column->aggregate(AggregatorFunctionsDataType::fromValue($this->getWorkbench(), $check_column->getAttribute()->getDefaultAggregateFunction()));
                    $logbook->addLine('Comparing input time ' . $updated_val . ' with max. update time of affected rows: ' . $check_val, 1);
                    try {
                        if (! $data_sheet->hasUidColumn() || empty($data_sheet->getUidColumn()->getValues()[0])) {
                            // Beim Massenupdate mit Filtern wird als TS_UPDATE-Wert die momentane Zeit mitgeliefert, die natuerlich neuer
                            // ist, als alle Werte in der Datenbank. Es werden jedoch keine oid-Werte uebergeben, da nicht klar ist welche
                            // Objekte betroffen sind. Momentan wird daher das Update einfach gestattet, spaeter soll hier eine Warnung
                            // ausgegeben werden.
                            throw new BehaviorRuntimeError($this, 'Cannot check for concurrent writes on mass updates via filters', '6T6I04D', null, $logbook);
                        }
                        $updated_date = new \DateTime($updated_val);
                        $check_date = new \DateTime($check_val);
                    } catch (\Exception $e) {
                        $updated_date = 0;
                        $check_date = 0;
                    }
                    
                    if ($updated_date != $check_date) {
                        $conflict_rows = [];
                        foreach ($check_column->getValues() as $check_sheet_row_nr => $check_val) {
                            $check_date = new \DateTime($check_val);
                            if ($updated_date != $check_date) {
                                $conflict_rows[] = $data_sheet_row_nr;
                                $logbook->addLine("**Conflict** found: input row 0 ({$updated_val}) <=> check row {$check_sheet_row_nr} ({$check_val})", 2);
                            }
                        }
                    }
                    break;
                // In all other cases throw an error - this should not happen actually, but just in case!
                default:
                    throw new BehaviorRuntimeError($this, 'Cannot check for concurrent writes: row count mismatch!', '6T6I04D', null, $logbook);
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
        
        if (empty($conflict_rows) === false) {
            $data_sheet->dataMarkInvalid();
            $reason = '';
            $labelAttr = $data_sheet->getMetaObject()->getLabelAttribute();
            if ($labelAttr && $labelCol = $data_sheet->getColumns()->getByAttribute($labelAttr)) {
                foreach ($conflict_rows as $rowNr) {
                    $reason .= ($reason !== '' ? ', ' : '') . '"' . $labelCol->getCellValue($rowNr) . '"';
                }
                $reason = 'object' . (count($conflict_rows) === 1 ? '' : 's') . ' ' . $reason; 
            } elseif ($data_sheet->hasUidColumn() === true) {
                foreach ($conflict_rows as $rowNr) {
                    $reason .= ($reason !== '' ? ', ' : '') . '"' . $data_sheet->getUidColumn()->getCellValue($rowNr) . '"';
                }
                $reason = 'object' . (count($conflict_rows) === 1 ? '' : 's') . '  with id ' . $reason;
            } else {
                $reason = count($conflict_rows) === 1 ? 'the object' : count($conflict_rows) . ' objects'; 
            }
            $reason .= ' changed in the meantime!';
            throw new ConcurrentWriteError($this, 'Cannot update ' . $data_sheet->getMetaObject()->__toString() . ': ' . $reason, null, null, $logbook, $data_sheet);
        } else {
            $logbook->addLine('No conflicts found');
        }
    }
    
    /**
     * 
     * @param DataSheetInterface $originalSheet
     * @param LogBookInterface $logbook
     * @return DataSheetInterface
     */
    protected function readCurrentData(DataSheetInterface $originalSheet, LogBookInterface $logbook) : DataSheetInterface
    {
        $logbook->addSection('Loading potential conflicts');
        $check_sheet = $originalSheet->copy()->removeRows();
        // Only read current data if there are UIDs or filters in the original sheet!
        // Otherwise it would read ALL data which is useless.
        switch (true) {
            case $originalSheet->hasUidColumn(true) === true:
                $logbook->addLine('Input data has UID column: using UID values as filters');
                $check_sheet->getFilters()->addConditionFromColumnValues($originalSheet->getUidColumn());
                break;
            case $originalSheet->getFilters()->isEmpty() === false:
                $logbook->addLine('Input data has NO UID column, but filters');
                // If there are no UIDs, but filters and a single row, this is a mass-update sheet.
                // In this case, we need to get the maximum of the update-times of the affected data items
                // and compare that to the time in the input data (in `onUpdateCheckForConflicts()`). 
                // FIXME what about the other columns? Read them too? With default aggregators?!
                if ($originalSheet->countRows() === 1 && $updCol = $originalSheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute())) {
                    $logbook->addLine('Input data has a single row and a column with update timestamps - must be a mass-update');
                    $maxSheet = DataSheetFactory::createFromObject($check_sheet->getMetaObject());
                    $maxCol = $maxSheet->getColumns()->addFromExpression(DataAggregation::addAggregatorToAlias($this->getUpdatedOnAttributeAlias(), AggregatorFunctionsDataType::MAX));
                    $maxSheet->setFilters($check_sheet->getFilters()->copy());
                    $maxSheet->dataRead();
                    $logbook->addLine('Reading max. update timestamp for data source using the filters from the input data: "' . $maxCol->getValue(0) . '"');
                    $updCol->setValue(0, $maxCol->getValue(0));
                }
                break;
            default:
                $logbook->addLine('Input data has NO UID column and NO filters - cannot read any check data!');
                $logbook->addDataSheet('Check data', $check_sheet);
                return $check_sheet;
        }
        
        // Remove nested sheet columns
        // TODO better read max-timestamp of all nested data here!
        foreach ($check_sheet->getColumns() as $col) {
            if ($col->getDataType() instanceof DataSheetDataType) {
                $check_sheet->getColumns()->remove($col);
            }
        }
        
        $check_sheet->dataRead();
        $logbook->addLine('Found ' . $check_sheet->countRows() . ' rows in data source');
        $logbook->addDataSheet('Check data', $check_sheet);
        return $check_sheet;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    protected function getCurrentAction() : ?ActionInterface
    {
        return $this->getWorkbench()->getContext()->getScopeWindow()->getActionContext()->getCurrentAction();
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getCreatedByAttributeAlias() : ?string
    {
        return $this->createdByAttributeAlias;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasCreatedByAttribute() : bool
    {
        return $this->createdByAttributeAlias !== null;
    }
    
    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface
     */
    public function getCreatedByAttribute() : MetaAttributeInterface
    {
        if (! $this->hasCreatedByAttribute()) {
            throw new BehaviorConfigurationError($this, 'Property `created_by_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getCreatedByAttributeAlias());
    }
    
    /**
     * The attribute where the the user, that created the object, is to be tracked.
     *  
     * By default, the behavior will save the UID of the user in this attribute.
     * If you need another value (e.g. the username) - use `created_by_value_user_attribute_alias`. 
     * 
     * @uxon-property created_by_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return TimeStampingBehavior
     */
    public function setCreatedByAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->createdByAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getUpdatedByAttributeAlias() : ?string
    {
        return $this->updatedByAttributeAlias;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasUpdatedByAttribute() : bool
    {
        return $this->updatedByAttributeAlias !== null;
    }
    
    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface
     */
    public function getUpdatedByAttribute() : MetaAttributeInterface
    {
        if (! $this->hasUpdatedByAttribute()) {
            throw new BehaviorConfigurationError($this, 'Property `updated_by_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getUpdatedByAttributeAlias());
    }
    
    /**
     * The attribute where the the user, that performed the last change, is to be tracked
     * 
     * By default, the behavior will save the UID of the user in this attribute.
     * If you need another value (e.g. the username) - use `updated_by_value_user_attribute_alias`. 
     * 
     * @uxon-property updated_by_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return TimeStampingBehavior
     */
    public function setUpdatedByAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->updatedByAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getCreatedByValueUserAttributeAlias() : ?string
    {
        return $this->createdByValueUserAttributeAlias;
    }
    
    /**
     * The alias of the user attribute to be saved in `created_by_attribute_alias`.
     * 
     * By default, the behavior will save the UID of the user in the created-by
     * and updated-by attributes. Use this property to specify another attribute
     * of the user, e.g.:
     * 
     * - `USERNAME` to save the username
     * - `OTHER_OBJECT__SOMETHING_ELSE` - to save a value from a related object
     * 
     * @uxon-property created_by_value_user_attribute_alias
     * @uxon-type string
     * @uxon-default UID
     * 
     * @param string $value
     * @return TimeStampingBehavior
     */
    public function setCreatedByValueUserAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->createdByValueUserAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getUpdatedByValueUserAttributeAlias() : ?string
    {
        return $this->updatedByValueUserAttributeAlias;
    }
    
    /**
     * The alias of the user attribute to be saved in `updated_by_attribute_alias`.
     *
     * By default, the behavior will save the UID of the user in the created-by
     * and updated-by attributes. Use this property to specify another attribute
     * of the user, e.g.:
     * 
     * - `USERNAME` to save the username
     * - `OTHER_OBJECT__SOMETHING_ELSE` - to save a value from a related object
     * 
     * @uxon-property updated_by_value_user_attribute_alias
     * @uxon-type string
     * @uxon-default UID
     *
     * @param string $value
     * @return TimeStampingBehavior
     */
    public function setUpdatedByValueUserAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->updatedByValueUserAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getCheckForConflictsNotMandatoryForSubsheets() : bool
    {
        return $this->checkForConflictsNotMandatoryForSubsheets;
    }
    
    /**
     * Set to FALSE to force concurrent writes detection in subsheets
     * 
     * By default pre-update checks (see `check_for_conflicts_on_update`) are not mandatory for nested
     * data. This means, that on update operations that involve subsheets (e.g. tags, categories, etc.
     * saved together with their head-object) the optimistic locking checks are only performed if
     * the corresponding timestamp columns are present in the subsheet. If not AND the object of the
     * main sheet has a timestamping behavior, it is assumed, that having the head-object checked is
     * enough and the update is permitted. If `check_for_conflicts_not_mandatory_for_subsheets` is set 
     * to `FALSE`, such updates would fail. 
     * 
     * @uxon-property check_for_conflicts_not_mandatory_for_subsheets
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return TimeStampingBehavior
     */
    public function setCheckForConflictsNotMandatoryForSubsheets(bool $value) : TimeStampingBehavior
    {
        $this->checkForConflictsNotMandatoryForSubsheets = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface::getAttributesModified()
     */
    public function getAttributesModified(): array
    {
        $attrs = [];
        if ($this->hasCreatedOnAttribute()) {
            $attrs[] = $this->getCreatedOnAttribute();
        }
        if ($this->hasUpdatedOnAttribute()) {
            $attrs[] = $this->getUpdatedOnAttribute();
        }
        if ($this->hasCreatedByAttribute()) {
            $attrs[] = $this->getCreatedByAttribute();
        }
        if ($this->hasUpdatedByAttribute()) {
            $attrs[] = $this->getUpdatedByAttribute();
        }
        return $attrs;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface::canAddColumnsToData()
     */
    public function canAddColumnsToData(): bool
    {
        return true;
    }
}