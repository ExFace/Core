<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Communication\Messages\NotificationMessage;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\TranslationPlaceholders;
use exface\Core\Templates\Placeholders\ExcludedPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Communication\Messages\Envelope;

/**
 * Creates user-notifications on certain events and conditions.
 * 
 * BETA: This behavior is not yet fully functional. Some features may not work correctly!
 * 
 * Each behavior instance can be configured to send notifications on a specific event
 * by setting the mandatory `notify_on` option. Additionally other `notify_*` options
 * can be used to introduce further conditions. If you need notifications on multiple
 * events - create multiple behaviors for the object.
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
 * ## Examples
 * 
 * ### Send an in-app notification to a user role every time a task is created
 * 
 * ```
 *  {
 *      "notify_on": "exface.Core.DataSheet.OnCreateData",
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
 * ### Send an email to the ticket author once it reaches a certain status
 * 
 * ```
 *  {
 *      "notify_on": "exface.Core.DataSheet.OnUpdateData",
 *      "notify_if_attributes_change": [
 *          "status"
 *      ],
 *      "notify_on_conditions": [{
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
    private $notifyOn = null;
    
    private $notifyIfAttributesChange = [];
    
    private $notifyIfDataMatchesConditions = null;
    
    private $messageUxons = null;

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
        
        if (($event instanceof MetaObjectEventInterface) && ! $event->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        if (($event instanceof DataSheetEventInterface) && ! $event->getDataSheet()->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        if ($event instanceof DataSheetEventInterface) {
            $dataSheet = $event->getDataSheet();
            if (! $dataSheet->getMetaObject()->is($this->getObject())) {
                return;
            }
        }
        
        $communicator = $this->getWorkbench()->getCommunicator();
        foreach ($this->getNotificationEnvelopes($event) as $envelope)
        {
            $communicator->send($envelope);
        }
        return;
    }
    
    protected function getNotifyOnEventName() : string
    {
        return $this->notifyOn;
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
        $this->notifyOn = $value;
        return $this;
    }

    /**
     * 
     * @return CommunicationMessageInterface[]
     */
    protected function getNotificationEnvelopes(EventInterface $event) : array
    {
        $messages = [];
        foreach ($this->messageUxons as $uxon) {
            $json = $uxon->toJson();
            $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
            $renderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench()));
            $renderer->addPlaceholder(new TranslationPlaceholders($this->getWorkbench()));
            $renderer->addPlaceholder(new ExcludedPlaceholders('~notification:', '[#', '#]'));
            switch (true) {
                case $event instanceof DataSheetEventInterface:
                    $dataSheet = $event->getDataSheet();
                    foreach (array_keys($dataSheet->getRows()) as $rowNo) {
                        $rowRenderer = clone $renderer;
                        $rowRenderer->addPlaceholder(new DataRowPlaceholders($dataSheet, $rowNo, '~data:'));
                        $rowRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench(), $dataSheet, $rowNo));
                        $renderedUxon = UxonObject::fromJson($rowRenderer->render($json));
                        $messages[] = new Envelope($this->getWorkbench(), $renderedUxon);
                    }
                    break;
                default:
                    $renderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench()));
                    $renderedUxon = UxonObject::fromJson($renderer->render($json));
                    $messages[] = new Envelope($this->getWorkbench(), $renderedUxon);
            }
        }
            
        return $messages;
    }
    
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
     * for which the notification was triggered - only works with notification on data sheet events!
     * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
     * This means, static formulas will always work, while data-driven formulas will only work on data sheet
     * events!
     * 
     * @uxon-property notifications
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template {"channel": "", "recipients": ""}
     * 
     * @param UxonObject $arrayOfEnvelopes
     * @return NotifyingBehavior
     */
    protected function setNotifications(UxonObject $arrayOfEnvelopes) : NotifyingBehavior
    {
        $this->messageUxons = $arrayOfEnvelopes;
        return $this;
    }
}