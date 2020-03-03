<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\DomainUsernamePasswordAuthToken;
use exface\Core\Interfaces\Widgets\iHaveButtons;

/**
 * Performs authentication via PHP LDAP extension. 
 * 
 * @author Andrej Kabachnik
 *
 */
class LdapAuthenticator extends AbstractAuthenticator
{    
    private $hostname = null;
    
    private $authenticatedToken = null;
    
    private $domains = null;
    
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
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.SIGN_IN');
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
    
    /**
     * The windows domains allowed.
     *
     * @uxon-property domains
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $domain
     * @return SymfonyLdapBindAuthenticator
     */
    public function setDomains($arrayOrUxon) : SymfonyLdapBindAuthenticator
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->domains = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->domains = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     *
     * @return string[]|NULL
     */
    public function getDomains() : ?array
    {
        return $this->domains;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        $container->setWidgets(new UxonObject([
            [
                'data_column_name' => 'DOMAIN',
                'widget_type' => 'InputSelect',
                'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.DOMAIN'),
                'selectable_options' => array_combine($this->getDomains(), $this->getDomains()) ?? [],
                'required' => true
            ],[
                'attribute_alias' => 'USERNAME',
                'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.USERNAME'),
                'required' => true
            ],[
                'attribute_alias' => 'PASSWORD',
                'required' => true
            ],[
                'attribute_alias' => 'AUTH_TOKEN_CLASS',
                'value' => '\\' . DomainUsernamePasswordAuthToken::class,
                'widget_type' => 'InputHidden'
            ]
        ]));
        
        if ($container instanceof iLayoutWidgets) {
            $container->setColumnsInGrid(1);
        }
        
        if ($container instanceof iHaveButtons && $container->hasButtons() === false) {
            $container->addButton($container->createButton(new UxonObject([
                'action_alias' => 'exface.Core.Login',
                'align' => EXF_ALIGN_OPPOSITE,
                'visibility' => WidgetVisibilityDataType::PROMOTED
            ])));
        }
        
        return $container;
    }
}