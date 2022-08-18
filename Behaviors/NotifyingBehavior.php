<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Communication\Messages\NotificationMessage;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\CommonLogic\Traits\SendMessagesFromDataTrait;
use exface\Core\Templates\Placeholders\ExcludedPlaceholders;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;

/**
 * Creates user-notifications on certain events and conditions.
 * 
 * Each behavior instance can be configured to send notifications on a specific event
 * by setting the mandatory `notify_on` option. Additionally other `notify_*` options
 * can be used to introduce further conditions. If you need notifications on multiple
 * events - create multiple behaviors for the object.
 * 
 * ## Notifications and placeholders
 * 
 * Each behavior instance can send multiple `notifications` through different communication
 * channels. The available configuration options for each notification depend on the message
 * type of the selected channel.
 * 
 * In any case, the contents of the notificaionts can contain the follwing placeholders
 * at any position (see `notifications` property for more details):
 * 
 * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
 * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key` 
 * from the given app
 * - `[#~data:column_name#]` - will be replaced by the value from `column_name` of the data sheet,
 * for which the notification was triggered - only works with notification on data sheet events!
 * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
 * This means, static formulas will always work, while data-driven formulas will only work on data sheet
 * events!
 * 
 * ## Notify on certain conditions only
 * 
 * You can make the behavior send notifications on certain conditions only:
 * 
 * - `notify_if_attributes_change` - will only send a notification if one of these attribtues 
 * is changed
 * - `notify_if_data_matches_conditions` - will only send notifications if the `notify_on_event` 
 * contains data and that data matches the provided conditions. In case only some of the data 
 * rows match the conditions, notifications will be sent for these rows only!
 * 
 * ## Examples
 * 
 * ### Send an in-app notification to a user role every time a task is created
 * 
 * ```
 *  {
 *      "notify_on_event": "exface.Core.DataSheet.OnCreateData",
 *      "notifications": [
 *          {
 *              "channel": "exface.Core.NOTIFICATION",
 *              "recipient_roles": ["exface.Core.ADMINISTRATOR"],
 *              "title": "New ticket: [#ticket_title#]",
 *              "text": "A new ticket has been created!",
 *              "icon": "ticket"
 *          }
 *      ]
 *  }
 * 
 * ```
 * 
 * ### Send an in-app notification to a user every time an action is performed
 * 
 * ```
 *  {
 *      "notify_on_action": "exface.Core.CommunicationChannelMute",
 *      "notifications": [
 *          {
 *              "channel": "exface.Core.NOTIFICATION",
 *              "recipient_users": ["username"],
 *              "title": "Channel [#~data:LABEL#] muted!",
 *              "text": "User [#=User('username')#] just muted the communication channel [#~data:LABEL#]"
 *          }
 *      ]
 *  }
 * 
 * ```
 * 
 * ### Send an email to the ticket author once it reaches a certain status
 * 
 * ```
 *  {
 *      "notify_on_event": "exface.Core.DataSheet.OnUpdateData",
 *      "notify_if_attributes_change": [
 *          "status"
 *      ],
 *      "notify_if_data_matches_conditions": [{
 *          "operator": "AND",
 *          "conditions": ["value_left": "status", "comparator": "==", "value_right": 60]
 *      }],
 *      "notifications": [
 *          {
 *              "channel": "exface.Core.EMAIL",
 *              "recipient_users": ["[#creator_user__username#]"],
 *              "subject": "Your ticket [#id#] requires feedback",
 *              "text": "Your ticket [#id#] \"[#ticket_title#]\" is awaiting your feedback"
 *          }
 *      ]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class NotifyingBehavior extends AbstractBehavior
{
    use SendMessagesFromDataTrait;
    
    private $notifyOnEventName = null;
        
    private $notifyIfAttributesChange = null;
    
    private $notifyIfDataMatchesConditionGroupUxon = null;
    
    private $ignoreDataSheets = [];
    
    private $errorIfNotSent = false;
    
    private $notifyOnActionAlias = null;
    
    private $useActionInputData = false;
    
    private $messageUxons = null;
    
    /**
     * Array of messages to send - each with a separate message model: channel, recipients, etc.
     *
     * You can use the following placeholders inside any message model - as recipient,
     * message subject - anywhere:
     *
     * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
     * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key`
     * from the given app
     * - `[#~data:column_name#]` - will be replaced by the value from `column_name` of the data sheet,
     * for which the notification was triggered - only works with notification that have data sheets present!
     * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
     * This means, static formulas will always work, while data-driven formulas will only work on notifications
     * that have data sheets present!
     *
     * @uxon-property notifications
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"channel": ""}]
     *
     * @param UxonObject $arrayOfMessages
     * @return NotifyingBehavior
     */
    public function setNotifications(UxonObject $arrayOfMessages) : NotifyingBehavior
    {
        $this->messageUxons = $arrayOfMessages;
        return $this;
    }
    
    /**
     * Same as `notifications` - just to allow similar syntax as in `SendMessage` action, etc.
     * 
     * @uxon-property messages
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"channel": ""}]
     * 
     * @param UxonObject $arrayOfMessages
     * @return NotifyingBehavior
     */
    protected function setMessages(UxonObject $arrayOfMessages) : NotifyingBehavior
    {
        return $this->setNotifications($arrayOfMessages);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon, array $skip_property_names = array())
    {
        //skip import of `disabled` property because it depends on `notify_on_event` being set
        //so we import it after all other properties got imported
        parent::importUxonObject($uxon, ['disabled']);
        if ($uxon->hasProperty('disabled')) {
            $this->setDisabled($uxon->getProperty('disabled'));
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        // Register the change-check listener first to make sure it is called before the
        // call-action listener even if both listen the OnBeforeUpdateData event
        if ($this->hasRestrictionOnAttributeChange()) {
            $this->getWorkbench()->eventManager()
                ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange']);
        }
        
        $this->getWorkbench()->eventManager()
            ->addListener($this->getNotifyOnEventName(), [$this, 'onEventNotify']);
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        if ($this->hasRestrictionOnAttributeChange()) {
            $this->getWorkbench()->eventManager()
                ->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange']);
        }
        $this->getWorkbench()->eventManager()
            ->removeListener($this->getNotifyOnEventName(), [$this, 'onEventNotify']);
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
        // TODO
        $uxon = parent::exportUxonObject();
        return $uxon;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return NotificationMessage[]
     */
    public function onEventNotify(EventInterface $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        // Ignore object-events where the object does not match
        if (($event instanceof MetaObjectEventInterface) && ! $event->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        // Ignore data-events if their data is based on another object
        if (($event instanceof DataSheetEventInterface) && ! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        // Ignore action-events if 
        // - the behavior is targeting a specific action and that is NOT the event-action
        // - or the behavior is targeting an action event regardless of the action, but the actions object does not match
        if ($event instanceof ActionEventInterface) {
            if ($this->getNotifyOnActionAlias() !== null){
                if (! $event->getAction()->isExactly($this->getNotifyOnActionAlias())) {
                    return;
                }
            } else {
                if (! $event->getAction()->getMetaObject()->isExactly($this->getObject())) {
                    return;
                }
            }
        }
        
        // Here is a possibility to add custom placeholder resolvers depending on the event type:
        // just instantiate the resolver and add it to this array.
        $phResolvers = [];
        
        $dataSheet = null;
        switch (true) {
            // For data-events, use their data obviously
            case $event instanceof DataSheetEventInterface:
                $dataSheet = $event->getDataSheet();
                break;
            // For action-events, use their input data as object-restrictions will be probably expected to apply to input data:
            // e.g. notify_on_action on object XYZ obviously means "if action performed upon object XYZ", not "if action produces
            // object XYZ"
            case $event instanceof OnActionPerformedEvent:
            case $event instanceof OnBeforeActionPerformedEvent:
                // TODO getting data from action events is not straight-forward: we can either use input or result data (or both?)
                // Maybe add additional placeholders to $phResolvers for `input_data:` and `result_data`? But the $phResolvers are
                // currently applied to the entire config, not each data row... -> allow two additional arrays?
                $dataSheet = $event->getActionInputData();
                break;          
        }
        
        // Don't send anything if the event has data restrictions, but no data!
        if (! $dataSheet && ($this->hasRestrictionConditions() || $this->hasRestrictionOnAttributeChange())) {
            $this->getWorkbench()->getLogger()->debug('Behavior ' . $this->getAlias() . ' skipped for object ' . $this->getObject()->__toString() . ' because `notify_if_data_matches_conditions` or `notify_if_attributes_change` is set, but the event "' . $event->getAliasWithNamespace() . '" does not contain any data!', [], $dataSheet);
            return;
        }
        
        // Ignore the event if the data is based on another object
        if ($dataSheet && ! $dataSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        // Ignore the event if its data was already processed and set to be ignored (e.g. required change did not happen)
        if ($dataSheet && in_array($dataSheet, $this->ignoreDataSheets)) {
            $this->getWorkbench()->getLogger()->debug('Behavior ' . $this->getAlias() . ' skipped for object ' . $this->getObject()->__toString() . ' because of `notify_if_attributes_change`', [], $dataSheet);
            return;
        }
        
        // Ignore the event if its data does not match restrictions
        if ($dataSheet && $this->hasRestrictionConditions()) {
            $dataSheet = $dataSheet->extract($this->getNotifyIfDataMatchesConditions(), true);
            if ($dataSheet->isEmpty()) {
                $this->getWorkbench()->getLogger()->debug('Behavior ' . $this->getAlias() . ' skipped for object ' . $this->getObject()->__toString() . ' because of `notify_if_data_matches_conditions`', [], $dataSheet);
                return;
            }
        }        
        
        // If everything is OK, generate UXON envelopes for the messages and send them
        try {
            $communicator = $this->getWorkbench()->getCommunicator();
            foreach ($this->getMessageEnvelopes(
                ($this->messageUxons ?? new UxonObject()), 
                $dataSheet, 
                $phResolvers
            ) as $envelope) {
                $communicator->send($envelope);
            }
        } catch (\Throwable $e) {
            if (($e instanceof CommunicationExceptionInterface) || $envelope === null) {
                $sendingError = $e;
            } else {
                $sendingError = new CommunicationNotSentError($envelope, 'Cannot send notification: ' . $e->getMessage(), null, $e);
            }
            if ($this->isErrorIfNotSent() === false) {
                $this->getWorkbench()->getLogger()->logException($sendingError);
            } else {
                throw $sendingError;
            }
        }
        
        return;
    }
    
    /**
     * Checks if any of the `notify_if_attribtues_change` attributes are about to change
     *
     * @param OnBeforeUpdateDataEvent $event
     * @return void
     */
    public function onBeforeUpdateCheckChange(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $ignore = true;
        foreach ($this->getNotifyIfAttributesChange() as $attrAlias) {
            if ($event->willChangeColumn(DataColumn::sanitizeColumnName($attrAlias))) {
                $ignore = false;
                break;
            }
        }
        if ($ignore === true) {
            $this->ignoreDataSheets[] = $event->getDataSheet();
        }
    }
    
    protected function getNotifyOnEventName() : string
    {
        return $this->notifyOnEventName;
    }
    
    /**
     * The alias of the event that should trigger the notification
     * 
     * @uxon-property notify_on_event
     * @uxon-type metamodel:event
     * @uxon-required true
     * 
     * @param string $value
     * @return NotifyingBehavior
     */
    public function setNotifyOnEvent(string $value) : NotifyingBehavior
    {
        $this->notifyOnEventName = $value;
        return $this;
    }

    
    
    protected function getNotifyIfAttributesChange() : array
    {
        return $this->notifyIfAttributesChange ?? [];
    }
    
    /**
     * Only call the action if any of these attributes change (list of aliases)
     *
     * @uxon-property notify_if_attributes_change
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param UxonObject $value
     * @return NotifyingBehavior
     */
    protected function setNotifyIfAttributesChange(UxonObject $value) : NotifyingBehavior
    {
        $this->notifyIfAttributesChange = $value->toArray();
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    protected function hasRestrictionOnAttributeChange() : bool
    {
        return $this->notifyIfAttributesChange !== null;
    }
    
    /**
     *
     * @return bool
     */
    protected function hasRestrictionConditions() : bool
    {
        return $this->notifyIfDataMatchesConditionGroupUxon !== null;
    }
    
    /**
     *
     * @return ConditionGroupInterface|NULL
     */
    protected function getNotifyIfDataMatchesConditions() : ?ConditionGroupInterface
    {
        if ($this->notifyIfDataMatchesConditionGroupUxon === null) {
            return null;
        }
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->notifyIfDataMatchesConditionGroupUxon, $this->getObject());
    }
    
    /**
     * Only call the action if it's input data would match these conditions
     *
     * @uxon-property notify_if_data_matches_conditions
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "=","value": ""}]}
     *
     * @param UxonObject $uxon
     * @return NotifyingBehavior
     */
    protected function setNotifyIfDataMatchesConditions(UxonObject $uxon) : NotifyingBehavior
    {
        $this->notifyIfDataMatchesConditionGroupUxon = $uxon;
        return $this;
    }
    
    protected function isErrorIfNotSent() : bool
    {
        return $this->errorIfNotSent;
    }
    
    /**
     * Set to TRUE to produce errors if at least one notification cannot be sent (will prevent saving data!!!)
     * 
     * @uxon-property error_if_not_sent
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return NotifyingBehavior
     */
    protected function setErrorIfNotSent(bool $value) : NotifyingBehavior
    {
        $this->errorIfNotSent = $value;
        return $this;
    }
    
    /**
     * If set, only successfully performing this specific action will trigger the notifications.
     * 
     * In a sense this is an alternative to `notify_on_event`, which reacts to all sorts of events. Using
     * `notify_on_action` you can send notification only when specific actions are performed successfully.
     * 
     * There is no need to set `notify_on_event` together with `notify_on_action`, however, you may want
     * to combine the two options to send notification `OnBeforeActionPerformed` or in other very special
     * cases.
     *
     * @uxon-property notify_on_action
     * @uxon-type metamodel:action
     *
     * @param string $value
     * @return NotifyingBehavior
     */
    public function setNotifyOnAction(string $value) : NotifyingBehavior
    {
        $this->notifyOnActionAlias = $value === '' ? null : $value;
        if ($this->notifyOnEventName === null) {
            $this->notifyOnEventName = OnActionPerformedEvent::getEventName();
        }
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getNotifyOnActionAlias() : ?string
    {
        return $this->notifyOnActionAlias;
    }
    
    /**
     * Set to TRUE to use the action input data to check against the data matches conditions
     *
     * @uxon-property use_action_input_data
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return NotifyingBehavior
     */
    protected function setUseActionInputData(bool $value) : NotifyingBehavior
    {
        $this->useActionInputData = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getUseInputData() : bool
    {
        return $this->useActionInputData;
    }
}