<?php
namespace exface\Core\CommonLogic\Security\Authenticators\Traits;

use exface\Core\Interfaces\UserInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
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
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

trait CreateUserFromTokenTrait
{   
    private $createNewUsers = false;
    
    private $newUsersRoles = null;
    
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
     * @return array|NULL
     */
    protected function getNewUserRoles() : ?array
    {
        return $this->newUsersRoles;
    }

    /**
     * Creates a user from the given token and saves it to the database. Returns the user.
     * 
     * @param UsernamePasswordAuthToken $token
     * @param WorkbenchInterface $exface
     * @return UserInterface
     */
    protected function createUserFromToken(WorkbenchInterface $exface, UsernamePasswordAuthToken $token, string $surname = null, string $givenname = null): UserInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER');
        $row = [];
        $row['USERNAME'] = $token->getUsername();
        $row['PASSWORD'] = $token->getPassword();
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
     * @param array $rolesArray
     * @return UserInterface
     */
    protected function addRolesToUser(WorkbenchInterface $exface, UserInterface $user, array $rolesArray) : UserInterface
    {
        $roleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_ROLE');
        $orFilterGroup = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_OR, $roleDataSheet->getMetaObject());
        foreach ($rolesArray as $role) {
            $roleSelector = new UserRoleSelector($exface, $role);
            if ($roleSelector->isUid()) {
                $orFilterGroup->addConditionFromString($roleDataSheet->getMetaObject()->getUidAttributeAlias(), $roleSelector->toString(), ComparatorDataType::EQUALS);
            } elseif ($roleSelector->isAlias()) {                
                if ($roleSelector->hasNamespace() === false) {
                    $orFilterGroup->addConditionFromString('ALIAS', $roleSelector->toString(), ComparatorDataType::EQUALS);
                } else {
                    $aliasFilterGrp = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_AND, $roleDataSheet->getMetaObject());
                    $aliasFilterGrp->addConditionFromString('APP__ALIAS', $roleSelector->getAppAlias(), ComparatorDataType::EQUALS);
                    //$roleAlias = substr($roleSelector->toString(), strlen($roleSelector->getAppAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER));
                    $roleAlias = StringDataType::substringAfter($roleSelector->toString(), $roleSelector->getAppAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);                    
                    $aliasFilterGrp->addConditionFromString('ALIAS', $roleAlias, ComparatorDataType::EQUALS);
                    $orFilterGroup->addNestedGroup($aliasFilterGrp);
                }
            }
        }
        $roleDataSheet->getFilters()->addNestedGroup($orFilterGroup);        
        $roleDataSheet->dataRead();
        if (empty($roleDataSheet->getRows())) {
            return $user;
        }
        $userRoleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_ROLE_USERS');
        foreach ($roleDataSheet->getRows() as $row) {
            $userRoleRow = [];
            $userRoleRow['USER'] = $user->getUid();
            $userRoleRow['USER_ROLE'] = $row[$userRoleDataSheet->getUidColumnName()];
            $userRoleDataSheet->addRow($userRoleRow);
        }
        $userRoleDataSheet->dataCreate();
        return $user;
    }
    
    /**
     * Creates a new user, saves in the database and adds the roles.
     * 
     * @param WorkbenchInterface $exface
     * @param UsernamePasswordAuthToken $token
     * @param string $surname
     * @param string $givenname
     * @throws AuthenticationFailedError
     * @return UserInterface
     */
    protected function createUserWithRoles(WorkbenchInterface $exface, UsernamePasswordAuthToken $token, string $surname = null, string $givenname = null) : UserInterface
    {
        $userDataSheet = $this->getUserData($exface, $token);        
        if (empty($this->getUserData($exface, $token)->getRows())) {
            try {
                $user = $this->createUserFromToken($exface, $token, $surname, $givenname);
            } catch (\Throwable $e) {
                throw new AuthenticationFailedError($this, 'User could not be created!', null, $e);
            }
            if ($this->getNewUserRoles() !== null) {
                try {
                    $user = $this->addRolesToUser($exface, $user, $this->getNewUserRoles());
                } catch (\Throwable $e) {
                    $user->exportDataSheet()->dataDelete();
                    throw new AuthenticationFailedError($this, 'User roles could not be applied!', null, $e);
                }
            }
        } else {
            $user = UserFactory::createFromUsernameOrUid($exface, $userDataSheet->getRows(0)[0][$userDataSheet->getMetaObject()->getUidAttributeAlias()]);
        }
        return $user;
    }
    
    /**
     * Returns data sheet with rows containing the data for users with same username as in the given token.
     * 
     * @param WorkbenchInterface $exface
     * @param UsernamePasswordAuthToken $token
     * @return DataSheetInterface
     */
    protected function getUserData(WorkbenchInterface $exface, UsernamePasswordAuthToken $token) : DataSheetInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER');
        $userFilterGroup = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_AND, $userDataSheet->getMetaObject());
        $userFilterGroup->addConditionFromString('USERNAME', $token->getUsername(), ComparatorDataType::EQUALS);
        $userDataSheet->getFilters()->addNestedGroup($userFilterGroup);
        $userDataSheet->dataRead();
        return $userDataSheet;
    }
}