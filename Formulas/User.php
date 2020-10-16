<?php
namespace exface\Core\Formulas;

use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\FormulaError;

/**
 * Gets a property of the current user.
 * 
 * The property can be any attribute of the user's meta object along with some additional 
 * built-in properties.
 * 
 * Examples:
 * 
 * - `=User('USERNAME')` 
 * - `=User('EMAIL')`
 * - `=User('USER_ROLE__LABEL:LIST')`
 * - `=User('ID')` or `=User('UID')`
 * - `=User('NAME')` or `=User('FULL_NAME')` - first and last name
 * - `=User('INITIALS')`
 *
 * @author Andrej Kabachnik
 *        
 */
class User extends \exface\Core\CommonLogic\Model\Formula
{

    function run($property = null)
    {
        // If looking for the username, get it from the authenticated token without
        // loading user data, etc.
        if ($property === null || strcasecmp($property, 'username') === 0) {
            return $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername();
        }
        
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        switch (mb_strtoupper($property)) {
            case "ID":
            case "UID":
                return $user->getUid();
            case "USERNAME":
                return $user->getUsername();
            case "FULL_NAME":
            case "NAME":
                return $user->getName();
            case "INITIALS":
                return $user->getInitials();
            default:
                $userObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
                if ($userObj->hasAttribute($property)) {
                    $attr = $userObj->getAttribute($property);
                    if ($attr->getDataType()->isSensitiveData()) {
                        throw new FormulaError('Cannot show user property "' . $property . '" - it is concidered sensitive data!');
                    }
                    $ds = DataSheetFactory::createFromObject($userObj);
                    $ds->getColumns()->addFromExpression($property);
                    $ds->getFilters()->addConditionFromString($userObj->getUidAttributeAlias(), $user->getUid(), ComparatorDataType::EQUALS);
                    $ds->dataRead();
                    return $ds->getCellValue($property, 0);
                } else {
                    $getter ='get' . StringDataType::convertCaseUnderscoreToPascal($property);
                    if (method_exists($user, $getter)) {
                        return call_user_func([$user, $getter]);
                    }
                }
        }
        throw new OutOfBoundsException('User property "' . $property . '" not found!');
    }
}
?>