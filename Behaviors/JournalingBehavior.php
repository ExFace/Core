<?php
namespace exface\Core\Behaviors;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Actions\CreateData;
use exface\Core\CommonLogic\Traits\ICanBypassDataAuthorizationTrait;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;

/**
 * Automatically creates entries in a "journal" object when things change in the main object.
 * 
 * Journal entries can contain any information from the original object, so you can save important
 * information from the time when the journal was written. For example, you can save the 
 * value of the state of the main object in every journal entry.
 * 
 * Journal entries are created upon creation of the behaviors object and on every update on it.
 * You can change this logic to write entries only if at least one of the listed "important"
 * attributes change using `save_if_attributes_change`. You can also skip the initial entry
 * by turning off `save_on_create`.
 * 
 * ## Transaction handling
 * 
 * Journal entries are written within the same transaction as the original operation. This means,
 * if something goes wrong and the original object will not be saved, there will also be no
 * journal entries. 
 * 
 * However, this also means, that any errors occurring while saving journal entries will prevent
 * the original operation too!
 * 
 * ## Permissions and data authorization
 * 
 * This behavior will bypass data authoriaztion policies by default. This ensures, that journaling
 * entries are properly written even if the current user does not have access to the journal object
 * or can only see subsets of its data.
 * 
 * If for any reason this is unwanted, use `bypass_data_authorization_point` to change this behavior.
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
    use ICanBypassDataAuthorizationTrait;

    private $journalRelationAlias = null;
    
    private $journalRelation = null;
    
    private $saveIfAttributesChange = [];
    
    private $saveAttributesMappingsUxon = null;
    
    private $saveOnCreate = true;
    
    private $onUpdateBehavior = null;
    
    private $onCreateBehavior = null;
    
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
                "name" => $this->getName() . ' (autom. generated from ' . $this->getAlias() . ')',
                "event_alias" => OnUpdateDataEvent::getEventName(),
                "only_if_attributes_change" => $this->getSaveIfAttributesChange(),
                "bypass_data_authorization_point" => $this->willBypassDataAuthorizationPoint() ?? true,
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
        
        if ($this->getSaveOnCreate()) {
            if ($this->onCreateBehavior === null) {
                $colMappings = $this->getSaveAttributesMappingsUxon();
                $thisObjKeyAlias = $this->getRelationToJournal()->getLeftKeyAttribute()->getAlias();
                $journalKeyAlias = $this->getRelationToJournal()->getRightKeyAttribute()->getAlias();
                $colMappings->append(new UxonObject([
                    'from' => $thisObjKeyAlias,
                    'to' => $journalKeyAlias
                ]));
                $uxon = new UxonObject([
                    "name" => $this->getName() . ' (autom. generated from ' . $this->getAlias() . ')',
                    "event_alias" => OnCreateDataEvent::getEventName(),
                    "only_if_attributes_change" => $this->getSaveIfAttributesChange(),
                    "bypass_data_authorization_point" => $this->willBypassDataAuthorizationPoint() ?? true,
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
                $this->onCreateBehavior = BehaviorFactory::createFromUxon($this->getObject(), CallActionBehavior::class, $uxon);
                $this->getObject()->getBehaviors()->add($this->onCreateBehavior);
            } else {
                $this->onCreateBehavior->enable();
            }
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
        if ($this->onCreateBehavior !== null) {
            $this->onCreateBehavior->disable();
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
    /**
     * @return boolean
     */
    protected function getSaveOnCreate() : bool
    {
        return $this->saveOnCreate;
    }

    /**
     * Set false to not create a journal entry on creation of e new entry.
     * 
     * @uxon-property save_on_create
     * @uxon-type bool
     * @uxon-default true
     * 
     * @param trueOrFalse
     * @return JournalingBehavior
     */
    public function setSaveOnCreate(bool $trueOrFalse) : JournalingBehavior
    {
        $this->saveOnCreate = $trueOrFalse;
        return $this;
    }

}