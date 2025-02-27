<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\Events\Contexts\OnContextInitEvent;
use exface\Core\Contexts\DebugContext;
use exface\Core\Events\Communication\OnMessageRoutedEvent;
use exface\Core\Communication\Recipients\UserRecipient;
use exface\Core\Factories\UserFactory;
use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class CommunicationInterceptor
{    
    private $workbench = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param int $startOffsetMs
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
        $this->registerEventHandlers();
        $workbench->eventManager()->addListener(OnContextInitEvent::getEventName(), function(OnContextInitEvent $event){
            if ($event->getContext() instanceof DebugContext) {
                $event->getContext()->startInterceptingCommunication($this);
            }
        });
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\Tracer
     */
    protected function registerEventHandlers()
    {
        $event_manager = $this->workbench->eventManager();
        
        $event_manager->addListener(OnMessageRoutedEvent::getEventName(), [
            $this,
            'onMessageRoutedIntercept'
        ]);
        
        return $this;
    }
    
    /**
     * 
     * @param OnMessageRoutedEvent $event
     */
    public function onMessageRoutedIntercept(OnMessageRoutedEvent $event)
    {
        $msg = $event->getMessage();
        
        $sendToUsers = $this->workbench->getConfig()->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USERS');
        $sendToRoles = $this->workbench->getConfig()->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USER_ROLES');
        if ($sendToUsers || $sendToRoles) {
            $recipients = $msg->getRecipients();
            if (! empty($recipients)) {
                $interceptedTo = [];
                $msg->clearRecipients();
                if ($sendToUsers) {
                    foreach (explode(',', $sendToUsers) as $userSelector) {
                        if ($userSelector !== '' && $userSelector !== null) {
                            $interceptedTo[] = $userSelector;
                            $msg->addRecipient(new UserRecipient(UserFactory::createFromUsernameOrUid($this->workbench, trim($userSelector))));
                        }
                    }
                }
                if ($sendToRoles) {
                    foreach (explode(',', $sendToRoles) as $roleSelector) {
                        if ($roleSelector !== '' && $roleSelector !== null) {
                            $interceptedTo[] = $roleSelector;
                            $msg->addRecipient(new UserRoleRecipient(new UserRoleSelector($this->workbench, trim($roleSelector))));
                        }
                    }
                }
                $rerouteList = implode(', ', $interceptedTo);
                $recipientsList = implode(', ', $recipients);
                $msg->setText($msg->getText() . <<<TEXT
             
       
-------------------
DEBUG 

This message was intercepted and re-routed to $rerouteList by the debugger.

Original recipients: $recipientsList

TEXT);
            }
        }
    }
}