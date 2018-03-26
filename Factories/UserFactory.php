<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Model\User;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Workbench;

/**
 * Factory class to create Users.
 * 
 * @author SFL
 *
 */
class UserFactory extends AbstractStaticFactory
{

    /**
     * Creates a user from the passed parameters.
     * 
     * @param Workbench $exface
     * @param string $username
     * @param string $firstname
     * @param string $lastname
     * @param string $locale
     * @param string $email
     * @return User
     */
    public static function create(Workbench $exface, $username, $firstname, $lastname, $locale, $email)
    {
        $user = new User($exface);
        $user->setUsername($username);
        $user->setFirstName($firstname);
        $user->setLastName($lastname);
        $user->setLocale($locale);
        $user->setEmail($email);
        return $user;
    }

    /**
     * Creates an empty user.
     * 
     * @param Workbench $exface
     * @return User
     */
    public static function createEmpty(Workbench $exface)
    {
        return new User($exface);
    }

    /**
     * Creates an anonymous user.
     * 
     * An anonymous user is returned if the currently logged in user is requested but no
     * named user is logged in.
     * 
     * @param Workbench $exface
     * @return User
     */
    public static function createAnonymous(Workbench $exface)
    {
        return new User($exface, null, true);
    }

    /**
     * Creates a user from the passed DataSheet with 'exface.Core.USER' containing exactly one
     * row of user data.
     * 
     * @param DataSheetInterface $datasheet
     * @throws InvalidArgumentException
     * @return User
     */
    public static function createFromDataSheet(DataSheetInterface $datasheet)
    {
        if (! $datasheet->getMetaObject()->isExactly('exface.Core.USER')) {
            throw new InvalidArgumentException('Datasheet with "' . $datasheet->getMetaObject()->getAliasWithNamespace() . '" passed. Expected "exface.Core.USER".');
        }
        if ($datasheet->countRows() != 1) {
            throw new InvalidArgumentException('DataSheet with ' . $datasheet->countRows() . ' rows passed. Expected exactly one row.');
        }
        
        $exface = $datasheet->getWorkbench();
        
        $user = new User($exface, $datasheet);
        $userRow = $datasheet->getRow(0);
        foreach ($userRow as $key => $val) {
            $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal(strtolower($key));
            if (method_exists($user, $setterCamelCased)) {
                call_user_func([
                    $user,
                    $setterCamelCased
                ], $val);
            }
        }
        
        return $user;
    }
}