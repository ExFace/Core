<?php
namespace exface\Core\CommonLogic\Security\Authenticators\Traits;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

trait SyncRolesWithTokenTrait
{   
    private $syncRoles = false;
    
    protected function syncUserRoles(UserInterface $user, AuthenticationTokenInterface $token)
    {
        if($this->hasSyncRoles() === false){
            return;
        }
        
        $externalRolesData = $this->getExternalRolesData($token);
        $transaction = $this->getWorkbench()->data()->startTransaction();
        
        $newRolesSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_USERS');
        $newRolesSheet->getFilters()->addConditionFromString('USER', $user->getUid(), ComparatorDataType::EQUALS);
        $newRolesSheet->getFilters()->addConditionFromString('USER_ROLE__USER_ROLE_EXTERNAL__AUTHENTICATOR', $this->getId(), ComparatorDataType::EQUALS);
        $newRolesSheet->dataDelete($transaction);
        
        foreach ($externalRolesData->getRows() as $row) {
            if ($row['USER_ROLE'] !== null) {
                $newRolesSheet->addRow([
                    'USER' => $user->getUid(),
                    'USER_ROLE' => $row['USER_ROLE']
                ]);
            }
        }
        
        if($newRolesSheet->countRows() !== 0){
            $newRolesSheet->dataCreate(false, $transaction);
        }
        $transaction->commit();
    }
    
    protected function getExternalRolesData(AuthenticationTokenInterface $token) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_EXTERNAL');
        $ds->getColumns()->addMultiple([
            'UID',
            'ALIAS',
            'NAME',
            'USER_ROLE'
        ]);
        $tokenRoles = $this->getExternalRolesFromToken($token);
        if (empty($tokenRoles)) {
            return $ds;
        }
        $ds->getFilters()->addConditionFromValueArray('ALIAS', $tokenRoles);
        $ds->dataRead();
        return $ds;
    }
    
    abstract protected function getExternalRolesFromToken(AuthenticationTokenInterface $token) : array;
    
    /**
     * Set to TRUE to synchronize roles with the authentication provider
     * 
     * @uxon-property sync_roles
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return AuthenticatorInterface
     */
    protected function setSyncRoles(bool $trueOrFalse) : AuthenticatorInterface
    {
        $this->syncRoles = $trueOrFalse;
        return $this;
    }
    
    protected function hasSyncRoles() : bool
    {
        return $this->syncRoles;
    }
}