<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\Exceptions\Behaviors\ConcurrentWriteError;
use exface\Core\Exceptions\Behaviors\ConcurrentWritesCannotBePreventedWarning;
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

/**
 * Prevents concurrent writes using a timestamp updated with every crate/update operation.
 * 
 * ## Example
 * 
 * ```
 * {
 *  "updated_on_attribute_alias": "UPDATED_ON"
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class TimeStampingBehavior extends AbstractBehavior
{

    private $createdOnAttributeAlias = null;
    
    private $createdByAttributeAlias = null;
    
    private $createdByValueUserAttributeAlias = null;

    private $updatedOnAttributeAlias = null;
    
    private $updatedByAttributeAlias = null;
    
    private $updatedByValueUserAttributeAlias = null;

    private $check_for_conflicts_on_update = true;

    public function register() : BehaviorInterface
    {
        if ($this->hasCreatedByAttribute()) {
            $this->getCreatedByAttribute()->setFixedValue(null)->setDefaultValue(null);
        }
        if ($this->hasCreatedOnAttribute()) {
            $this->getCreatedOnAttribute()->setFixedValue(null)->setDefaultValue(null);
        }
        if ($this->hasUpdatedByAttribute()) {
            $this->getUpdatedByAttribute()->setFixedValue(null)->setDefaultValue(null);
        }
        if ($this->hasUpdatedOnAttribute()) {
            $this->getUpdatedOnAttribute()->setFixedValue(null)->setDefaultValue(null);
            if ($this->getCheckForConflictsOnUpdate()) {
                $this->getUpdatedOnAttribute()->setSystem(true)->setDefaultAggregateFunction('MAX');
            }
        }
        
        $this->registerEventListeners();
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()
            ->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onCreateSetValues'])
            ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onUpdateSetValuesAndCheckConflicts'])
            ->addListener(OnMetaAttributeModelValidatedEvent::getEventName(), [$this, 'onAttributeValidatedDisableFields']);
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
        return $this->check_for_conflicts_on_update;
    }

    /**
     * Set to FALSE to disable automatic race condition prevention.
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
        $this->check_for_conflicts_on_update = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasCreatedOnAttribute() : bool
    {
        return $this->createdOnAttributeAlias !== null;
    }

    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface
     */
    protected function getCreatedOnAttribute() : MetaAttributeInterface
    {
        if (! $this->hasCreatedOnAttribute()) {
            throw new BehaviorConfigurationError($this->getObject(), 'Property `created_on_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getCreatedOnAttributeAlias());
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasUpdatedOnAttribute() : bool
    {
        return $this->updatedOnAttributeAlias !== null;
    }

    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface|NULL
     */
    protected function getUpdatedOnAttribute() : ?MetaAttributeInterface
    {
        if (! $this->hasUpdatedOnAttribute()) {
            throw new BehaviorConfigurationError($this->getObject(), 'Property `updated_on_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
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
     * @throws ConcurrentWritesCannotBePreventedWarning
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
        
        // Check if the updated_on column is present in the sheet
        $updated_column = $data_sheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute());
        if (! $updated_column) {
            throw new DataSheetColumnNotFoundError($data_sheet, 'Cannot check for potential update conflicts in TimeStamping behavior: column "' . $this->getUpdatedOnAttributeAlias() . '" not found in given data sheet!');
        }
        $update_qty = count($updated_column->getValues());
        
        $conflict_rows = array();
        // See, if the UndoAction is performed currently. It needs special treatment
        $current_action = $this->getCurrentAction();
        if ($current_action instanceof iUndoActions) {
            // FIXME To check for conflicts when performing and undo, we need to see, if the timestamp changed
            // since the undone action had been performed. The current problem is, however, that we do not store
            // the resulting data sheet of actions in the action history. So for now, undo will work without any
            // timestamp check. The probability of conflicts within the 3-5 seconds, when the undo link is displayed
            // is very small. Still, this really needs to be fixed!
        } else {
            // Check the current update timestamp in the data source
            $check_sheet = $this->readCurrentData($data_sheet);
            $check_column = $check_sheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute());
            $check_qty = count($check_column->getValues());
            
            if ($check_qty === $update_qty) {
                // beim Bearbeiten eines einzelnen Objektes ueber einfaches Bearbeiten, Massenupdate in Tabelle, Massenupdate
                // ueber Knopf, ueber Knopf mit Filtern $check_nr == 1, $update_nr == 1
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate in Tabelle $check_nr == $update_nr > 1
                foreach ($updated_column->getValues() as $row_nr => $updated_val) {
                    $check_val = $check_column->getCellValue($check_sheet->getUidColumn()->findRowByValue($data_sheet->getUidColumn()->getCellValue($row_nr)));
                    try {
                        if (empty($data_sheet->getUidColumn()->getValues()[$row_nr])) {
                            // Beim Massenupdate mit Filtern wird als TS_UPDATE-Wert die momentane Zeit mitgeliefert, die natuerlich neuer
                            // ist, als alle Werte in der Datenbank. Es werden jedoch keine oid-Werte uebergeben, da nicht klar ist welche
                            // Objekte betroffen sind. Momentan wird daher das Update einfach gestattet, spaeter soll hier eine Warnung
                            // ausgegeben werden.
                            throw new ConcurrentWritesCannotBePreventedWarning('Cannot check for concurrent writes on mass updates via filters', '6T6I04D');
                        }
                        $updated_date = new \DateTime($updated_val);
                        $check_date = new \DateTime($check_val);
                    } catch (\Exception $e) {
                        $updated_date = 0;
                        $check_date = 0;
                    }
                    
                    if ($updated_date != $check_date) {
                        $conflict_rows[] = $row_nr;
                    }
                }
            } else if ($check_qty > 1 && $update_qty == 1) {
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate ueber Knopf, mehrerer Objekte ueber Knopf mit Filtern
                // $check_nr > 1, $update_nr == 1
                $updated_val = $updated_column->getValues()[0];
                $check_val = $check_column->aggregate(AggregatorFunctionsDataType::fromValue($this->getWorkbench(), $check_column->getAttribute()->getDefaultAggregateFunction()));
                
                try {
                    if (empty($data_sheet->getUidColumn()->getValues()[0])) {
                        // Beim Massenupdate mit Filtern wird als TS_UPDATE-Wert die momentane Zeit mitgeliefert, die natuerlich neuer
                        // ist, als alle Werte in der Datenbank. Es werden jedoch keine oid-Werte uebergeben, da nicht klar ist welche
                        // Objekte betroffen sind. Momentan wird daher das Update einfach gestattet, spaeter soll hier eine Warnung
                        // ausgegeben werden.
                        throw new ConcurrentWritesCannotBePreventedWarning('Cannot check for concurrent writes on mass updates via filters', '6T6I04D');
                    }
                    $updated_date = new \DateTime($updated_val);
                    $check_date = new \DateTime($check_val);
                } catch (\Exception $e) {
                    $updated_date = 0;
                    $check_date = 0;
                }
                
                if ($updated_date != $check_date) {
                    $conflict_rows = array_keys($check_column->getValues(), $check_val);
                }
            }
        }
        
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
            throw new ConcurrentWriteError($data_sheet, 'Cannot update ' . $data_sheet->getMetaObject()->getName() . ' (' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '): ' . $reason);
        }
    }
    
    /**
     * 
     * @param DataSheetInterface $originalSheet
     * @return DataSheetInterface
     */
    protected function readCurrentData(DataSheetInterface $originalSheet) : DataSheetInterface
    {
        $check_sheet = $originalSheet->copy()->removeRows();
        // Only read current data if there are UIDs or filters in the original sheet!
        // Otherwise it would read ALL data which is useless.
        if ($originalSheet->hasUidColumn(true) === true || $originalSheet->getFilters()->isEmpty() === false) {
            $check_sheet->getFilters()->addConditionFromColumnValues($originalSheet->getUidColumn());
            $check_sheet->dataRead();
        }
        return $check_sheet;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    protected function getCurrentAction()
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
    protected function hasCreatedByAttribute() : bool
    {
        return $this->createdByAttributeAlias !== null;
    }
    
    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface
     */
    protected function getCreatedByAttribute() : MetaAttributeInterface
    {
        if (! $this->hasCreatedByAttribute()) {
            throw new BehaviorConfigurationError($this->getObject(), 'Property `created_by_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getCreatedByAttributeAlias());
    }
    
    /**
     * The attribute where the UID of the creator user is to be saved
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
    protected function hasUpdatedByAttribute() : bool
    {
        return $this->updatedByAttributeAlias !== null;
    }
    
    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaAttributeInterface
     */
    protected function getUpdatedByAttribute() : MetaAttributeInterface
    {
        if (! $this->hasUpdatedByAttribute()) {
            throw new BehaviorConfigurationError($this->getObject(), 'Property `updated_by_attribute_alias` not set for TimestampingBehavior of object "' . $this->getObject()->getAliasWithNamespace() . '"!');
        }
        return $this->getObject()->getAttribute($this->getUpdatedByAttributeAlias());
    }
    
    /**
     * The attribute where the UID of the last updater user is to be saved
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
    
    protected function getCreatedByValueUserAttributeAlias() : ?string
    {
        return $this->createdByValueUserAttributeAlias;
    }
    
    public function setCreatedByValueUserAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->createdByValueUserAttributeAlias = $value;
        return $this;
    }
    
    protected function getUpdatedByValueUserAttributeAlias() : ?string
    {
        return $this->updatedByValueUserAttributeAlias;
    }
    
    public function setUpdatedByValueUserAttributeAlias(string $value) : TimeStampingBehavior
    {
        $this->updatedByValueUserAttributeAlias = $value;
        return $this;
    }
}
?>