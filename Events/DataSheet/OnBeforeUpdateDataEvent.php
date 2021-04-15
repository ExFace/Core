<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

/**
 * Event fired before a data sheet starts updating it's data in the corresponding data sources.
 * 
 * Use `$event->preventUpdate()` to disable the general create logic of the data sheet: i.e.
 * the UPDATE-query to the data source(s).
 * 
 * @event exface.Core.DataSheet.OnBeforeUpdateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeUpdateDataEvent extends AbstractDataSheetEvent
{
    private $preventUpdate = false;
    
    private $createIfUidNotFound = false;
    
    private $oldDataSheet = null;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param DataTransactionInterface $transaction
     * @param bool $updateIfUidExists
     */
    public function __construct(DataSheetInterface $dataSheet, DataTransactionInterface $transaction, bool $createIfUidNotFound = false)
    {
        parent::__construct($dataSheet, $transaction);
        $this->createIfUidNotFound = $createIfUidNotFound;
    }
    
    /**
     * Prevents the default update operation.
     *
     * Use this if the event handler fills the data sheet.
     *
     * @return OnBeforeUpdateDataEvent
     */
    public function preventUpdate() : OnBeforeUpdateDataEvent
    {
        $this->preventUpdate = true;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isPreventUpdate() : bool
    {
        return $this->preventUpdate;
    }
    
    /**
     * Returns the value of the create-if-not-exists-flag used in the operation, that fired the event.
     * 
     * @return bool
     */
    public function getCreateIfUidNotFound() : bool
    {
        return $this->createIfUidNotFound;
    }
    
    /**
     * Returns an identical data sheet as that of the event, but filled with current data (or NULL if not possible)
     * 
     * @return DataSheetInterface|NULL
     */
    public function getDataSheetWithOldData() : ?DataSheetInterface
    {
        if ($this->oldDataSheet === null) {
            $newSheet = $this->getDataSheet();
            $oldSheet = $newSheet->copy()->removeRows();
            // Only read current data if there are UIDs or filters in the original sheet!
            // Otherwise it would read ALL data which is useless.
            // TODO what if it is a mass update via filters?
            // if ($newSheet->getFilters()->isEmpty() === false) {
            if ($newSheet->hasUidColumn(true) === true) {
                $oldSheet->getFilters()->addConditionFromColumnValues($newSheet->getUidColumn());
                $oldSheet->dataRead();
                $this->oldDataSheet = $oldSheet;
            } else {
                $this->oldDataSheet = false;
            }
        }
        if ($oldSheet === false) {
            return null;
        }
        return $this->oldDataSheet;
    }
    
    /**
     * Returns TRUE if the given column of the updated data sheet will actually change data.
     * 
     * Retruns FALSE if the updated data does not contain a column with a matching name or
     * the data of that column is the same as the current data in the
     * data source for each UID.
     * 
     * Will return NULL if it is not sure, if the data will be changed (e.g. if the data
     * has rows without UIDs).
     * 
     * @param string $columnName
     * @return bool|NULL
     */
    public function willChangeColumn(string $columnName) : ?bool
    {
        $newData = $this->getDataSheet();
        $newCol = $newData->getColumns()->get($columnName);
        if (! $newCol) {
            return false;
        }
        
        return $this->willChange($newCol);
    }
    
    /**
     * Returns TRUE if the updated data will change the value of the given attribute in at least one row.
     * 
     * Retruns FALSE if the updated data does not contain a column for the give attribute
     * or the data in that column is the same as the current data in the data source for 
     * each UID.
     * 
     * Will return NULL if it is not sure, if the data will be changed (e.g. if the data
     * has rows without UIDs).
     * 
     * @param MetaAttributeInterface $attribute
     * @return bool|NULL
     */
    public function willChangeAttribute(MetaAttributeInterface $attribute) : ?bool
    {
        $newData = $this->getDataSheet();
        if (! $attribute->getRelationPath()->getStartObject()->is($newData->getMetaObject())) {
            return false;
        }
        $newCol = $newData->getColumns()->getByAttribute($attribute);
        if (! $newCol) {
            return false;
        }
        
        return $this->willChange($newCol);
    }
    
    /**
     * Returns TRUE if the given column of the updated data sheet will actually change data.
     * 
     * Retruns FALSE if the data in this column is the same as the current data in the
     * data source for each UID.
     * 
     * Will return NULL if it is not sure, if the data will be changed (e.g. if the data
     * has rows without UIDs).
     * 
     * @param DataColumnInterface $newCol
     * @return bool|NULL
     */
    public function willChange(DataColumnInterface $newCol) : ?bool
    {
        $newData = $this->getDataSheet();
        
        if ($newData->hasAggregations()) {
            return null;
        }
        
        $oldData = $this->getDataSheetWithOldData();
        if ($oldData === null) {
            return null;
        }
        $oldCol = $oldData->getColumns()->get($newCol->getName());
        
        if ($newData->hasUidColumn(true)) {
            return empty($newCol->diffValuesByUid($oldCol)) === false;
        }
        
        return null;
    }
    
    /**
     * Returns TRUE if the updated data is different from the current state of the data source.
     * 
     * Returns NULL if differences cannot be determined reliably for at least one column
     * (e.g. if the data has rows without UIDs).
     * 
     * Returns FALSE if no cell contains an actual change.
     * 
     * @return bool|NULL
     */
    public function willChangeAnything() : ?bool
    {
        foreach ($this->getDataSheet()->getColumns() as $newCol) {
            switch ($this->willChange($newCol)) {
                case true: return true;
                case null: return null;
            }
        }
        return false;
    }
    
    /**
     * Adds a callback to be performed after the update was done.
     * 
     * This is a convenience method for adding an event listener to `OnUpdateDataEvent`, that will
     * only react to the data sheet of this event.
     * 
     * @param callable $callback
     * @param int $priority
     * @return OnBeforeUpdateDataEvent
     */
    public function doAfterUpdate(callable $callback, int $priority = null) : OnBeforeUpdateDataEvent
    {
        $thisSheet = $this->getDataSheet();
        $this->getWorkbench()->eventManager()->addListener(OnUpdateDataEvent::getEventName(), function(OnUpdateDataEvent $event) use ($callback, $thisSheet) {
            if ($event->getDataSheet() !== $thisSheet) {
                return;
            } else {
                call_user_func($callback, $event);
            }
        }, $priority);
    }
}