<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * BETA
 * 
 * @author Andrej Kabachnik
 *
 */
class LdapAuthenticator extends AbstractAuthenticator
{    
    private $hostname = null;
    
    private $authenticatedToken = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! $token instanceof UsernamePasswordAuthToken) {
            throw new InvalidArgumentException('Invalid token type!');
        }
        // verwenden von ldap bind
        $ldaprdn  = $token->getUsername();  // ldap rdn oder dn
        $ldappass = $token->getPassword();  // entsprechendes password
        
        // verbinden zum ldap server
        $ldapconn = @ldap_connect($this->hostname);
        if (!$ldapconn) {
            throw new AuthenticationFailedError('No connection to LDAP server!');
        }
        
        // binden zum ldap server
        $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);
        
        // Bindung überpfrüfen
        if (! $ldapbind) {
            throw new AuthenticationFailedError('LDAP authentication failed');
        }
        $this->authenticatedToken = $token;
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $token === $this->authenticatedToken;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool
    {
        return $token instanceof UsernamePasswordAuthToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'LDAP Authentication';
    }
    
    /**
     *
     * @return string
     */
    public function getHost() : string
    {
        return $this->hostname;
    }
    
    /**
     * The LDAP server host name
     * 
     * @uxon-property host
     * @uxon-type uri
     * @uxon-required true
     * 
     * @param string $value
     * @return LdapAuthenticator
     */
    public function setHost(string $value) : LdapAuthenticator
    {
        $this->hostname = $value;
        return $this;
    }
}