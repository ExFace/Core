<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

/**
 * DEPRECATED - use MetamodelAuthenticator instead!
 * 
 * Keeps existing authenticator configurations working by using `MetamodelAuthenticator`.
 * Passwords are checked against the hashes stored in the metamodel without Symfony Security.
 * 
 * For new configurations, use:
 * 
 * ```json
 * {
 *     "class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\MetamodelAuthenticator",
 *     "id": "METAMODEL"
 * }
 * 
 * ```
 * 
 * @deprecated use \exface\Core\CommonLogic\Security\Authenticators\MetamodelAuthenticator
 * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyLdapBindAuthenticator
 */
class SymfonyAuthenticator extends MetamodelAuthenticator
{
}
