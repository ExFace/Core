<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\DomainUsernamePasswordAuthToken;
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Form;

/**
 * Performs authentication via PHP LDAP extension. 
 * 
 * ## Configuration
 * 
 * **IMPORTANT**: make sure ldap PHP extension is installed (i.e. uncomment `extension=ldap` in php.ini)
 * 
 * - `host` - IP address or hostname of the LDAP server
 * - `dn_string` - default is `[#domain#]\\[#username#]`.
 * - `domains` - array of domains for the user to pick from.
 * - `ldap_options` - see below
 * 
 * ### LDAP options
 * 
 * PHP allows to configure its LDAP extension by setting various options as described here:
 * https://www.php.net/manual/en/function.ldap-set-option.php. Some information about the
 * possible values of these options can also be found here: https://www.php.net/manual/en/ldap.constants.php.
 * 
 * ## LDAPS (secure LDAP) and custom ports
 * 
 * In order to use LDAPS, make sure the `host` looks like this: `ldaps://adserver:636`.
 * 
 * If getting `Can't contact LDAP server` errors with a self-signed SSL certificate, try
 * disabling certificate verification:
 * 
 * - on Windows create the file `C:\OpenLDAP\sysconf\ldap.conf` with a single line `TLS_REQCERT never`.
 * - on Linux add the same line to `/usr/local/openldap/etc/openldap/ldap.conf`
 * - **restart** the web server (e.g. Apache)!
 * 
 * NOTE: disabling certifcate validation makes the server vulnurable to man-in-the-middle attacks!
 * 
 * ## Debugging the connection
 * 
 * Create a separate php-file for testing with the code below an call it from command line!
 * 
 * ```
 * <?php
 * ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
 * $resource = ldap_connect("ldaps://{redacted}/", 636) or die ("Could not connect.");
 * ldap_set_option($resource, LDAP_OPT_PROTOCOL_VERSION, 3)
 * ldap_set_option ($ldapconn, LDAP_OPT_REFERRALS, 0);
 * $bound = ldap_bind($resource, "{redacted}\ldap", "****");
 * echo ldap_error($resource);
 * 
 * ```
 * 
 * ## Examples
 * 
 * ### Authentication + create new users with static roles
 * 
 * ```
 * {
 * 		"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\LdapAuthenticator",
 * 		"host": "adserver",
 * 		"domains": [
 * 			"mydomain"
 * 		],
 * 		"create_new_users": true,
 * 		"create_new_users_with_roles": [
 * 			"exface.Core.SUPERUSER"
 * 		],
 *      "ldap_name_alias": "name",
 *      "ldap_name_pattern": "/(?<lastname>.*), (?<firstname>.*)/i",
 *      "dn_string": "[#domain#]\\[#username#]"
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
    
    private const LDAP_NAME_DEFAULT_PATTERN = '/(?<lastname>.*), (?<firstname>.*)/i';
    
    private const LDAP_NAME_DEFAULT_ALIAS = 'name';
    
    private const LDAP_DEFAULT_DN_STRING = '[#domain#]\\[#username#]';
    
    private $hostname = null;
    
    private $authenticatedToken = null;
    
    private $domains = null;
    
    private $dnString = null;
    
    private $usernameInputCaption = null;
    
    private $ldapNameAlias = null;
    
    private $ldapNamePattern = null;
    
    private $ldapOptions = [];
    
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
        $this->checkAuthenticatorDisabledForUsername($token->getUsername());
        
        $ldappass = $token->getPassword();
        $placeholders = [];
        $placeholders['username'] = $token->getUsername();
        $placeholders['domain'] = $token->getDomain();
        $ldaprdn = StringDataType::replacePlaceholders($this->getDnString(), $placeholders);
        
        // verbinden zum ldap server
        $host = $this->getHost(); 
        $ldapconn = @ldap_connect($host);        
        if (!$ldapconn) {
            throw new AuthenticationFailedError($this, 'No connection to LDAP server "' . $host . '"! ', '7AL3J9X');
        }
        
        // those options are necessary for ldap_search to work, must be applied before the ldap_bind
        $defaultOptions = [
            'LDAP_OPT_REFERRALS' => 0,
            'LDAP_OPT_PROTOCOL_VERSION' => 3
        ];
        $options = array_merge($defaultOptions, $this->getLdapOptions());
        foreach ($options as $opt => $val) {
            $const = constant(strtoupper($opt));
            if ($const === null) {
                throw new InvalidArgumentException('Cannot initialize LDAP authenticator: unknown LDAP option "' . $opt . '"!');
            }
            if (is_string($val) && StringDataType::startsWith($val, 'LDAP_OPT_', false)) {
                $val = constant(strtoupper($val));
                if ($val === null) {
                    throw new InvalidArgumentException('Cannot initialize LDAP authenticator: invalid value for LDAP option "' . $opt . '"!');
                }
            }
            $optRs = ldap_set_option($ldapconn, $const, $val);
            if ($optRs === false) {
                throw new InvalidArgumentException('Cannot initialize LDAP authenticator: cannot set LDAP option "' . $opt . '" to "' . $val . '"!');
            }
        }
        $pwd = trim($token->getPassword());
        if ($pwd === null || $pwd === '' || preg_match('/\x00/',$pwd) === 1 || empty($pwd)) {
            throw new AuthenticationFailedError($this, 'Authentication failed, empty password not allowed!', '7AL3J9X');
        }
        // anmelden am ldap server
        $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);
        if (! $ldapbind) {
            $errText = ldap_error($ldapconn);
            $errDetails = null;
            ldap_get_option($ldapconn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $errDetails);
            if ($errDetails && $errDetails !== $errText) {
                $errText .= ' (' . $errDetails . ')';
            }
            throw new AuthenticationFailedError($this, 'LDAP error ' . ldap_errno($ldapconn) . ': ' . $errText, '7AL3J9X');
        }
        
        if ($token->isAnonymous() === false) {
            $user = null;
            
            if ($this->userExists($token) === true) {
                $user = $this->getUserFromToken($token);
            } elseif ($this->getCreateNewUsers(true) === true) {    
                $user = $this->createUserWithRoles($this->getWorkbench(), $token, $this->getNewUserData($ldapconn, $token));
            } else {
                throw new AuthenticationFailedError($this, "Authentication failed, no workbench user '{$token->getUsername()}' exists: either create one manually or enable `create_new_users` in authenticator configuration!", '7AL3J9X');
            }
            ldap_unbind($ldapconn);
            $this->authenticatedToken = $token;
            $this->logSuccessfulAuthentication($user, $token->getUsername());
            
            if ($token->getUsername() !== $user->getUsername()) {
                return new DomainUsernamePasswordAuthToken($token->getDomain(), $user->getUsername(), $token->getPassword());
            }
        }
        
        $this->syncUserRoles($user, $token);
        
        return $token;
    }
    
    /**
     * 
     * @param resource $ldapconn
     * @param AuthenticationTokenInterface $token
     * @return array
     */
    protected function getNewUserData($ldapconn, AuthenticationTokenInterface $token) : array
    {
        $dnArray = explode('.', $this->getHost());
        $baseDn = '';
        foreach ($dnArray as $part) {
            $baseDn .= 'dc=' . $part . ',';
        }
        $baseDn = substr($baseDn, 0, -1);
        $attributes = [$this->getLdapNameAlias()];
        $ldapresult = ldap_search($ldapconn, $baseDn, "(&(objectClass=user)(sAMAccountName={$token->getUsername()}))", $attributes);
        if ($ldapresult === false) {
            $this->getWorkbench()->getLogger()->logException(new RuntimeException(ldap_error($ldapconn), ldap_errno($ldapconn)));
            return [];
        }
        $entryArray = ldap_get_entries($ldapconn, $ldapresult);
        $surname = null;
        $givenname = null;
        if ($entryArray['count'] > 0) {
            $pattern = $this->getLdapNamePattern();
            $nameString = $entryArray[0][$this->getLdapNameAlias()][0];
            $matches = [];
            if (preg_match_all($pattern, $nameString, $matches)) {
                $surname = $matches['lastname'][0];
                $givenname = $matches['firstname'][0];
            }
        }
        return [
            'LAST_NAME' => $surname,
            'FIRST_NAME' => $givenname
        ];
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
        return ($token instanceof DomainUsernamePasswordAuthToken) && $this->isSupportedFacade($token);
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
        if ($this->dnString === null) {
            return self::LDAP_DEFAULT_DN_STRING;
        }
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
     * Set the attribute name the users full name is saved as in the Ldap user object.
     * Default is `name`.
     *
     * @uxon-property ldap_name_attribute
     * @uxon-type string
     *
     * @param string $attribute
     * @return LdapAuthenticator
     */
    public function setLdapNameAttribute(string $attribute) : LdapAuthenticator
    {
        $this->ldapNameAlias = $attribute;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getLdapNameAlias() : string
    {
        if ($this->ldapNameAlias === null) {
            return self::LDAP_NAME_DEFAULT_ALIAS;
        }
        return  $this->ldapNameAlias;
    }
    
    /**
     * Set the regular expression mask the value of the ldap_name_alias option in the Ldap user object will be evaluated by.
     * The mask should contain the named character groups `lastname` and `firstname`.
     * The default regular expression is: `/(?<lastname>.*), (?<firstname>.*)/i`.
     * 
     * @uxon-prototype ldap_name_pattern
     * @uxon-type string
     * 
     * @param string $pattern
     * @return LdapAuthenticator
     */
    public function setLdapNamePattern(string $pattern) : LdapAuthenticator
    {
        $this->ldapNamePattern = $pattern;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getLdapNamePattern() : string
    {
        if ($this->ldapNamePattern === null) {
            return self::LDAP_NAME_DEFAULT_PATTERN;
        }
        return $this->ldapNamePattern;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::createLoginForm()
     */
    protected function createLoginForm(Form $emptyForm) : Form
    {
        $domains = $this->getDomains() ?? [];
        $emptyForm->setWidgets(new UxonObject([
            [
                'data_column_name' => 'DOMAIN',
                'widget_type' => 'InputSelect',
                'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.DOMAIN'),
                'selectable_options' => $domains,
                'required' => true,
                'value' => count($domains) === 1 ? $domains[0] : null
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
        
        $emptyForm->setColumnsInGrid(1);
        
        if ($emptyForm->hasButtons() === false) {
            $emptyForm->addButton($emptyForm->createButton(new UxonObject([
                'action_alias' => 'exface.Core.Login',
                'align' => EXF_ALIGN_OPPOSITE,
                'visibility' => WidgetVisibilityDataType::PROMOTED
            ])));
        }
        
        return $emptyForm;
    }
    
    /**
     * Set the options of the PHP extendsion (see PHP's `ldap_set_option()`).
     * 
     * See https://www.php.net/manual/en/function.ldap-set-option.php for available options.
     * 
     * @uxon-property ldap_options
     * @uxon-type array
     * @uxon-template {"LDAP_OPT_PROTOCOL_VERSION": 3, "LDAP_OPT_REFERRALS": 0, "": ""}
     * 
     * @link https://www.php.net/manual/en/function.ldap-set-option.php
     * 
     * @param UxonObject|array $uxonOrArray
     * @return LdapAuthenticator
     */
    public function setLdapOptions($uxonOrArray) : LdapAuthenticator
    {
        $this->ldapOptions = $uxonOrArray instanceof UxonObject ? $uxonOrArray->toArray() : $uxonOrArray;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getLdapOptions() : array
    {
        return $this->ldapOptions;
    }
}