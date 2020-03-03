<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use Symfony\Component\Security\Core\User\UserChecker;
use exface\Core\CommonLogic\Security\Symfony\SymfonyUserProvider;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\DomainUsernamePasswordAuthToken;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;

/**
 * Performs authentication via the Symfony LdapBindAuthenticationProvider.
 * 
 * @link https://symfony.com/doc/current/security/ldap.html
 * 
 * @author Andrej Kabachnik
 *
 */
class SymfonyLdapBindAuthenticator extends SymfonyAuthenticator
{    
    private $host = null;
    
    private $dnString = '{username}';
    
    private $domains = null;
    
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {
        if ($token instanceof DomainUsernamePasswordAuthToken) {
            $domain = $token->getDomain();
            $this->setDnString($domain . '\{username}');
        }
        return parent::authenticate($token);
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