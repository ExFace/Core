<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\User;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Factory class to instantiate workbench users
 * 
 * @author Andrej Kabachnik
 *
 */
class UserFactory extends AbstractStaticFactory
{  
    /**
     * Instantiates a user from the given selector.
     * 
     * If `$checkModel` is false (default), model data of the user will not be loaded right away - only if
     * it is required when reading corresponding user data. Use this option to lazily instatiate users, that
     * might not be needed in further processing. But keep in mind, that an unverified user might not even
     * exist!
     * 
     * @param UserSelectorInterface $selector
     * @param array $constructorArguments
     * @param bool $checkModel
     * 
     * @return UserInterface
     */
    public static function createFromSelector(UserSelectorInterface $selector, array $constructorArguments = null, bool $checkModel = false) : UserInterface
    {
        if ($checkModel === false && $selector->isUsername()) {
            $user = static::createFromUsername($selector->getWorkbench(), $selector->__toString(), false);
            unset($selector);
            return $user;
        }
        return static::createFromModel($selector->getWorkbench(), $selector);
    }
    
    /**
     * Instantiates a user from the given username
     * 
     * If `$checkModel` is false (default), model data of the user will not be loaded right away - only if
     * it is required when reading corresponding user data. Use this option to lazily instatiate users, that
     * might not be needed in further processing. But keep in mind, that an unverified user might not even
     * exist!
     * 
     * @param WorkbenchInterface $workbench
     * @param string $username
     * @param bool $checkModel
     * 
     * @return UserInterface
     */
    public static function createFromUsername(WorkbenchInterface $workbench, string $username, bool $checkModel = false)
    {
        if ($checkModel === false) {
            return new User($workbench, $username, $workbench->model()->getModelLoader());
        }
        return static::createFromModel($workbench, $username);
    }
    
    /**
     * Instatiates a user from the give UID or username as string
     * 
     * If `$checkModel` is false (default), model data of the user will not be loaded right away - only if
     * it is required when reading corresponding user data. Use this option to lazily instatiate users, that
     * might not be needed in further processing. But keep in mind, that an unverified user might not even
     * exist!
     * 
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @param bool $checkModel
     * 
     * @return UserInterface
     */
    public static function createFromUsernameOrUid(WorkbenchInterface $workbench, string $selectorString, bool $checkModel = false) : UserInterface
    {
        return static::createFromSelector(new UserSelector($workbench, $selectorString), null, $checkModel);
    }

    /**
     * Creates an empty user without a link to the meta model.
     * 
     * @param WorkbenchInterface $exface
     * @param string $username
     * @return UserInterface
     */
    public static function createEmpty(WorkbenchInterface $exface, string $username) : UserInterface
    {
        if ($username === '') {
            throw new UserNotFoundError('Empty username not allowed!');
        }
        
        return new User($exface, $username);
    }

    /**
     * Instantiates an anonymous user.
     * 
     * An anonymous user is returned if the currently logged in user is requested but no
     * named user is logged in.
     * 
     * @param Workbench $exface
     * @return UserInterface
     */
    public static function createAnonymous(Workbench $exface) : UserInterface
    {
        return new User($exface, null, $exface->model()->getModelLoader());
    }
    
    /**
     * Instatiates a user loading the respective data from the metamodel
     * 
     * @param WorkbenchInterface $workbench
     * @param string|UserSelectorInterface $usernameOrUidOrSelector
     * @throws UserNotFoundError
     * 
     * @return UserInterface
     */
    public static function createFromModel(WorkbenchInterface $workbench, $usernameOrUidOrSelector) : UserInterface
    {
        if (! $usernameOrUidOrSelector instanceof UserSelectorInterface) {
            if ($usernameOrUidOrSelector === '' || $usernameOrUidOrSelector === null) {
                throw new UserNotFoundError('Invalid (empty) user selector!');
            }
            $selector = new UserSelector($workbench, $usernameOrUidOrSelector);
        } else {
            $selector = $usernameOrUidOrSelector;
        }
        
        return $selector->getWorkbench()->model()->getModelLoader()->loadUserData($selector);
    }
}