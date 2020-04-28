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
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Model\MetaObject;

trait CreateUserFromTokenTrait
{   
    private $createNewUsers = false;
    
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
    
    protected function getCreateNewUsers() : bool
    {
        return $this->createNewUsers;
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
     * Creates a user from the given token and saves it to the database. Returns the user.
     * 
     * @param AuthenticationTokenInterface $token
     * @param WorkbenchInterface $exface
     * @return UserInterface
     */
    protected function createUserFromToken(WorkbenchInterface $exface, AuthenticationTokenInterface $token, string $surname = null, string $givenname = null): UserInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER');
        $row = [];
        $row['USERNAME'] = $token->getUsername();
        $row['MODIFIED_BY_USER'] = UserSelector::ANONYMOUS_USER_OID;
        $row['LOCALE'] = $exface->getConfig()->getOption("LOCALE.DEFAULT");
        if ($surname !== null) {
            $row['LAST_NAME'] = $surname;
        }
        if ($givenname !== null) {
            $row['FIRST_NAME'] = $givenname;
        }
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
     * @param WorkbenchInterface $exface
     * @param AuthenticationTokenInterface $token
     * @param string $surname
     * @param string $givenname
     * 
     * @return UserInterface
     */
    protected function createUserWithRoles(WorkbenchInterface $exface, AuthenticationTokenInterface $token, string $surname = null, string $givenname = null, array $roles = null) : UserInterface
    {
        if ($roles === null) {
            $roles = $this->getNewUserRoles();
        }
        $user = $this->createUserFromToken($exface, $token, $surname, $givenname);
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