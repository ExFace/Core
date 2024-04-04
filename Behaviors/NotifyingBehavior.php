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
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Interfaces\Events\ActionRuntimeEventInterface;
use exface\Core\Communication\Messages\Envelope;
use exface\Core\Events\Workbench\OnBeforeStopEvent;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Sends communication messages (notifications, emails, chat posts, etc.) on certain events and conditions.
 * 
 * Each behavior instance can be configured to send messages on a specific event
 * or action by setting the `notify_on_event` or `notify_on_action` options. Other `notify_*` 
 * options can be used to add further conditions. 
 * 
 * **NOTE:** If the message is based on data (i.e. on data events or actions) and the reciepient
 * is a user, it will only be sent if this user is authorized to read the data. You can change
 * this via `notify_if_data_authorized`.
 * 
 * If you need notifications on multiple events - create multiple behaviors for the object.
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
 * Alternatively you can also save the message as a template in `Administration > Metamodel > Communication`
 * and reference it here like this:
 * 
 * ```
 *  {
 *      "notify_on_event": "exface.Core.DataSheet.OnCreateData",
 *      "notifications": [
 *          {
 *              "template": "my.App.template_alias"
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
    
    private $notifyIfDataAuthorized = true;
    
    private $ignoreDataSheets = [];
    
    private $errorIfNotSent = false;
    
    private $notifyOnActionAlias = null;
    
    private $useActionInputData = false;
    
    private $messageUxons = null;
    
    private $preventRecursion = false;
    
    private $notifyAfterAllActionsComplete = false;
    
    private $isNotificationInProgress = false;
    
    /**
     * Array of messages to send - each with a separate message model: channel, recipients, etc.
     *
     * You can either define a message here explicitly by setting the `channel`, etc., or
     * select a `template` and customize it if needed by overriding certain properties. Note, that
     * when using templates, proper autosuggest is only available if you set the channel explicitly
     * too. 
     *
     * The following placeholders can be used anywhere inside each message configuration: in `text`,
     * `recipients` - anywhere:
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
     * ## Examples
     * 
     * ### Send message using a template
     * 
     * ```
     *  {
     *      "notifications": [
     *          {
     *              "template": "my.App.template_alias"
     *          }
     *      ]
     *  }
     * 
     * ```
     * 
     * ### Send custom message without a template
     * 
     * ```
     *  {
     *      "notifications": [
     *          {
     *              "channel": "exface.Core.NOTIFICATION",
     *              "recipient_roles": ["exface.Core.ADMINISTRATOR"],
     *              "title": "New ticket: [#ticket_title#]",
     *              "text": "A new ticket has been created!"
     *          }
     *      ]
     *  }
     * 
     * ```
     *
     * @uxon-property notifications
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"": ""}]
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
     * @uxon-template [{"": ""}]
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
            ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange'], $this->getPriority());
        }
        
        $this->getWorkbench()->eventManager()
        ->addListener($this->getNotifyOnEventName(), [$this, 'onEventNotify'], $this->getPriority());
        
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
        if ($this->isDisabled() || ($this->isNotificationInProgress === true && $this->getPreventRecursion() === true)) {
            return;
        }
        
        // Track if this behavior is active already. This is important to prevent recursion.
        // Recursion would occur for example when notifying about errors if the notification itself produces
        // an error in-turn.
        $this->isNotificationInProgress = true;
        
        // Ignore object-events where the object does not match
        if (($event instanceof MetaObjectEventInterface) && ! $event->getMetaObject()->isExactly($this->getObject())) {
            $this->isNotificationInProgress = false;
            return;
        }
        
        // Ignore data-events if their data is based on another object
        if (($event instanceof DataSheetEventInterface) && ! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            $this->isNotificationInProgress = false;
            return;
        }
        
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->setIndentActive(1);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        
        // Ignore action-events if 
        // - the behavior is targeting a specific action and that is NOT the event-action
        // - or the behavior is targeting an action event regardless of the action, but the actions object does not match
        if ($event instanceof ActionEventInterface) {
            $action = $event->getAction();
            $logbook->addLine('Event action is ' . $action->getName() . ' (' . $action->getAliasWithNamespace() . ')');
            if ($this->getNotifyOnActionAlias() !== null){
                if (! $event->getAction()->isExactly($this->getNotifyOnActionAlias())) {
                    $this->skipEvent('**Skipping** event because of `notify_on_action_alias:' . $this->getNotifyOnActionAlias() .'`', $event, $logbook);
                    return;
                }
            } else {
                if (! $event->getAction()->getMetaObject()->isExactly($this->getObject())) {
                    $this->skipEvent('**Skipping** event because action object does not match event object', $event, $logbook);
                    return;
                }
            }
        }
        
        // If these notifications need to be sent after all transactions commit, add a listener
        // to the OnStop event of the workbench and remember the original event, that triggered
        // the notifications. Just call this whole method again then, but remove the postponing-flag.
        if ($this->getNotifyAfterAllActionsComplete() === true) {
            $this->getWorkbench()->eventManager()->addListener(OnBeforeStopEvent::getEventName(), function(OnBeforeStopEvent $onBeforeStopEvent) use ($event) {
                $this->setNotifyAfterAllActionsComplete(false);
                $this->onEventNotify($event);
            });
            $this->skipEvent('**Delegating** to `OnBeforeStop` event because of `notify_after_all_actions_complete:true`', $event, $logbook);
            return;
        }
        
        // Here is a possibility to add custom placeholder resolvers depending on the event type:
        // just instantiate the resolver and add it to this array.
        $phResolvers = [];
        
        $dataSheet = null;
        switch (true) {
            // For data-events, use their data obviously
            case $event instanceof DataSheetEventInterface:
                $dataSheet = $event->getDataSheet();
                $logbook->addLine('Received data for ' . $dataSheet->getMetaObject()->__toString() . ' from data-event');
                break;
            // For action-events, use their input data as object-restrictions will be probably expected to apply to input data:
            // e.g. notify_on_action on object XYZ obviously means "if action performed upon object XYZ", not "if action produces
            // object XYZ"
            case $event instanceof ActionRuntimeEventInterface:
                // TODO getting data from action events is not straight-forward: we can either use input or result data (or both?)
                // Maybe add additional placeholders to $phResolvers for `input_data:` and `result_data`? But the $phResolvers are
                // currently applied to the entire config, not each data row... -> allow two additional arrays?
                $dataSheet = $event->getActionInputData();
                $logbook->addLine('Received data for ' . $dataSheet->getMetaObject()->__toString() . ' from action-event');
                break;          
        }
        $logbook->addDataSheet('Data for messages', $dataSheet);
        
        // Don't send anything if the event has data restrictions, but no data!
        if (! $dataSheet && ($this->hasRestrictionConditions() || $this->hasRestrictionOnAttributeChange())) {
            $this->skipEvent('**Skipping** event because of `notify_if_attributes_change` or `notify_if_data_matches_conditions` are set, but the event does not provide any data', $event, $logbook);
            return;
        }
        
        // Ignore the event if the data is based on another object
        if ($dataSheet && ! $dataSheet->getMetaObject()->isExactly($this->getObject())) {
            $this->skipEvent('**Skipping** event because data object does not match behavior object', $event, $logbook);
            return;
        }
        
        // Ignore the event if its data was already processed and set to be ignored (e.g. required change did not happen)
        if ($dataSheet && in_array($dataSheet, $this->ignoreDataSheets, true)) {
            $this->skipEvent('**Skipping** event because data was already processed and set to be ignored (e.g. required change did not happen)', $event, $logbook);
            return;
        }
        
        // Ignore the event if its data does not match restrictions
        if ($dataSheet && $this->hasRestrictionConditions()) {
            $dataSheet = $dataSheet->extract($this->getNotifyIfDataMatchesConditions(), true);
            if ($dataSheet->isEmpty()) {
                $this->skipEvent('**Skipping** event because of `notify_if_data_matches_conditions`', $event, $logbook);
                return;
            }
        }        
        
        // If everything is OK, generate UXON envelopes for the messages and send them
        $logbook->addSection('Sending notifications');
        $communicator = $this->getWorkbench()->getCommunicator();
        $e = null;
        if ($this->messageUxons !== null) {
            try {
                $envelopes = $this->getMessageEnvelopes($this->messageUxons, $dataSheet, $phResolvers);
                $logbook->addLine('Prepared ' . count($envelopes) . ' envelopes');
            } catch (\Throwable $e) {
                $logbook->addException($e);
                $this->handleError($e);
            }
            foreach ($envelopes as $envelope) {
                try {
                    $logbook->addLine('Sending ' . $envelope->getChannelSelector());
                    $communicator->send($envelope);
                } catch (\Throwable $e) {
                    $logbook->addException($e);
                    $this->handleError($e, $envelope);
                }
            }
        }
        
        // If need to prevent recursion, do not release the lock on errors as they might cause
        // the next iteration asynchronously - e.g. if reacting to events inside the logger!
        if ($e === null || $this->getPreventRecursion() === false) {
            $this->isNotificationInProgress = false;
        }
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
        return;
    }
    
    protected function skipEvent(string $reason, EventInterface $event, LogBookInterface $logbook)
    {
        $logbook->addLine($reason);
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
        $this->isNotificationInProgress = false;
        return;
    }
    
    /**
     * 
     * @param \Throwable $e
     * @param Envelope $envelope
     * 
     * @throws \exface\Core\Exceptions\Communication\CommunicationNotSentError
     * 
     * @return void
     */
    protected function handleError(\Throwable $e, Envelope $envelope = null)
    {
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
    
    /**
     * 
     * @return bool
     */
    protected function getPreventRecursion() : bool
    {
        return $this->preventRecursion;
    }
    
    /**
     * Set to TRUE to make sure no new notification are sent while the sending one.
     * 
     * For example, if notifying about errors, new errors might arise while the original notification
     * is being sent. This option can forcibly prevent them from being sent, because otherwise they
     * would probably cause recursion.
     * 
     * @uxon-property prevent_recursion
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return NotifyingBehavior
     */
    protected function setPreventRecursion(bool $value) : NotifyingBehavior
    {
        $this->preventRecursion = $value;
        return $this;
    }
    
    protected function getNotifyAfterAllActionsComplete() : bool
    {
        return $this->notifyAfterAllActionsComplete;
    }
    
    /**
     * Set to TRUE to not notify immediately, but to wait until all business logic is done and transactions are committed
     * 
     * @uxon-property notify_after_all_actions_complete
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return NotifyingBehavior
     */
    public function setNotifyAfterAllActionsComplete(bool $value) : NotifyingBehavior
    {
        $this->notifyAfterAllActionsComplete = $value;
        return $this;
    }
    
    /**
     * Set to FALSE to send notifications for data events even if the recipient user is not authorized to read the corresponding data
     * 
     * By default, the behavior will check every data row to see if the user to be notified
     * is authorized to read it and will only send the message if so.
     * 
     * This option only applies to notifications sent on data events and only if the recipient is
     * a user, a user role, or anything else, that implies a message being sent ot a user.
     * 
     * @uxon-property notify_if_data_authorized
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return NotifyingBehavior
     */
    protected function setNotifyIfDataAuthorized(bool $trueOrFalse) : NotifyingBehavior
    {
        $this->notifyIfDataAuthorized = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @see SendMessagesFromDataTrait::willSendOnlyForAuthorizedData()
     */
    protected function willSendOnlyForAuthorizedData() : bool
    {
        return $this->notifyIfDataAuthorized;
    }
}