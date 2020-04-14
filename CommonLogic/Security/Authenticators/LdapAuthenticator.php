<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\DomainUsernamePasswordAuthToken;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\StringDataType;

/**
 * Performs authentication via PHP LDAP extension. 
 * 
 * ## Examples
 * 
 * ### Authentication + create new users with static roles
 * 
 * ```
 * {
 * 		"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\LdapAuthenticator",
 * 		"host": "MYLDAP",
 * 		"domains": [
 * 			"mydomain"
 * 		],
 * 		"create_new_users": true,
 * 		"create_new_users_with_roles": [
 * 			"exface.Core.SUPERUSER"
 * 		]
 * }
 * 
 * ```
 * 
 * Place the domain name of your LDAP server (or it's IP address) in the `host` property
 * and list all domains available for logging in to under `domains`.
 * 
 * If `create_new_users` is `true`, a new workbench user will be created automatically once
 * a new username is authenticated successfully. These new users can be assigned some roles
 * under `create_new_users_with_roles`. 
 * 
 * If a new user is not assigned any roles, he or she will only have access to resources
 * available for the user roles `exface.Core.ANONYMOUS` and `exface.Core.AUTHENTICATED`.
 * 
 * @author Andrej Kabachnik
 *
 */
class LdapAuthenticator extends AbstractAuthenticator
{    
    use CreateUserFromTokenTrait;
    
    private $hostname = null;
    
    private $authenticatedToken = null;
    
    private $domains = null;
    
    private $dnString = '[#domain#]\\[#username#]';
    
    private $usernameInputCaption = null;
    
    private $ldapSurnameAlias = 'surname';
    
    private $ldapGivenNameAlias = 'givenname';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! $token instanceof DomainUsernamePasswordAuthToken) {
            throw new InvalidArgumentException('Invalid token type!');
        }
        
        $ldappass = $token->getPassword();
        $placeholders = [];
        $placeholders['username'] = $token->getUsername();
        $placeholders['domain'] = $token->getDomain();
        $ldaprdn = StringDataType::replacePlaceholders($this->getDnString(), $placeholders);
        
        // verbinden zum ldap server
        $host = $this->getHost(); 
        $ldapconn = @ldap_connect($host);        
        if (!$ldapconn) {            
            throw new AuthenticationFailedError($this, 'No connection to LDAP server!');
        }
        
        // those options are necessary for ldap_search to work, must be applied before the ldap_bind
        ldap_set_option ($ldapconn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        
        // anmelden am ldap server
        $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);
        if (! $ldapbind) {
            throw new AuthenticationFailedError($this, 'LDAP authentication failed', null, new RuntimeException(ldap_error($ldapconn), ldap_errno($ldapconn)));
        }        
        
        if ($this->getCreateNewUsers() === true) {
            $dnArray = explode('.', $host);
            $baseDn = '';
            foreach ($dnArray as $part) {
                $baseDn .= 'dc=' . $part . ',';
            }
            $baseDn = substr($baseDn, 0, -1);
            $attributes = [$this->getLdapGivennameAlias(), $this->getLdapSurnameAlias()];
            $ldapresult = ldap_search($ldapconn, $baseDn, "(&(objectClass=user)(sAMAccountName={$token->getUsername()}))", $attributes);
            if ($ldapresult === false) {
                $this->getWorkbench()->getLogger()->logException(new RuntimeException(ldap_error($ldapconn), ldap_errno($ldapconn)));
            }
            $entry_array = ldap_get_entries($ldapconn, $ldapresult);
            $surname = null;
            $givenname = null;
            if ($entry_array['count'] > 0) {
                $surname = $entry_array[0][$this->getLdapSurnameAlias()][0];
                $givenname = $entry_array[0][$this->getLdapGivennameAlias()][0];
            }            
            $this->createUserWithRoles($this->getWorkbench(), $token, $surname, $givenname);
        } else {
            if (empty($this->getUserData($this->getWorkbench(), $token)->getRows())) {
                throw new AuthenticationFailedError($this, 'Authentication failed, no PowerUI user with that username exists and none was created!');
            }
        }
        ldap_unbind($ldapconn);
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
        return $token instanceof DomainUsernamePasswordAuthToken;
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
    protected function getHost() : string
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
     * @return LdapAuthenticator
     */
    public function setDomains($arrayOrUxon) : LdapAuthenticator
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->domains = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->domains = array_combine($arrayOrUxon, $arrayOrUxon);
        }
        return $this;
    }
    
    /**
     *
     * @return string[]|NULL
     */
    protected function getDomains() : ?array
    {
        return $this->domains;
    }
    
    /**
     *
     * @return string
     */
    protected function getDnString() : string
    {
        return $this->dnString;
    }
    
    /**
     * The dn_string for LDAP authentication.
     *
     * This key defines the form of the string used in order to compose the DN of the user, from the username.
     * Supported placeholders are `[#domain#]` and `[#username#]`. Default is [#domain#]\\[#username#].
     *
     * @uxon-property dn_string
     * @uxon-type string
     * @uxon-template [#domain#]\[#username#]
     * @uxon-default [#domain#]\[#username#]
     *
     * @param string $value
     * @return LdapAuthenticator
     */
    public function setDnString(string $value) : LdapAuthenticator
    {
        $this->dnString = $value;
        return $this;
    }
    
    /**
     * Set the caption for the input field of the username.
     * 
     * @uxon-property username_input_caption
     * @uxon-type string
     * 
     * @param string $caption
     * @return LdapAuthenticator
     */
    public function setUsernameInputCaption(string $caption) : LdapAuthenticator
    {
        $this->usernameInputCaption = $caption;
        return $this;
    }
    
    protected function getUsernameInputCaption() : string
    {
        if ($this->usernameInputCaption === null) {
            return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.USERNAME');
        }
        return $this->usernameInputCaption;
    }
    
    /**
     * Set the property name the surname is saved as in the Ldap user object.
     * Default is `surname`
     * 
     * @uxon-property ldap_surname_alias
     * @uxon-type string
     * 
     * @param string $alias
     * @return LdapAuthenticator
     */
    public function setLdapSurnameAlias(string $alias) : LdapAuthenticator
    {
        $this->ldapSurnameAlias;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getLdapSurnameAlias() : string
    {
        return  $this->ldapSurnameAlias;
    }
    
    /**
     * Set the property name the givenname is saved as in the Ldap user object.
     * Default is `givenname`
     *
     * @uxon-property ldap_givenname_alias
     * @uxon-type string
     *
     * @param string $alias
     * @return LdapAuthenticator
     */
    public function setLdapGivennameAlias(string $alias) : LdapAuthenticator
    {
        $this->ldapGivennameAlias;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getLdapGivennameAlias() : string
    {
        return  $this->ldapGivenNameAlias;
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
                'selectable_options' => $this->getDomains() ?? [],
                'required' => true
            ],[
                'attribute_alias' => 'USERNAME',
                'caption' => $this->getUsernameInputCaption(),
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