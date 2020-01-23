<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataColumn;

class SoftDeleteBehavior extends AbstractBehavior
{
    private $soft_delete_attribute_alias = null;
    
    public function register() : BehaviorInterface
    {
        $this->getSoftDeleteAttribute()->setSystem(true)->setDefaultAggregateFunction('MAX');
       
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'setFlagOnDelete']);

        $this->setRegistered(true);
        return $this;
    }
    
    public function setFlagOnDelete(OnBeforeDeleteDataEvent $event)
    {
        if ($this->isDisabled())
            return;
            
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        // Check if the updated_on column is present in the sheet 
        if (! $data_sheet->getColumns()->getByAttribute($this->getSoftDeleteAttribute())){
            throw new DataSheetColumnNotFoundError($data_sheet, 'Cannot set "IsDeleted" flag for current object: column "' . $this->getSoftDeleteAttributeAlias() . '" not found in given data sheet!');
        } 

        $event->preventDelete();
        
        $data_sheet->setColumnValues($this->getSoftDeleteAttributeAlias(), true);
        $data_sheet->dataUpdate(false);
        
    }
    
    /**
     * 
     * @return string
     */
    public function getSoftDeleteAttributeAlias() 
    {
        return $this->soft_delete_attribute_alias;
    }
    
    /**
     * Alias of the attribute, where the deletion flag is being set.
     *
     * @uxon-property soft_delete_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return \exface\Core\Behaviors\SoftDeleteBehavior
     */
    public function setSoftDeleteAttributeAlias(string $value) : SoftDeleteBehavior
    {
        $this->soft_delete_attribute_alias = $value;
        return $this;
    }
    
//     public function importUxonObject(UxonObject $uxon, array $skip_property_names = [])
//     {
        
//     }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getSoftDeleteAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->getSoftDeleteAttributeAlias()); 
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
            $check_sheet->addFilterFromColumnValues($originalSheet->getUidColumn());
            $check_sheet->dataRead();
        }
        return $check_sheet;
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
        return $uxon;
    }
}