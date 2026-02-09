<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Contexts\DebugContext;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\QueryBuilders\AbstractSqlBuilder;

/**
 * Replaces the default delete-operation by setting a "deleted"-attribute to a special value.
 * 
 * Instead of letting the data source actually remove data on delete-operations, this behavior
 * performs an update and sets the attribute specified by the `soft_delete_attribute_alias`
 * to the `soft_delete_value`, thus marking the data item as "deleted".
 *
 * Note, that `soft_delete_value` value will be parsed into the data type of the soft-delete
 * attribute, so you can use any supported notation: e.g. a `0` for the current timestamp for
 * time-attribute (e.g. if you have a `deleted_on` attribute with a timestamp instead of
 * a `deleted_flag`).
 * 
 * Soft-deleted data rows will be automatically filtered away when reading data. However, there
 * are different algorithms, that can be used here. Normally, the best one is selected
 * automatically, but this can also be customized via `filter_deleted_on_read`:
 * 
 * - `off` - do filter out soft-deleted rows when reading
 * - `auto` - auto-select the best suitable algorithm
 * - `via_custom_sql_where` - adds a `SQL_SELECT_WHERE` data address property to the object,
 * wich will filter `soft_delete_attribute != value`. This is the preferred algorithm
 * for SQL-based objects because it will automatically filter JOINs too.
 * - `via_datatsheet_condition` - TODO
 * 
 * The default reading logic for soft-delete objects can be configured in `System.config.json`
 * in `BEHAVIORS.SOFTDELETE.FILTER_DELETED_ON_READ`.
 * 
 * ## Side-effects
 * 
 * ### Data-visibility outside of the workbench
 * 
 * This behavior of course does not make soft-deleted data invisible in its data source. Instead, the
 * data is simply filtered away at runtime. If the data source has its own logic (like SQL stored
 * procedures or triggers), you will need to make sure, they treat soft-deleted data accordingly.
 * 
 * ### Update-Events
 * 
 * Since this behavior replaces a delete operation by an update operation, it will generate update
 * events between `OnBeforeDeleteData` and `OnDeleteData`. By default it will disable all behaviors
 * of its object for this short time to avoid inconsistent states, where other behaviors still think
 * the data is there although it is being deleted. This should work for most of the cases, but if
 * you still want on-update behaviors, you can re-enable them by setting `bypass_behaviors_on_update` 
 * to FALSE.
 * 
 * ## Examples
 * 
 * For example, this is used for the Core app's PAGEs. The following configuration sets the
 * attribute `deleted_flag` to `1` for every deleted page instead of actually removing it
 * from the model database.
 * 
 * ```
 * {
 *  "soft_delete_attribute_alias": "deleted_flag",
 *  "soft_delete_value": 1
 * }
 * 
 * ```
 * 
 * @author Thomas Michael
 * @author Andrej Kabachnik
 *
 */
class SoftDeleteBehavior extends AbstractBehavior implements DataModifyingBehaviorInterface
{
    const ON_READ_FILTER_VIA_CUSTOM_SQL_WHERE = 'via_custom_sql_where';
    const ON_READ_FILTER_VIA_DATASHEET_CONDITION = 'via_datasheet_condition';
    const ON_READ_FILTER_OFF = 'off';
    const ON_READ_FILTER_AUTO = 'auto';
    
    private $soft_delete_attribute_alias = null;
    private $soft_delete_value = null;
    private $undelete_value = null;
    
    private $bypassBehaviorsOnUpdate = true;
    
    private ?string $filterDeletedOnRead = null;
    private ?string $customSqlWhere = null;
    private ?string $previousSqlWhere = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onDeleteSetFlag'], $this->getPriority());
        if ($this->willFilterOnRead()) {
            $this->getWorkbench()->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), [$this, 'onReadAddFilter'], $this->getPriority());
        }
        
        if ($this->isDisabled() === false && $this->willUseSqlCustomWhere()) {
            $this->onRegisterAddCustomSqlWhere();
        }
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onDeleteSetFlag']);
        if ($this->willFilterOnRead()) {
            $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onReadAddFilter']);
        }
        
        if ($this->isDisabled() === false && $this->willUseSqlCustomWhere()) {
            $this->getObject()->setDataAddressProperty(AbstractSqlBuilder::DAP_SQL_SELECT_WHERE, $this->previousSqlWhere);
        }
        
        return $this;
    }
    
    protected function onRegisterAddCustomSqlWhere()
    {
        $this->previousSqlWhere = $previousWhere = $this->getObject()->getDataAddressProperty(AbstractSqlBuilder::DAP_SQL_SELECT_WHERE);
        $filterSQL = "[#~alias#].{$this->getSoftDeleteAttribute()->getDataAddress()} != ";
        $flagVal = $this->getSoftDeleteValue();
        switch (true) {
            case is_bool($flagVal):
            case is_numeric($flagVal):
                $filterSQL .= $flagVal;
                break;
            default:
                $filterSQL .= "'$flagVal'";
                break;
        }
        if ($previousWhere && mb_stripos($previousWhere, $filterSQL) === false) {
            $filterSQL = $previousWhere . ' AND ' . $filterSQL;
        }
        $this->getObject()->setDataAddressProperty(AbstractSqlBuilder::DAP_SQL_SELECT_WHERE, $filterSQL);
        $this->customSqlWhere = $filterSQL;
    }
    
    /**
     * This function contains all the logic for setting the given soft-delete-value into the given 
     * soft-delete-attribute.
     * 
     * The entries which shall be marked as deleted are read from the datasheet passed with the event.
     * The rows to set deleted may be passed in two different ways, and have to be handled differently:
     * 
     * - rows are passed as actual rows in the datasheet:
     * The columns of the datasheet are being stripped down to the essential ones (`uid`, `modified_on`
     * and the softDeleteAttribute), then the soft-delete-value is set to the soft-delete-attribute,
     * and the data is updated to the metaobject.
     *          
     * - there are no rows in the events datasheet, only filters:
     * Firstly, all rows which match the filters passed in the datasheet are read from the metaobject,
     * then handle the datasheet as described above.
     * 
     * @param OnBeforeDeleteDataEvent $event
     * 
     * @return void
     */
    public function onDeleteSetFlag(OnBeforeDeleteDataEvent $event)
    {
        if ($this->isDisabled())
            return;
            
        $eventData = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $eventData->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        // prevent deletion of the main object, but dont prevent the cascading deletion
        $event->preventDelete(false);

        $transaction = $event->getTransaction();

        $updateData = $eventData->copy();

        // remove all columns, except of system columns (like 'id' or 'modified-on' columns)
        foreach ($updateData->getColumns() as $col){
            if ($col->getExpressionObj()->isMetaAttribute() === true){
                if($col->getAttribute()->isSystem() === true) {
                    break;
                } else {
                    $updateData->getColumns()->remove($col);
                }
            } else {
                $updateData->getColumns()->remove($col);
            }
        }

        // add the soft-delete-column
        $deletedCol = $updateData->getColumns()->addFromAttribute($this->getSoftDeleteAttribute());
        
        // if there are no datarows in the passed datasheet, but there are filters assigned:
        // add a single row of data, with only the soft-delete-attribute being set, so that this 
        // attribute can be assigned to every row fitting the filter later
        if ($updateData->isEmpty() === true && $updateData->getFilters()->isEmpty(true) === false){
            $updateData->addRow([$deletedCol->getName() => $this->getSoftDeleteValue()]);
        }
        
        // if the datasheet still contains no datarows, then no items have to be marked as deleted
        if ($updateData->isEmpty() === false){
            $deletedCol->setValueOnAllRows($this->getSoftDeleteValue());
            // Disable ALL behaviors temporarily because this particular update is not an update really, but
            // a delete operation. So behaviors, listening to updates should not be triggered.
            if (true === $disableBehaviors = $this->willBypassBehaviorsOnUpdate()) {
                $updateData->getMetaObject()->getBehaviors()->disableTemporarily(true);
            }
            $updateData->dataUpdate(false, $transaction);
            if (true === $disableBehaviors) {
                $updateData->getMetaObject()->getBehaviors()->disableTemporarily(false);
            }
            $eventData->setCounterForRowsInDataSource($updateData->countRowsInDataSource());
        }
            
        // also update the original data sheet for further use
        if ($eventData->isEmpty() === false && $deletedColInEventData = $eventData->getColumns()->getByAttribute($this->getSoftDeleteAttribute())){
            $deletedColInEventData->setValueOnAllRows($this->getSoftDeleteValue());
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }

    public function onReadAddFilter(OnBeforeReadDataEvent $event)
    {
        if ($this->isDisabled())
            return;

        $eventData = $event->getDataSheet();

        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (!$eventData->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        if (! $this->willUseSqlCustomWhere()) {
            // TODO filter via DataSheet
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }
    
    protected function getFilterOnReadAlgorithm() : string
    {
        // Off if debug mode
        if ($this->getWorkbench()->getContext()->getScopeUser()->getVariable(DebugContext::VAR_SHOW_HIDDEN) === true) {
            return self::ON_READ_FILTER_OFF;
        }
        // Default from System.config.json
        if ($this->filterDeletedOnRead === null) {
            $this->filterDeletedOnRead = $this->getWorkbench()->getConfig()->getOption('BEHAVIORS.SOFTDELETE.FILTER_DELETED_ON_READ');
        }
        // Select best option if auto mode
        if ($this->filterDeletedOnRead === self::ON_READ_FILTER_AUTO) {
            if ($this->canUseSqlCustomWhere()) {
                $this->filterDeletedOnRead = self::ON_READ_FILTER_VIA_CUSTOM_SQL_WHERE;
            } else {
                $this->filterDeletedOnRead = self::ON_READ_FILTER_OFF;
            }
        }
        return $this->filterDeletedOnRead;
    }
    
    protected function willFilterOnRead() : bool
    {
        return $this->getFilterOnReadAlgorithm() !== self::ON_READ_FILTER_OFF;
    }

    /**
     * 
     * @param MetaObjectInterface $obj
     * @return bool
     */
    protected function willUseSqlCustomWhere() : bool
    {
        switch ($this->getFilterOnReadAlgorithm()) {
            case self::ON_READ_FILTER_OFF:
                return false;
            case self::ON_READ_FILTER_VIA_CUSTOM_SQL_WHERE:
                if ($this->canUseSqlCustomWhere() === false){
                    throw new BehaviorConfigurationError($this, 'Cannot use `via_custom_sql_where` for filter_deleted_on_read property of SoftDeleteBehavior');
                }
                return true;
        }
        return false;
    }

    /**
     *
     * @param MetaObjectInterface $obj
     * @return bool
     */
    protected function canUseSqlCustomWhere() : bool
    {
        $obj = $this->getObject();
        $dataAddress = $obj->getDataAddress();
        return ($obj->getDataConnection() instanceof SqlDataConnectorInterface)
            && ($dataAddress
                || mb_stripos($dataAddress, '(') === false
            );
    }

    /**
     * Should the behavior filter away soft-deleted rows on read? If yes, how exactly?
     * 
     * By default the behavior will automatically try to determine the most efficient way to filter away soft-deleted
     * rows when reading data. Using this option you can disable this filtering entirely (`off`), or pick a specific
     * algorithm.
     * 
     * @uxon-property filter_deleted_on_read
     * @uxon-type [auto,off,via_custom_sql_where]
     * @uxon-default auto
     * 
     * @param string $value
     * @return SoftDeleteBehavior
     */
    protected function setFilterDeletedOnRead(string $value) : SoftDeleteBehavior
    {
        $constName = 'self::ON_READ_FILTER_' . mb_strtoupper($value);
        if (! defined($constName)) {
            throw new BehaviorConfigurationError($this, 'Invalid value `' . $value . '` for `filter_deleted_on_read`!');
        }
        $this->filterDeletedOnRead = constant($constName);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getSoftDeleteAttributeAlias() 
    {
        return $this->soft_delete_attribute_alias;
    }
    
    /**
     * Alias of the attribute, where the deletion flag is being set.
     *
     * @uxon-property soft_delete_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return \exface\Core\Behaviors\SoftDeleteBehavior
     */
    public function setSoftDeleteAttributeAlias(string $value) : SoftDeleteBehavior
    {
        if ($this->getObject()->hasAttribute($value) === true){
            $this->soft_delete_attribute_alias = $value;
        } else {
            throw new BehaviorConfigurationError($this, 'Configuration error: no attribute ' . $value . 'found in object ' . $this->getObject()->getAlias() . '.');
        }
        return $this;
    }
        
    /**
     * 
     * @return MetaAttributeInterface
     */
    public  function getSoftDeleteAttribute() : MetaAttributeInterface
    {
        try {
            return $this->getObject()->getAttribute($this->getSoftDeleteAttributeAlias()); 
        } catch (MetaAttributeNotFoundError $e) {
            throw new BehaviorConfigurationError($this, 'Configuration error: no attribute "' . $this->getSoftDeleteAttributeAlias() . '" found in object "' . $this->getObject()->getAlias() . '".');
        }
    }
    
    /**
     * 
     * @return string
     */
    public function getSoftDeleteValue() : string
    {
        return $this->soft_delete_value;
    }
    
    /**
     * If this value is placed in the `soft_delete_attribute_alias`, the row will be "deleted".
     *
     * @uxon-property soft_delete_value
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $value
     * @return SoftDeleteBehavior
     */
    public function setSoftDeleteValue(string $value) : SoftDeleteBehavior
    {
        $this->soft_delete_value = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSoftDeleteUndoValue() : ?string
    {
        if ($this->undelete_value === null) {
            // Guess the undelete value for typical soft-delete values (all sorts of flags)
            switch (true) {
                case $this->soft_delete_value === true:
                    $this->undelete_value = false;
                    break;
                case $this->soft_delete_value === false:
                    $this->undelete_value = true;
                    break;
                case $this->soft_delete_value === 1:
                    $this->undelete_value = 0;
                    break;
                case $this->soft_delete_value === 0:
                    $this->undelete_value = 1;
                    break;
            }
        }
        return $this->undelete_value;
    }

    /**
     * The opposite of `soft_delete_value` - this value will be used to restore/undelete rows if necessary
     * 
     * For some typical types of `soft_delete_value` like `1`, `0`, `true` or `false`, the inverse value
     * can be determined automatically. For other values you will need to set it manually - otherwise 
     * undeletes will not work!
     * 
     * @uxon-property soft_delete_undo_value
     * @uxon-type string
     * 
     * @param string $value
     * @return $this
     */
    protected function setSoftDeleteUndoValue(string $value) : SoftDeleteBehavior
    {
        $this->undelete_value = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface::getAttributesModified()
     */
    public function getAttributesModified(DataSheetInterface $inputSheet): array
    {
        if (! $inputSheet->getMetaObject()->isExactly($this->getObject())) {
            return [];
        }
        return [
            $this->getSoftDeleteAttribute()
        ];
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

    /**
     * @param DataSheetInterface $deletedSheet
     * @return DataSheetEventInterface
     */
    public function undeleteData(DataSheetInterface $deletedSheet) : DataSheetEventInterface
    {
        if (! $deletedSheet->getMetaObject()->is($this->getObject())) {
            throw new BehaviorRuntimeError('Cannot restore soft-deleted rows for ' . $deletedSheet->getMetaObject()->__toString(). ' - expecting data of ' . $this->getObject()->__toString() . '.');
        }
        if ($deletedSheet->isEmpty()) {
            throw new BehaviorRuntimeError('Cannot restore soft-deleted rows: no rows found in data of ' . $deletedSheet->getMetaObject()->__toString());
        }
        if ($deletedSheet->hasAggregations() || $deletedSheet->hasAggregateAll()) {
            throw new BehaviorRuntimeError('Cannot restore soft-deleted rows in aggregated data!');
        }
        if (null === $undeleteVal = $this->getSoftDeleteUndoValue()) {
            throw new BehaviorRuntimeError('Cannot restore soft-deleted rows: cannot determine undo-value for `soft_delete_attribute_alias`!');
        }
        if (! $col = $deletedSheet->getColumns()->getByExpression($this->getSoftDeleteAttributeAlias())) {
            $col = $deletedSheet->getColumns()->addFromAttribute($this->getSoftDeleteAttribute());
        }
        $col->setValueOnAllRows($undeleteVal);
        return $deletedSheet;
    }

    /**
     * @return bool
     */
    protected function willBypassBehaviorsOnUpdate() : bool
    {
        return $this->bypassBehaviorsOnUpdate;
    }

    /**
     * Set to FALSE to allow other behaviors to react to update-events generated when setting the soft-delete flags.
     * 
     * By default, this behavior will disable all other behaviors when it performs the update operation to
     * mark given rows as deleted. Setting this property to FALSE will leave all behaviors untouched, so
     * our delete operation will trigger `OnDelete` and `OnUpdate` behaviors.
     * 
     * NOTE: this does not suppress OnUpdate events - only behaviors listening to them!
     * 
     * @uxon-property bypass_behaviors_on_update
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return $this
     */
    protected function setBypassBehaviorsOnUpdate(bool $trueOrFalse) : SoftDeleteBehavior
    {
        $this->bypassBehaviorsOnUpdate = $trueOrFalse;
        return $this;
    }
}