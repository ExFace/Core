<?php
namespace exface\Core\Interfaces\Security;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
interface RememberMeAuthenticatorInterface extends AuthenticationProviderInterface
{
    /**
     * 
     * @return RememberedTokenInterface|NULL
     */
    public function getTokenRemembered() : ?RememberedTokenInterface;
}