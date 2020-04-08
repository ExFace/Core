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
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\StringDataType;

trait CreateUserFromTokenTrait
{

    /**
     * Creates a user from the given token and saves it to the database. Returns the user.
     * 
     * @param UsernamePasswordAuthToken $token
     * @param WorkbenchInterface $exface
     * @return UserInterface
     */
    protected function createUserFromToken(UsernamePasswordAuthToken $token, WorkbenchInterface $exface, string $surname = null, string $givenname = null): UserInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
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
        $roleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE');
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $roleDataSheet->getMetaObject());
        #TODO build new RoleSelectors for each array entry, so array can contain, aliases, uids, or both
        #TODO add filter for app via APP__ALIAS
        foreach ($rolesArray as $role) {
            $roleSelector = new UserRoleSelector($exface, $role);
            if ($roleSelector->isUid()) {
                $orFilterGroup->addConditionFromString($roleDataSheet->getMetaObject()->getUidAttributeAlias(), $roleSelector->toString(), ComparatorDataType::EQUALS);
            } elseif ($roleSelector->isAlias()) {                
                if ($roleSelector->hasNamespace() === false) {
                    $orFilterGroup->addConditionFromString('ALIAS', $roleSelector->toString(), ComparatorDataType::EQUALS);
                } else {
                    $aliasFilterGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $roleDataSheet->getMetaObject());
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
        $userRoleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_USERS');
        foreach ($roleDataSheet->getRows() as $row) {
            $userRoleRow = [];
            $userRoleRow['USER'] = $user->getUid();
            $userRoleRow['USER_ROLE'] = $row[$userRoleDataSheet->getUidColumnName()];
            $userRoleDataSheet->addRow($userRoleRow);
        }
        $userRoleDataSheet->dataCreate();
        return $user;
    }
}