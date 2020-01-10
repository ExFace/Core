<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use Symfony\Component\Security\Core\User\UserChecker;
use exface\Core\CommonLogic\Security\Symfony\SymfonyUserProvider;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;

class SymfonyLdapBindAuthenticator extends SymfonyAuthenticator
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getName()
     */
    public function getName() : string
    {
        return 'Symfony LDAP Authentication';
    }
    
    /**
     * 
     * @return array
     */
    protected function getSymfonyAuthProviders() : array
    {
        return [
            $this->getSymfonyLdapBindAuthenticationProvider()
        ];
    }
    
    /**
     * 
     * @return LdapBindAuthenticationProvider
     */
    protected function getSymfonyLdapBindAuthenticationProvider() : LdapBindAuthenticationProvider
    {
        $userProvider = new SymfonyUserProvider($this->getWorkbench());
        $userChecker = new UserChecker();
        $adapter = new Adapter(["host" => "SDRDC4"]);
        $ldap = new Ldap($adapter); 
        return new LdapBindAuthenticationProvider($userProvider, $userChecker, 'secured_area', $ldap, 'salt-solutions\{username}');
    }
}