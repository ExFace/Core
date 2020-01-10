<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\User;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Interfaces\WorkbenchInterface;

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
        $user = self::createEmpty($exface, $username);
        $user->setFirstName($firstname);
        $user->setLastName($lastname);
        $user->setLocale($locale);
        $user->setEmail($email);
        return $user;
    }
    
    /**
     * 
     * @param UserSelectorInterface $selector
     * @return UserInterface
     */
    public static function createFromSelector(UserSelectorInterface $selector) : UserInterface
    {
        if ($selector->isUsername() === true) {
            return static::createFromModel($selector->getWorkbench(), $selector->toString());
        }
        return $selector->getWorkbench()->model()->getModelLoader()->loadUser($selector);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @return UserInterface
     */
    public function createFromUsernameOrUid(WorkbenchInterface $workbench, string $selectorString) : UserInterface
    {
        return static::createFromSelector(new UserSelector($workbench, $selectorString));
    }

    /**
     * Creates an empty user.
     * 
     * @param Workbench $exface
     * @param string $username
     * @return User
     */
    public static function createEmpty(Workbench $exface, string $username)
    {
        if ($username === '') {
            throw new UserNotFoundError('Empty username not allowed!');
        }
        
        return new User($exface, $username);
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
        return new User($exface);
    }
    
    public static function createFromModel(Workbench $workbench, string $username) : UserInterface
    {
        if ($username === '') {
            throw new UserNotFoundError('Empty username not allowed!');
        }
        
        return new User($workbench, $username, $workbench->model()->getModelLoader());
    }
}