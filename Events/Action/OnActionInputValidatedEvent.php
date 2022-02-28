<?php
namespace exface\Core\Events\Action;

/**
 * Event fired after an action has determined and validated it's input data.
 * 
 * This event allows to hook-in further validation handlers or even modify the
 * input data sheet if required.
 * 
 * @event exface.Core.Action.OnActionInputValidated
 *
 * @author Andrej Kabachnik
 *        
 */
class OnActionInputValidatedEvent extends OnBeforeActionInputValidatedEvent
{
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\Action\OnBeforeActionInputValidatedEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Action.OnActionInputValidated';
    }
}