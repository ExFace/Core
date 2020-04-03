<?php
namespace exface\Core\CommonLogic\Security\Authenticators\Traits;

use exface\Core\Interfaces\UserInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\WorkbenchInterface;

trait createUserFromTokenTrait
{

    protected function createUserFromToken(UsernamePasswordAuthToken $token, WorkbenchInterface $exface): UserInterface
    {
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
        $row = [];
        $row['USERNAME'] = $token->getUsername();
        $row['PASSWORD'] = $token->getPassword();
        $userDataSheet->addRow($row);
        $userDataSheet->dataCreate();
        $user = UserFactory::createFromUsernameOrUid($exface, $userDataSheet->getRow(0)[$userDataSheet->getMetaObject()->getUidAttributeAlias()]);
        return $user;
    }
    
    protected function addRolesToUser(WorkbenchInterface $exface, UserInterface $user, array $rolesAlias) : UserInterface
    {
        foreach ($rolesAlias as $role) {
            $user->addRoleSelector($role);
        }
        $user->exportDataSheet()->dataUpdate();
        return $user;
    }
}