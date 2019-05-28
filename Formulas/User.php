<?php
namespace exface\Core\Formulas;

use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\DataTypes\StringDataType;

/**
 * Gets a property of the current user.
 * 
 * E.g. =User('username') or =User('email').
 *
 * @author Andrej Kabachnik
 *        
 */
class User extends \exface\Core\CommonLogic\Model\Formula
{

    function run($property = null)
    {
        $user = $this->getWorkbench()->getContext()->getScopeUser()->getUserCurrent();
        if ($property === null || $property === 'user_name') {
            return $user->getUsername();
        }
        switch ($property) {
            case "id":
                return $user->getUid();
            case "full_name": 
                return $user->getFirstName() . ' ' . $user->getLastName();
            default:
                $getter ='get' . StringDataType::convertCaseUnderscoreToPascal($property);
                if (method_exists($user, $getter)) {
                    return call_user_func([$user, $getter]);
                }
                throw new OutOfBoundsException('User property "' . $property . '" not found!');
        }
    }
}
?>