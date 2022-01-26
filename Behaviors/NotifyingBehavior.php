<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Communication\Messages\NotificationMessage;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Factories\CommunicationChannelFactory;
use exface\Core\CommonLogic\Communication\Envelope;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;

/**
 * Creates user-notifications on certain conditions.
 * 
 * BETA: This behavior is not yet fully functional. Some features may not work correctly!
 * 
 * @author Andrej Kabachnik
 *
 */
class NotifyingBehavior extends AbstractBehavior
{
    private $notifyOnChangeOfAttributes = [];
    
    private $notifyOnCreate = false;
    
    private $notifyIf = null;
    
    private $notifyUsers = [];
    
    private $notifyUserFromAttribute = null;
    
    private $notifyRoles = [];
    
    private $notifyRoleFromAttribute = null;
    
    private $messages = null;
    
    private $channelAlias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
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
        $dispatcher = $this->getWorkbench()->eventManager();
        if ($this->getNotifyOnCreate()) {
            $dispatcher->addListener(OnCreateDataEvent::getEventName(), [$this, 'onCreateNotify']);
        }
        if ($this->getNotifyOnUpdate()) {
            $dispatcher->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckNotificationNeeded']);
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
        $this->getWorkbench()->eventManager()
            ->removeListener(OnCreateDataEvent::getEventName(), [$this, 'onCreateNotify'])
            ->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckNotificationNeeded'])
            ->removeListener(OnUpdateDataEvent::getEventName(), [$this, 'onUpdateNotify']);
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
        return $uxon;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @return void
     */
    public function onCreateNotify(OnCreateDataEvent $event)
    {
        $dataSheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $dataSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $this->notify($dataSheet);
        return;
    }
    
    public function onBeforeUpdateCheckNotificationNeeded(OnBeforeUpdateDataEvent $event)
    {
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
    }
    
    /**
     *
     * @param OnBeforeCreateDataEvent $event
     * @return void
     */
    public function onUpdateNotify(OnUpdateDataEvent $event)
    {
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return NotificationMessage[]
     */
    protected function notify(DataSheetInterface $dataSheet)
    {
        foreach ($this->getMessagesUxon() as $msgUxon) {
            $channelAlias = $msgUxon->getProperty('channel_alias');
            $msgUxon->unsetProperty('channel_alias');
            
            $json = $msgUxon->toJson();
            foreach (array_keys($dataSheet->getRows()) as $rowNo) {
                $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
                $renderer->addPlaceholder(new DataRowPlaceholders($dataSheet, $rowNo));
                $renderedUxon = UxonObject::fromJson($renderer->render($json));
                $envelope = new Envelope($renderedUxon, new CommunicationChannelSelector($this->getWorkbench(), $channelAlias));
                $this->getWorkbench()->getCommunicator()->send($envelope);
            }
        }
        
        return;
    }
    
    public function getNotifyOnCreate() : bool
    {
        return $this->notifyOnCreate;
    }
    
    public function getNotifyOnUpdate() : bool
    {
        return empty($this->getNotifyOnChangeOfAttributeAliases()) === false;
    }
    
    /**
     * Set to TRUE to send notifications when new instance of the object are created.
     * 
     * @uxon-property notify_on_create
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return NotifyingBehavior
     */
    public function setNotifyOnCreate(bool $value) : NotifyingBehavior
    {
        $this->notifyOnCreate = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getNotifyOnChangeOfAttributeAliases() : array
    {
        return $this->notifyOnChangeOfAttributes;
    }
    
    /**
     * Only send notifications if at least one of these attributes attributes changes.
     * 
     * @uxon-property notify_on_change_of_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject $uxonArray
     * @return NotifyingBehavior
     */
    public function setNotifyOnChangeOfAttributes(UxonObject $uxonArray) : NotifyingBehavior
    {
        $this->notifyOnChangeOfAttributes = $uxonArray->toArray();
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getNotifyUsernames() : array
    {
        return $this->notifyUsers;
    }
    
    /**
     * Notify users specified in this array of user names
     * 
     * @uxon-property notify_users
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $uxonArray
     * @return NotifyingBehavior
     */
    public function setNotifyUsers(UxonObject $uxonArray) : NotifyingBehavior
    {
        $this->notifyUsers = $uxonArray->toArray();
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getNotifyUserRoleAliases() : array
    {
        return $this->notifyRoles;
    }
    
    /**
     * Notify users with roles from this array of role aliases (with namespaces)
     * 
     * @uxon-property notify_user_roles
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $uxonArray
     * @return NotifyingBehavior
     */
    public function setNotifyUserRoles(UxonObject $uxonArray) : NotifyingBehavior
    {
        $this->notifyRoles = $uxonArray->toArray();
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getNotifyUserFromAttributeAlias() : ?string
    {
        return $this->notifyUserFromAttribute;
    }
    
    /**
     * Notify user related to the behaviors object
     * 
     * @uxon-property notify_user_from_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return NotifyingBehavior
     */
    public function setNotifyUserFromAttribute(string $value) : NotifyingBehavior
    {
        $this->notifyUserFromAttribute = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getNotifyUserRoleFromAttributeAlias() : ?string
    {
        return $this->notifyRoleFromAttribute;
    }
    
    /**
     * Notify users with the role related to the behaviors object
     * 
     * @uxon-property notify_user_role_from_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return NotifyingBehavior
     */
    public function setNotifyUserRoleFromAttribute(string $value) : NotifyingBehavior
    {
        $this->notifyRoleFromAttribute = $value;
        return $this;
    }
    
    /**
     * 
     * @return ConditionGroupInterface|NULL
     */
    public function getNotifyIf() : ?ConditionGroupInterface
    {
        return $this->notifyIf;
    }
    
    /**
     * Only send notification for data rows matching these conditions.
     * 
     * @uxon-property notify_if
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}
     *
     * @param UxonObject $conditionGroup
     * @return NotifyingBehavior
     */
    public function setNotifyIf(UxonObject $conditionGroup) : NotifyingBehavior
    {
        $this->notifyIf = $conditionGroup;
        return $this;
    }
    
    public function getMessagesUxon() : UxonObject
    {
        return $this->messages;
    }
    
    protected function setMessages(UxonObject $array) : NotifyingBehavior
    {
        $this->messages = $array;
        return $this;
    }
    
    /**
     * The notification to show
     * 
     * @uxon-property notification
     * @uxon-type \exface\Core\Communication\Messages\NotificationMessage
     * @uxon-template {"title": "", "body": "", "buttons": [{"caption": "", "action": {"alias": "", "object_alias": ""}}]}
     * 
     * @param UxonObject $value
     * @return NotifyingBehavior
     */
    protected function setNotification(UxonObject $value) : NotifyingBehavior
    {
        // TODO remove
        return $this;
    }
    
    protected function getCommunicationChannelAlias() : string
    {
        return $this->channelAlias;
    }
    
    public function setCommunicationChannel(string $value) : NotifyingBehavior
    {
        $this->channelAlias = $value;
        return $this;
    }
}