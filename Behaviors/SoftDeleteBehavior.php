<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\Model\MetaObjectHasNoDataSourceError;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Exceptions\DataSheets\DataSheetWriteError;

class SoftDeleteBehavior extends AbstractBehavior
{
    private $soft_delete_attribute_alias = null;
    
    private $soft_delete_value = null;
    
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
            
        $eventData = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $eventData->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        $event->preventDelete();

        $transaction = $event->getTransaction();

        $affected_rows = 0;

        $updateSheet = $eventData->copy();
        
        if ($updateSheet->hasUidColumn()){
            $uidCol = $updateSheet->getUidColumn();
        }
        
        foreach ($updateSheet->getColumns() as $col){
            if ($col === $uidCol){
                continue;
            }
            $updateSheet->getColumns()->remove($col);
        }
        
        $updateSheet->getColumns()->addFromExpression($this->getSoftDeleteAttributeAlias());

        try {
            if ($eventData->getMetaObject()->hasAttribute($this->getSoftDeleteAttributeAlias())){
                $updateSheet->dataRead();
                if ($deletedCol = $updateSheet->getColumns()->getByExpression($this->getSoftDeleteAttributeAlias())){
                    $deletedCol->setValueOnAllRows($this->getSoftDeleteValue());
                }
                $updatedRows = $updateSheet->dataUpdate(false);
                $affected_rows += $updatedRows;
            } else {
                $transaction->rollback();
                throw new DataSheetColumnNotFoundError($eventData, 'Cannot set "IsDeleted" flag for current object: column "' . $this->getSoftDeleteAttributeAlias() . '" not found in given data sheet!');                
            }

            $eventData->setCounterForRowsInDataSource($updateSheet->countRowsInDataSource());
            
        } catch (\Throwable $e) {
            $transaction->rollback();
            throw new DataSheetWriteError($eventData, 'Data source error. ' . $e->getMessage(), null, $e);
        }
        if ($eventData->isEmpty() === false){
            if ($deletedCol = $eventData->getColumns()->getByExpression($this->getSoftDeleteAttributeAlias())){
                $deletedCol->setValueOnAllRows($this->getSoftDeleteValue());
            }
        }
                
//        $this->getWorkbench()->eventManager()->dispatch(new OnDeleteDataEvent($eventData, $transaction));
        
        $transaction->commit();
        
        return $affected_rows;
        
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
     * @uxon-required true
     *
     * @param string $value
     * @return \exface\Core\Behaviors\SoftDeleteBehavior
     */
    public function setSoftDeleteAttributeAlias(string $value) : SoftDeleteBehavior
    {
        $this->soft_delete_attribute_alias = $value;
        return $this;
    }
        
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
        $uxon->setProperty('soft_delete_value', $this->getSoftDeleteValue);
        return $uxon;
    }
}