<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * BETA
 * 
 * @author Andrej Kabachnik
 *
 */
class LdapAuthenticator implements AuthenticatorInterface
{
    private $workbench;
    
    private $hostname = null;
    
    private $authenticatedToken = null;
    
    public function __construct(WorkbenchInterface $workbench, string $hostname)
    {
        $this->workbench = $workbench;
        $this->hostname = $hostname;
    }
    
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
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getName()
     */
    public function getName() : string
    {
        return 'LDAP Authentication';
    }
    public function getWorkbench()
    {
        return $this->workbench();
    }
}