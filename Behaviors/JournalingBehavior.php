<?php
namespace exface\Core\Behaviors;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Actions\CreateData;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;

/**
 * Automatically creates entries in a "journal" object when things change in the main object
 * 
 * ## Examples
 * 
 * ### Log status changes
 * 
 * This will create a journal entry every time the `status` attribute of the object is changed.
 * The new value of the status will be saved in the `status` attribute of the journal object,
 * so the journal will state: status changed to X at a certain time by a certain user (provided
 * time and user are always saved using the `TimeStampingBehavior` or similar).
 * 
 * NOTE: the journal must have an n-to-1 relation to the main object of course, so each journal
 * entry "knows" its original object! 
 * 
 * ```
 *  {
 *      "relation_to_journal": "journal",
 *      "save_if_attributes_change": [
 *          "status"
 *      ],
 *      "save_attributes": [
 *          {"from": "status", "to": "status"}
 *      ]
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class JournalingBehavior extends AbstractBehavior
{    
    private $callActionBehavior = null;
    
    private $journalRelationAlias = null;
    
    private $journalRelation = null;
    
    private $saveIfAttributesChange = [];
    
    private $saveAttributesMappingsUxon = [];
    
    private $onUpdateBehavior = null;
    
    protected function registerEventListeners() : BehaviorInterface
    {
        if ($this->onUpdateBehavior === null) {
            $colMappings = $this->getSaveAttributesMappingsUxon();
            $thisObjKeyAlias = $this->getRelationToJournal()->getLeftKeyAttribute()->getAlias();
            $journalKeyAlias = $this->getRelationToJournal()->getRightKeyAttribute()->getAlias();
            $colMappings->append(new UxonObject([
                'from' => $thisObjKeyAlias,
                'to' => $journalKeyAlias
            ]));
            $uxon = new UxonObject([
                "event_alias" => OnUpdateDataEvent::getEventName(),
                "only_if_attributes_change" => $this->getSaveIfAttributesChange(),
                "action" => [
                    "alias" => CreateData::class,
                    "object_alias" => $this->getObjectOfJournal()->getAliasWithNamespace(),
                    "input_mapper" => [
                        "from_object_alias" => $this->getObject()->getAliasWithNamespace(),
                        "to_object_alias" => $this->getObjectOfJournal()->getAliasWithNamespace(),
                        "column_to_column_mappings" => $colMappings->toArray()
                    ]
                ]
            ]);
            $this->onUpdateBehavior = BehaviorFactory::createFromUxon($this->getObject(), CallActionBehavior::class, $uxon);
            $this->getObject()->getBehaviors()->add($this->onUpdateBehavior);   
        } else {
            $this->onUpdateBehavior->enable();
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
        if ($this->onUpdateBehavior !== null) {
            $this->onUpdateBehavior->disable();
        }
        return $this;
    }
    
    /**
     * 
     * @return MetaRelationInterface
     */
    protected function getRelationToJournal() : MetaRelationInterface
    {
        if ($this->journalRelation === null) {
            $this->journalRelation = $this->getObject()->getRelation($this->journalRelationAlias);
        }
        return $this->journalRelation;
    }
    
    /**
     * Relation path from the object of the behavior to the journal object
     * 
     * @uxon-property relation_to_journal
     * @uxon-type metamodel:relation
     * @uxon-required true
     * 
     * @param string $value
     * @return JournalingBehavior
     */
    protected function setRelationToJournal(string $value) : JournalingBehavior
    {
        $this->journalRelation = null;
        $this->journalRelationAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    protected function getObjectOfJournal() : MetaObjectInterface
    {
        return $this->getRelationToJournal()->getRightObject();
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getSaveIfAttributesChange() : array
    {
        return $this->saveIfAttributesChange;
    }
    
    /**
     * Create a journa entry every time at least one of these attributes change
     * 
     * @uxon-property save_if_attributes_change
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject $value
     * @return JournalingBehavior
     */
    protected function setSaveIfAttributesChange(UxonObject $value) : JournalingBehavior
    {
        $this->saveIfAttributesChange = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getSaveAttributesMappingsUxon() : UxonObject
    {
        return $this->saveAttributesMappingsUxon;
    }
    
    /**
     * Attributes to save to the journal
     * 
     * @uxon-property save_attributes
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @param UxonObject $value
     * @return JournalingBehavior
     */
    protected function setSaveAttributes(UxonObject $value) : JournalingBehavior
    {
        $this->saveAttributesMappingsUxon = $value;
        return $this;
    }
}