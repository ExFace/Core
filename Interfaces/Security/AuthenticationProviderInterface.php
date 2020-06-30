<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * An authentication provider is a class, that can authenticate tokens.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthenticationProviderInterface extends WorkbenchDependantInterface
{
    
    /**
     * Authenticates the given token or throws an exception.
     * 
     * @param AuthenticationTokenInterface $token
     * @throws AuthenticationFailedError
     * @return AuthenticationTokenInterface
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface;
    
    /**
     * Populates the given container with inputs required to perform authentication via this provider.
     *
     * In many cases, this method will simply add input-widgets for username and password.
     * However, some providers may add a secondary authentication factors or even use a
     * `Browser` widget to display a remote login-page (e.g. for OAuth-authentication).
     *
     * @param iContainOtherWidgets $container
     * @return iContainOtherWidgets
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets;
}