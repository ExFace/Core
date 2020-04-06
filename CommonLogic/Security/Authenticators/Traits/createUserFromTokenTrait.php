<?php
namespace exface\Core\CommonLogic\Security\Authenticators\Traits;

use exface\Core\Interfaces\UserInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\CommonLogic\Selectors\UserSelector;

trait createUserFromTokenTrait
{

    /**
     * Creates a user from the given token and saves it to the database. Returns the user.
     * 
     * @param UsernamePasswordAuthToken $token
     * @param WorkbenchInterface $exface
     * @return UserInterface
     */
    protected function createUserFromToken(UsernamePasswordAuthToken $token, WorkbenchInterface $exface): UserInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
        $row = [];
        $row['USERNAME'] = $token->getUsername();
        $row['PASSWORD'] = $token->getPassword();
        $row['MODIFIED_BY_USER'] = UserSelector::ANONYMOUS_USER_OID;
        $row['LOCALE'] = $exface->getConfig()->getOption("LOCALE.DEFAULT");
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
     * @param array $rolesAlias
     * @return UserInterface
     */
    protected function addRolesToUser(WorkbenchInterface $exface, UserInterface $user, array $rolesAlias) : UserInterface
    {
        $roleDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE');
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $roleDataSheet->getMetaObject());
        #TODO build new RoleSelectors for each array entry, so array can contain, aliases, uids, or both
        #TODO add filter for app via APP__ALIAS
        foreach ($rolesAlias as $role) {
            $orFilterGroup->addConditionFromString('ALIAS', $role);
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