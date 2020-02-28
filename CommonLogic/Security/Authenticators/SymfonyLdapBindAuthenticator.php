<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use Symfony\Component\Security\Core\User\UserChecker;
use exface\Core\CommonLogic\Security\Symfony\SymfonyUserProvider;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;

class SymfonyLdapBindAuthenticator extends SymfonyAuthenticator
{    
    private $host = null;
    
    private $dnString = '{username}';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
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
        $adapter = new Adapter(["host" => $this->getHost()]);
        $ldap = new Ldap($adapter); 
        return new LdapBindAuthenticationProvider($userProvider, $userChecker, 'secured_area', $ldap, $this->getDnString());
    }
    
    /**
     *
     * @return string
     */
    public function getHost() : string
    {
        return $this->host;
    }
    
    /**
     * The LDAP server host name
     * 
     * @uxon-property host
     * @uxon-type uri
     * @uxon-required true
     * 
     * @param string $value
     * @return SymfonyLdapBindAuthenticator
     */
    public function setHost(string $value) : SymfonyLdapBindAuthenticator
    {
        $this->host = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getDnString() : string
    {
        return $this->dnString;
    }
    
    /**
     * The dn_string for Symfony's LDAP authentication.
     * 
     * This key defines the form of the string used in order to compose the DN of the user, from the username. 
     * The `{username}` string is replaced by the actual username of the person trying to authenticate.
     * 
     * For example, if your users have DN strings in the form `uid=einstein,dc=example,dc=com`, then the `dn_string` 
     * will be `uid={username},dc=example,dc=com`.
     * 
     * See https://symfony.com/doc/current/security/ldap.html#dn-string for details.
     * 
     * @uxon-property dn_string
     * @uxon-type string
     * @uxon-template {username}
     * @uxon-default {username}
     * 
     * @link https://symfony.com/doc/current/security/ldap.html#dn-string
     * 
     * @param string $value
     * @return SymfonyLdapBindAuthenticator
     */
    public function setDnString(string $value) : SymfonyLdapBindAuthenticator
    {
        $this->dnString = $value;
        return $this;
    }
    
}