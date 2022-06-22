<?php
namespace exface\Core\Events\Security;

/**
 * Event fired after a remember-me-authentication token expired.
 *
 * @event exface.Core.Security.OnAuthenticationExpired
 *
 * @author Andrej Kabachnik
 *        
 */
class OnAuthenticationExpiredEvent extends OnAuthenticationFailedEvent
{
}