<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\CommonLogic\DataSheets\DataSheet;
use MongoDB\BSON\Undefined;
use exface\Core\Actions\UxonAutosuggest;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use Ramsey\Uuid\Type\Integer;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;

/**
 * This behavior auto sorts the given object
 *
 * @author Miriam Seitz
 *
 */
class OrderingBehavior extends AbstractBehavior
{
    private $closeGaps = true;
    private $working = false;
    
    private $indexAttributeAlias = null;
    private $boundaryAttributeAliases = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $handler = array(
            $this,
            'handleEvent'
        );
        $prio = $this->getPriority();
        $this->getWorkbench()->eventManager()->addListener(OnUpdateDataEvent::getEventName(), $handler, $prio);
        $this->getWorkbench()->eventManager()->addListener(OnCreateDataEvent::getEventName(), $handler, $prio);
        $this->getWorkbench()->eventManager()->addListener(OnDeleteDataEvent::getEventName(), $handler, $prio);
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $handler = array(
            $this,
            'handleEvent'
        );
        $this->getWorkbench()->eventManager()->removeListener(OnUpdateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->removeListener(OnCreateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->removeListener(OnDeleteDataEvent::getEventName(), $handler);
        
        return $this;
    }
    
    /**
     *
     * @param DataSheetEventInterface $event
     */
    public function handleEvent(DataSheetEventInterface $event)
    {
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        if ($this->working === true){
            return;
        }
        
        $this->working = true;
        $sheet = $event->getDataSheet();
        
        foreach ($this->getBoundaryAttributesAliases() as $boundaryAttributeAlias){
            foreach ($sheet->getRows() as $row){
                $indexAttributeAlias = $this->getIndexAttributeAlias();
                
                // load all siblings for a page
                $indexSheet = $this->createEmptyCopyWithIndexAttribute($sheet, $indexAttributeAlias);
                $indexSheet->getColumns()->addFromExpression($boundaryAttributeAlias);
                $indexSheet->getFilters()->addConditionFromString(
                    $boundaryAttributeAlias, 
                    $row[$boundaryAttributeAlias], 
                    ComparatorDataType::IN);   
                $indexSheet->getSorters()->addFromString($indexAttributeAlias, SortingDirectionsDataType::ASC);
                $indexSheet->dataRead();
                
                $indexAttributeDataType = $sheet->getMetaObject()->getAttribute($indexAttributeAlias)->getDataType();
                switch($indexAttributeDataType->getAlias()){
                    case 'Integer':
                        $lastIndex = null;
                        $updateIndex = $this->createEmptyCopyWithIndexAttribute($sheet, $indexAttributeAlias);
                        foreach ($indexSheet->getRows() as $currentRow){
                            $currentIndex = $currentRow[$indexAttributeAlias];
                            $updateNeeded = false;
                            
                            if ($currentIndex === null && $this->closeGaps){
                                $updateNeeded = true;
                                $currentIndex = $lastIndex === null ? 0 : $lastIndex+1;
                            }
                            else if ($currentIndex <= $lastIndex){
                                $updateNeeded = true;
                                $currentIndex = $lastIndex+1;
                            }
                            else if ($currentIndex > $lastIndex+1 && $this->closeGaps){
                                $updateNeeded = true;
                                $currentIndex = $lastIndex+1;
                            }
                            
                            if ($updateNeeded){
                                $rowWithUpdatedValue = $currentRow;
                                $rowWithUpdatedValue[$indexAttributeAlias] = $currentIndex;
                                $updateIndex->addRow($rowWithUpdatedValue);
                            }
                            
                            $lastIndex = $currentIndex;
                        }
                        
                        $updateIndex->dataUpdate(false, $event->getTransaction());
                        break;
                    default:
                        throw new BehaviorConfigurationError(
                            $this, 'Datatype of ordering attribute \'' . $indexAttributeAlias . '\' not supported!');
                        break;                    
                }                
            }
        }
        
        $this->working = false;        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this));      
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this));
    }
    
    private function createEmptyCopyWithIndexAttribute(
        DataSheetInterface $sheet, 
        string $indexAttributeAlias) : DataSheetInterface 
    {
        $newSheet = DataSheetFactory::createFromObject($sheet->getMetaObject());
        $newSheet->getColumns()->addFromSystemAttributes();
        $newSheet->getColumns()->addFromExpression($indexAttributeAlias);
        return $newSheet;
    }
    
    /**
     * Set to FALSE to disable reindexing when a new item is inserted
     *
     * @uxon-property close_gaps
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $trueOrFalse
     * @return OrderingBehavior
     */
    protected function setCloseGaps(bool $trueOrFalse) : OrderingBehavior
    {
        $this->closeGaps = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    protected function getCloseGaps() : bool
    {
        return $this->closeGaps;
    }
    
    /**
     *
     * @return array
     */
    protected function getBoundaryAttributesAliases() : array
    {
        return $this->boundaryAttributeAliases;
    }
    
    /**
     * Aliases of attributes that define an index - e.g. a folder to be indexed
     *
     * @uxon-property indexing_boundary_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param UxonObject $value
     * @return OrderingBehavior
     */
    protected function setIndexingBoundaryAttributes(UxonObject $value) : OrderingBehavior
    {
        $this->boundaryAttributeAliases = $value->toArray();
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getIndexAttributeAlias() : string 
    {
        return $this->indexAttributeAlias;
    }    
    
    /**
     * Alias of index attribute to order
     *
     * @uxon-property order_index_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return OrderingBehavior
     */
    protected function setOrderIndexAttribute(string $value) : OrderingBehavior
    {
        $this->indexAttributeAlias = $value;
        return $this;
    }
}