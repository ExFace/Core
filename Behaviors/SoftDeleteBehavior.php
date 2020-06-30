<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

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
class SoftDeleteBehavior extends AbstractBehavior
{
    private $soft_delete_attribute_alias = null;
    
    private $soft_delete_value = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'setFlagOnDelete']);

        $this->setRegistered(true);
        return $this;
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
    public function setFlagOnDelete(OnBeforeDeleteDataEvent $event)
    {
        if ($this->isDisabled())
            return;
            
        $eventData = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $eventData->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

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
            $updateData->dataUpdate(false, $transaction);
            $eventData->setCounterForRowsInDataSource($updateData->countRowsInDataSource());
        }
            
        // also update the original data sheet for further use
        if ($eventData->isEmpty() === false && $deletedColInEventData = $eventData->getColumns()->getByAttribute($this->getSoftDeleteAttribute())){
            $deletedColInEventData->setValueOnAllRows($this->getSoftDeleteValue());
        }

        return;
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
            throw new BehaviorConfigurationError($this->getObject(), 'Configuration error: no attribute ' . $value . 'found in object ' . $this->getObject()->getAlias() . '.');
        }
        return $this;
    }
        
    /**
     * 
     * @return MetaAttributeInterface
     */
    protected  function getSoftDeleteAttribute() : MetaAttributeInterface
    {
        try {
            return $this->getObject()->getAttribute($this->getSoftDeleteAttributeAlias()); 
        } catch (MetaAttributeNotFoundError $e) {
            throw new BehaviorConfigurationError($this->getObject(), 'Configuration error: no attribute "' . $this->getSoftDeleteAttributeAlias() . '" found in object "' . $this->getObject()->getAlias() . '".');
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function getSoftDeleteValue() : string
    {
        return $this->soft_delete_value;
    }
    
    /**
     * Value, which should be filled into the flag attribute.
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
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('soft_delete_attribute_alias', $this->getSoftDeleteAttributeAlias());
        $uxon->setProperty('soft_delete_value', $this->getSoftDeleteValue());
        return $uxon;
    }
}