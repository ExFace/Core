<?php
namespace exface\Core\CommonLogic\Security\Authenticators\Traits;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;

trait CreateUserFromTokenTrait
{   
    private $createNewUsers = null;
    
    private $newUsersRoles = [];
    
    /**
     * Set if a new PowerUI user should be created if no user with that username already exists.
     *
     * @uxon-property create_new_users
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $trueOrFalse
     * @return AuthenticatorInterface
     */
    public function setCreateNewUsers(bool $trueOrFalse) : AuthenticatorInterface
    {
        $this->createNewUsers = $trueOrFalse;
        return $this;
    }
    
    protected function getCreateNewUsers(bool $default = false) : bool
    {
        return $this->createNewUsers ?? $default;
    }
    
    /**
     * The role aliases for the roles newly created users should inherit.
     *
     * @uxon-property create_new_users_with_roles
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $create_new_users_with_roles
     * @return AuthenticatorInterface
     */
    public function setCreateNewUsersWithRoles($arrayOrUxon) : AuthenticatorInterface
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->newUsersRoles = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->newUsersRoles = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     *
     * @return array
     */
    protected function getNewUserRoles() : array
    {
        return $this->newUsersRoles;
    }

    /**
     * Creates a new workbench user in the model from the given token and data row array and returns the user.
     * 
     * The $userData parameter may contain any attributes of the object `exface.Core.USER`: e.g.
     * `['FIRST_NAME' => '...', 'LAST_NAME' => '...', 'EMAIL' => '...']`. If no $userData is passed,
     * the created user will only get a username and the system default locale. 
     * 
     * @param WorkbenchInterface $exface
     * @param AuthenticationTokenInterface $token
     * @param string[] $userData
     * @return UserInterface
     */
    protected function createUserFromToken(WorkbenchInterface $exface, AuthenticationTokenInterface $token, array $userData = []): UserInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER');
        $row = [];
        $row['USERNAME'] = $token->getUsername();
        $row['MODIFIED_BY_USER'] = UserSelector::ANONYMOUS_USER_OID;
        $row['LOCALE'] = $exface->getConfig()->getOption("SERVER.DEFAULT_LOCALE");
        $row = array_merge($row, $userData);
        $userDataSheet->addRow($row);
        $userDataSheet->dataCreate();
        $user = UserFactory::createFromUsernameOrUid($exface, $userDataSheet->getRow(0)[$userDataSheet->getMetaObject()->getUidAttributeAlias()]);
        return $user;
    }
    
    /**
     * Adds the given roles in the array to the given user, if the roles actually exist in the database.
     * 
     * @param WorkbenchInterface $exface
     * @param UserInterface $user
     * @param array $roles
     */
    protected function addRolesToUser(WorkbenchInterface $exface, UserInterface $user, array $roles) : void
    {
        $roleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_ROLE');
        $orFilterGroup = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_OR, $roleDataSheet->getMetaObject());
        foreach ($roles as $role) {
            $roleSelector = new UserRoleSelector($exface, $role);
            if ($roleSelector->isUid()) {
                $orFilterGroup->addConditionFromString($roleDataSheet->getMetaObject()->getUidAttributeAlias(), $roleSelector->toString(), ComparatorDataType::EQUALS);
            } elseif ($roleSelector->isAlias()) {                
                if ($roleSelector->hasNamespace() === false) {
                    $orFilterGroup->addConditionFromString('ALIAS', $roleSelector->toString(), ComparatorDataType::EQUALS);
                } else {
                    $aliasFilterGrp = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_AND, $roleDataSheet->getMetaObject());
                    $aliasFilterGrp->addConditionFromString('APP__ALIAS', $roleSelector->getAppAlias(), ComparatorDataType::EQUALS);
                    $roleAlias = StringDataType::substringAfter($roleSelector->toString(), $roleSelector->getAppAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);                    
                    $aliasFilterGrp->addConditionFromString('ALIAS', $roleAlias, ComparatorDataType::EQUALS);
                    $orFilterGroup->addNestedGroup($aliasFilterGrp);
                }
            }
        }
        $roleDataSheet->getFilters()->addNestedGroup($orFilterGroup);        
        $roleDataSheet->dataRead();
        if ($roleDataSheet->isEmpty() === true) {
            return;
        }
        $userRoleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_ROLE_USERS');
        foreach ($roleDataSheet->getRows() as $row) {
            $userRoleRow = [];
            $userRoleRow['USER'] = $user->getUid();
            $userRoleRow['USER_ROLE'] = $row[$userRoleDataSheet->getUidColumnName()];
            $userRoleDataSheet->addRow($userRoleRow);
        }
        $userRoleDataSheet->dataCreate();
        return;
    }
    
    /**
     * Creates a new user, saves in the database and adds the roles.
     * 
     * The `$userData` is the same as that in `createUserFromToken()`.
     * If the parameter `$roles` is `NULL` (default) the roles from the
     * `create_new_users_with_roles` configuration of the authenticator
     * will be used. Otherwise the parameter is expected to be an array
     * of role selectors (alias with namespace). An empty array would
     * force the user not to have any roles at all!
     * 
     * @see createUserFromToken()
     * 
     * @param WorkbenchInterface $exface
     * @param AuthenticationTokenInterface $token
     * @param string[] $userData
     * @param string[]|NULL $roles
     * 
     * @return UserInterface
     */
    protected function createUserWithRoles(WorkbenchInterface $exface, AuthenticationTokenInterface $token, array $userData = [], array $roles = null) : UserInterface
    {
        if ($roles === null) {
            $roles = $this->getNewUserRoles();
        }
        $user = $this->createUserFromToken($exface, $token, $userData);
        if (!empty($roles)) {
            try {
                $this->addRolesToUser($exface, $user, $roles);
            } catch (\Throwable $e) {
                $this->deleteUser($user);
                throw $e;
            }
        }
        return $user;
    }
    
    /**
     * Deletes the user from the database.
     * 
     * @param UserInterface $user
     */
    protected function deleteUser(UserInterface $user) : void
    {
        $user->exportDataSheet()->dataDelete();
        return;
    }
}