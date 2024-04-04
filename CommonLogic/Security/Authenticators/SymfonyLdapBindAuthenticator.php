<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use Symfony\Component\Security\Core\User\UserChecker;
use exface\Core\CommonLogic\Security\Symfony\SymfonyUserProvider;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\DomainUsernamePasswordAuthToken;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Widgets\Form;

/**
 * Performs authentication via the Symfony LdapBindAuthenticationProvider.
 * 
 * See https://symfony.com/doc/current/security/ldap.html for detailes on Symfony's
 * LDAP configuration.
 * 
 * ## Configuration options
 * 
 * - `host` - IP address or hostname of the LDAP server
 * - `dn_string` - default is `[#domain#]\\[#username#]`.
 * - `domains` - array of domains for the user to pick from.
 * 
 * ## Examples
 * 
 * ### Authentication + create new users with static roles
 * 
 * ```
 * {
 * 		"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\SymfonyLdapBindAuthenticator",
 * 		"host": "MYLDAP",
 *      "dn_string": "{username}",
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
 * @link https://symfony.com/doc/current/security/ldap.html
 * 
 * @author Andrej Kabachnik
 *
 */
class SymfonyLdapBindAuthenticator extends SymfonyAuthenticator
{    
    use CreateUserFromTokenTrait;
    
    private $host = null;
    
    private $dnString = '{username}';
    
    private $domains = null;
    
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {
        if ($token instanceof DomainUsernamePasswordAuthToken) {
            $domain = $token->getDomain();
            $this->setDnString($domain . '\{username}');
        }
        $authenticatedToken = parent::authenticate($token);
        if ($authenticatedToken->isAnonymous() === false) {
            if ($this->getCreateNewUsers(true) === true) {
                $user = $this->createUserWithRoles($this->getWorkbench(), $token);
            } else {
                if (empty($this->getUserData($this->getWorkbench(), $token)->getRows())) {
                    throw new AuthenticationFailedError($this, "Authentication failed, no workbench user '{$token->getUsername()}' exists: either create one manually or enable `create_new_users` in authenticator configuration!", '7AL3J9X');
                }
            }
            $this->syncUserRoles($user, $authenticatedToken);
        }
        return $token;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.SIGN_IN');
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
            $this->domains = array_combine($arrayOrUxon, $arrayOrUxon);
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
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::createLoginForm()
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
}