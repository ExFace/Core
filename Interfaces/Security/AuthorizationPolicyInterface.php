<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\DataTypes\PolicyEffectDataType;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthorizationPolicyInterface extends iCanBeConvertedToUxon
{    
    public function authorize() : PermissionInterface;
    
    public function getName() : ?string;
    
    public function getEffect() : PolicyEffectDataType;
}