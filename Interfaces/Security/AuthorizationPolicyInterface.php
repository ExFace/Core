<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthorizationPolicyInterface extends iCanBeConvertedToUxon
{    
    public function authorize() : PermissionInterface;
}