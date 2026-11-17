<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

/**
 * DEPRECATED - use LdapAuthenticator instead!
 * 
 * This class used to authenticate via Symfony's LdapBindAuthenticationProvider. It is now
 * a mere alias for the `LdapAuthenticator`, kept to avoid breaking existing configurations.
 * 
 * Differences to the former implementation:
 * 
 * - `dn_string` is ignored. The former implementation always replaced it by `<domain>\{username}`
 * because its login form always contains a domain selector. This is exactly the default
 * `dn_string` of the `LdapAuthenticator`. If you need a custom DN string, switch to the
 * `LdapAuthenticator` and use its placeholder syntax (`[#domain#]`, `[#username#]`).
 * - New users get first and last name from LDAP (see `ldap_name_attribute` and `ldap_name_pattern`
 * of the `LdapAuthenticator`).
 * 
 * @deprecated use \exface\Core\CommonLogic\Security\Authenticators\LdapAuthenticator
 * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator
 * 
 * @author Andrej Kabachnik
 *
 */
class SymfonyLdapBindAuthenticator extends LdapAuthenticator
{
    /**
     * Ignored for backwards compatibility - see class description.
     * 
     * @uxon-property dn_string
     * @uxon-type string
     * 
     * @param string $value
     * @return LdapAuthenticator
     */
    public function setDnString(string $value) : LdapAuthenticator
    {
        return $this;
    }
}