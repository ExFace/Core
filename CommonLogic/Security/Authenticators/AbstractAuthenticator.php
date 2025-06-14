<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\UserInterface;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Factories\UserFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\UserDisabledError;
use exface\Core\DataTypes\RegularExpressionDataType;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Widgets\Form;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Security\AuthenticationRuntimeError;

/**
 * Provides common base function for authenticators.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractAuthenticator implements AuthenticatorInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    
    use TranslatablePropertyTrait;
    
    private $workbench = null;
    
    private $name = null;
    
    private $id = null;
    
    private $userData = [];      
    
    private $userAuthData = [];
    
    private $lifetime = null;
    
    private $lifetimeRefreshInterval = null;
    
    private $disabled = false;
    
    private $usernameReplaceChars = [];
    
    private $onlyForFacades = [];
    
    private $syncRolesWithDataSheet = null;
    
    private $hideLoginForm = false;

    /**
     *
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            'name' => $this->getName()
        ]);
    }
    
    /**
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name !== null ? $this->evaluatePropertyExpression($this->name) : $this->getNameDefault();
    }
    
    /**
     * The name of the authentication method will be shown on the login-screen and in error messages and traces.
     *
     * Use the `=TRANSLATE()` formula to make the name translatable.
     *
     * @uxon-property name
     * @uxon-type string
     *
     * @param string $value
     * @return AbstractAuthenticator
     */
    protected function setName(string $value) : AbstractAuthenticator
    {
        $this->name = $value;
        return $this;
    }
    
    /**
     * Returns the default name of the authenticator (if no name was set in it's configuration).
     *
     * @return string
     */
    abstract protected function getNameDefault() : string;
    
    /**
     * 
     * @throws RuntimeException
     * @return string
     */
    protected function getId() : string
    {
        if ($this->id === null) {
            throw new RuntimeException('Missing "id" in authenticator configuration!');
        }
        return $this->id;
    }
    
    /**
     * Unique identifier for this authenticator configuration.
     *
     * Each item in the config option `SECURITY.AUTHENTICATORS` must have a unique id!
     *
     * @uxon-property id
     * @uxon-type string
     *
     * @param string $id
     * @return AbstractAuthenticator
     */
    protected function setId(string $id) : AbstractAuthenticator
    {
        $this->id = $id;
        return $this;
    }
    
    /**
     * How long should a successful authentication be valid (in seconds).
     *
     * After this amount of seconds the user will be asked to log in again. The token
     * lifetime can be set for every authenticator individually. If it is not, the token
     * lifetime of the `RememberMeAuthenticator` will be used.
     *
     * @uxon-property token_lifetime_seconds
     * @uxon-type integer
     *
     * @param int $seconds
     * @return AbstractAuthenticator
     */
    protected function setTokenLifetimeSeconds(int $seconds) : AbstractAuthenticator
    {
        $this->lifetime = $seconds;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getTokenLifetime()
     */
    public function getTokenLifetime(AuthenticationTokenInterface $token) : ?int
    {
        return $this->lifetime;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getTokenRefreshInterval()
     */
    public function getTokenRefreshInterval() : ?int
    {
        return $this->lifetimeRefreshInterval;
    }
    
    /**
     * Number of seconds after which the token lifetime will be extended.
     *
     * This option allows to extend the token while the user is active. Thus, the token would expire only
     * after a period of inactivity longer than `token_lifetime`. If the user is authenticated sooner, the
     * lifetime counter will be refreshed.
     *
     * If set to 0, the lifetime is concidere absolute. Thus, the user will be logged out after `token_lifetime`
     * regardless of his actual activity.
     *
     * @uxon-property token_refresh_interval
     * @uxon-type integer
     *
     * @param int $seconds
     * @return AbstractAuthenticator
     */
    protected function setTokenRefreshInterval(int $seconds) : AbstractAuthenticator
    {
        $this->lifetimeRefreshInterval = $seconds;
        return $this;
    }
    
    /**
     * Set TRUE to not show the login form of the authenticator even so it is enabled.
     * That could be needed if you need a login with username and password for a specific facade
     * but do not want the login mask to be shown for the users.
     *
     * @param bool $values
     * @return AbstractAuthenticator
     */
    protected function setHideLoginForm(bool $values) : AbstractAuthenticator
    {
        $this->hideLoginForm = $values;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getHideLoginForm()
     */
    public function getHideLoginForm() : bool
    {
        return $this->hideLoginForm;
    }
    
    /**
     * Disable the authenticator, it won't be possible to login anymore using this authenticator and
     * it won't be shown in the login dialog.
     *
     * @param bool $values
     * @return AbstractAuthenticator
     */
    protected function setDisabled(bool $values) : AbstractAuthenticator
    {
        $this->disabled = $values;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }
    
    /**
     * Checks if the authenticator is flagged as disabled for the given username.
     * Throws exception if it is flagged as disabled.
     *
     * @param string $username
     * @throws AuthenticationFailedError
     * @return AbstractAuthenticator
     */
    protected function checkAuthenticatorDisabledForUsername(string $username) : AbstractAuthenticator
    {
        $dataSheet = $this->getAuthenticatorData($username);
        if ($dataSheet->isEmpty() === true) {
            return $this;
        }
        foreach ($dataSheet->getRows() as $row) {
            if (BooleanDataType::cast($row['DISABLED_FLAG']) === true) {
                throw new AuthenticationFailedError($this, "Authentication failed. Authenticator '{$this->getName()}' disabled for username '$username'!", '7AL3J9X');
            }
        }
        return $this;
    }
    
    /**
     * Returns a data sheet with the current user-authenticator configuration for the username AND this authenticator
     *
     * NOTE: the $username here refers to the username in the authenticator configuration, not
     * the user!
     *
     * @param string $username
     * @return DataSheetInterface
     */
    protected function getAuthenticatorData(string $username) : DataSheetInterface
    {
        if (null === ($this->userAuthData[$username] ?? null)) {
            $exface = $this->getWorkbench();
            $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_AUTHENTICATOR');
            $dataSheet->getColumns()->addMultiple([
                'DISABLED_FLAG',
                'PROPERTIES_UXON'
            ]);
            $dataSheet->getFilters()
            ->addConditionFromString('AUTHENTICATOR_USERNAME', $username, ComparatorDataType::EQUALS)
            ->addConditionFromString('AUTHENTICATOR', $this->getId(), ComparatorDataType::EQUALS);
            $dataSheet->dataRead();
            $this->userAuthData[$username] = $dataSheet;
        }
        return $this->userAuthData[$username];
    }
    
    /**
     * Writes/Updates log for successful login for this authenticator and given user and username.
     *
     * @param UserInterface $user
     * @param string $username
     */
    protected function logSuccessfulAuthentication(UserInterface $user, string $username, UxonObject $properties = null) : AbstractAuthenticator
    {
        $dataSheet = $this->getAuthenticatorData($username);
        $row = [
            'USER' => $user->getUid(),
            'AUTHENTICATOR_USERNAME' => $username,
            'AUTHENTICATOR' => $this->getId(),
            'LAST_AUTHENTICATED_ON' => DateTimeDataType::now()
        ];
        if ($properties !== null) {
            $row['PROPERTIES_UXON'] = $properties->isEmpty() ? null : $properties->toJson();
        }
        if ($dataSheet->isEmpty()) {
            $dataSheet->addRow($row);
            $dataSheet->dataCreate(false);
        } else {
            foreach ($row as $col => $val) {
                $dataSheet->setCellValue($col, 0, $val);
            }
            $dataSheet->dataUpdate();
        }
        
        return $this;
    }
    
    /**
     * Returns a data sheet with rows containing the data for user in the given token.
     *
     * @param WorkbenchInterface $exface
     * @param AuthenticationTokenInterface $token
     * @return DataSheetInterface
     */
    protected function getUserData(AuthenticationTokenInterface $token) : DataSheetInterface
    {
        $userDataSheet = $this->userData[$token->getUsername()];
        if ($userDataSheet === null) {
            $exface = $this->getWorkbench();
            $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER');
            $userDataSheet->getColumns()->addFromExpression('DISABLED_FLAG');
            $userFilterGroup = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_OR, $userDataSheet->getMetaObject());
            $userFilterGroup->addConditionFromString('USERNAME', $this->getUsernameInWorkbench($token), ComparatorDataType::EQUALS);
            
            //add filters to check if username already exists in USER_AUTHENTICATOR data
            $andFilterGroup = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_AND, $userDataSheet->getMetaObject());
            $andFilterGroup->addConditionFromString('USER_AUTHENTICATOR__AUTHENTICATOR_USERNAME', $token->getUsername(), ComparatorDataType::EQUALS);
            $andFilterGroup->addConditionFromString('USER_AUTHENTICATOR__AUTHENTICATOR', $this->getId(), ComparatorDataType::EQUALS);
            
            $userFilterGroup->addNestedGroup($andFilterGroup);
            $userDataSheet->getFilters()->addNestedGroup($userFilterGroup);
            $userDataSheet->dataRead();
            if ($userDataSheet->isEmpty()) {
                throw new UserNotFoundError("No user found matching the username '{$token->getUsername()}'!");
            }
            if (BooleanDataType::cast($userDataSheet->getRow(0)['DISABLED_FLAG']) === true) {
                throw new UserDisabledError("User with the username '{$this->getUsernameInWorkbench($token)}' is disabled!");
            }
            $this->userData[$token->getUsername()] = $userDataSheet;
        }
        return $userDataSheet;
    }
    
    /**
     *
     * @param AuthenticationTokenInterface $token
     * @return string
     */
    protected function getUsernameInWorkbench(AuthenticationTokenInterface $token) : string
    {
        $username = $token->getUsername();
        foreach ($this->getUsernameReplaceCharacters() as $exp => $repl) {
            if (RegularExpressionDataType::isRegex($exp)) {
                $username = preg_replace($exp, $repl, $username);
            } else {
                $username = str_replace($exp, $repl, $username);
            }
        }
        return $username;
    }
    
    /**
     * Checks if a user with the username given in the token does already exist, if so returns true.
     *
     * @param WorkbenchInterface $exface
     * @param AuthenticationTokenInterface $token
     * @return bool
     */
    protected function userExists(AuthenticationTokenInterface $token) : bool
    {
        try {
            $this->getUserData($token);
            return true;
        } catch (UserNotFoundError $e) {
            return false;
        }
    }
    
    /**
     * Returns a user matching the username in the token. Throws exception if no such user exists.
     *
     * @param AuthenticationTokenInterface $token
     * @throws UserNotFoundError
     * @return UserInterface
     */
    protected function getUserFromToken(AuthenticationTokenInterface $token) : UserInterface
    {
        $userDataSheet = $this->getUserData($token);
        $user = UserFactory::createFromUsernameOrUid($this->getWorkbench(), $userDataSheet->getRow(0)['UID']);
        return $user;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getUsernameReplaceCharacters() : array
    {
        return $this->usernameReplaceChars;
    }
    
    /**
     * Characters or regular expressions to replace in the username from the authentication provider
     *
     * Examples:
     *
     * - `{"\/@.*\/": ""}` to extract the username from an email address (the part before `@`)
     *
     * @uxon-property username_replace_characters
     * @uxon-type object
     * @uxon-template {"string or regex": "replacement"}
     *
     * @param UxonObject $value
     * @return AbstractAuthenticator
     */
    protected function setUsernameReplaceCharacters(UxonObject $value) : AbstractAuthenticator
    {
        $this->usernameReplaceChars = $value->toArray();
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        $loginForm = WidgetFactory::create($container->getPage(), 'Form', $container);
        $loginForm->setObjectAlias('exface.Core.LOGIN_DATA');
        $loginForm->setCaption($this->getName());
        try {
            $loginForm = $this->createLoginForm($loginForm);
        } catch (\Throwable $e) {
            if (! ($e instanceof ExceptionInterface)) {
                $e = new InternalError($e->getMessage(), null, $e);
            }
            $loginForm->addWidget(WidgetFactory::createFromUxonInParent($loginForm, new UxonObject([
                'widget_type' => 'Message',
                'type' => 'error',
                'text' => 'Failed to initialize authenticator "' . $this->getName() . '": see log ID "' . $e->getId() . '" for details!'
            ])));
            $this->getWorkbench()->getLogger()->logException($e);
        }
        if ($loginForm->isEmpty() === false) {
            $container->addForm($loginForm);
        }
        return $container;
    }
    
    /**
     *
     * @param Form $emptyForm
     * @return Form
     */
    protected function createLoginForm(Form $emptyForm) : Form
    {
        return $emptyForm;
    }
    
    /**
     *
     * @param AuthenticationTokenInterface $token
     * @return bool
     */
    protected function isSupportedFacade(AuthenticationTokenInterface $token) : bool
    {
        if (empty($this->onlyForFacades)) {
            return true;
        }
        
        if (null === $facade = $token->getFacade()) {
            return false;
        }
        
        foreach ($this->getOnlyForFacades() as $selector) {
            if ($facade->isExactly($selector)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     *
     * @return array
     */
    protected function getOnlyForFacades() : array
    {
        return $this->onlyForFacades;
    }
    
    /**
     * Array of facade selectors (alias, path or class name), that are allowed to use this authenticator
     *
     * @uxon-property only_for_facades
     * @uxon-type metamodel:facade[]
     *
     * @param UxonObject $value
     * @return AbstractAuthenticator
     */
    protected function setOnlyForFacades($uxonOrArrayOfSelectors) : AbstractAuthenticator
    {
        switch (true) {
            case $uxonOrArrayOfSelectors instanceof UxonObject:
                $array = $uxonOrArrayOfSelectors->toArray();
                break;
            case is_array($uxonOrArrayOfSelectors):
                $array = $uxonOrArrayOfSelectors;
                break;
            default:
                throw new InvalidArgumentException('Invalid argument supplied for setOnlyForFacades() in ' . get_class($this) . ': expecting UXON or array, received ' . gettype($uxonOrArrayOfSelectors));
        }
        
        $this->onlyForFacades = $array;
        return $this;
    }
    
    /**
     *
     * @param UserInterface $user
     * @param AuthenticationTokenInterface $token
     */
    protected function syncUserRoles(UserInterface $user, AuthenticationTokenInterface $token) : AuthenticatorInterface
    {
        if ($this->hasSyncRoles() === false) {
            return $this;
        }

        $logbook = new DataLogBook($this->getName());
        
        try {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            
            // Get external roles the user should have according to the remote
            $externalRolesData = $this->getExternalRolesForUser($user, $token);
            $externalRoles = $externalRolesData->getRows();
            $externalRoleUids = array_column($externalRoles, 'USER_ROLE');
            $logbook->addDataSheet('External roles', $externalRolesData);
            $logbook->addLine('Received ' . $externalRolesData->countRows() . ' external roles');
            
            // Get current workbench user-role-relations, that were added by this authenticator previously
            $localRolesSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_EXTERNAL');
            $localRolesCol = $localRolesSheet->getColumns()->addFromExpression('USER_ROLE__USER_ROLE_USERS__UID:LIST_DISTINCT');
            $keepManualRolesFlagCol = $localRolesSheet->getColumns()->addFromExpression('KEEP_MANUAL_ASSIGNMENTS_FLAG');
            // Filter: ext. roles, that have connections with this user
            $localRolesSheet->getFilters()->addConditionFromString('USER_ROLE__USER_ROLE_USERS__USER', $user->getUid(), ComparatorDataType::EQUALS);
            // AND ext. role belongs to this authenticator
            $localRolesSheet->getFilters()->addConditionFromString('AUTHENTICATOR', $this->getId(), ComparatorDataType::EQUALS);
            // AND ext. role is active
            $localRolesSheet->getFilters()->addConditionFromString('ACTIVE_FLAG', 1, ComparatorDataType::EQUALS);

            /* Example:
             * User 1 has Role1, Role2, Role3, Role 4
             * Role1 is a logical role (e.g. Admin)
             * Role2 is synced with authenticator 1 external role ExtRole1
             * Role3 is synced with authenticator 1 external role ExtRole2 BUT DISABLED!
             * Role4 is synced with authenticator 2
             * 
             * Cases:
             * 1. When syncing with authenticator 1, it provides ExtRole1 and ExtRole2
             *   - Nothing happens, the user keeps all local roles
             * 2. When syncing with authenticator 1, it provides ExtRole1 only
             *   - Nothing happens because the mapping to ExtRole2 is inactive, thus Role2 is
             *   basically concidered local-only
             * 3. When syncing with authenticator 1, it provides no roles at all
             *   - User loses Role1, but keeps all other roles - in particular Role2 because
             *   the ExtRole2 mapping is disabled
             * 4. The ExtRole2 mapping is set active and the user is synced with authenticator 1
             *   - User loses Role2 because it is now actively synced. 
             *   See more specific scenarios for local/manual roles below.
            */

            $logbook->addLine('Looking for local roles with the following filters: `' . $localRolesSheet->getFilters()->__toString() . '`');
            
            // Delete roles assigned by this sync previously
            $deleteSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_USERS');
            $deleteCol = $deleteSheet->getColumns()->addFromUidAttribute();
            $deleteUids = [];
            $checkUids = [];
            $localRolesSheet->dataRead();
            $logbook->addDataSheet('Local rows', $localRolesSheet);
            $logbook->addLine('Found ' . $localRolesSheet->countRows() . ' local roles');

            /*
             * Scenarios for local/manually assigned roles:
             * a) Keep manual assignments:
             *    If local/manually assigned user roles are set to be kept with synchronization, the role is NOT added to $deleteSheet.
             *    Thereby the role will not be removed with the next synchronization even if the user does not have the matching external role.
             * b) Synchronize manual assignments:
             *    If local/manually assigned user roles are set to be synchronized aswell, the role is added to $deleteSheet to be deleted before synchronizing external roles.
             *    Thereby the role will only be kept if it can be synchronized with matching external roles.
             */
            foreach ($localRolesSheet->getRows() as $key => $row) {
                // if KEEP_MANUAL_ASSIGNMENTS_FLAG is set to 1, then add this role to checkUids for further checks below
                if ($keepManualRolesFlagCol->getValue($key) == 1) {
                    foreach ($localRolesCol->getAttribute()->explodeValueList($localRolesCol->getValue($key)) as $uid) {
                        $checkUids[] = $uid;
                    }
                }
                // if KEEP_MANUAL_ASSIGNMENTS_FLAG is set to 0, then delete the role no matter if its a local or external role
                else {
                    foreach ($localRolesCol->getAttribute()->explodeValueList($localRolesCol->getValue($key)) as $uid) {
                        $deleteUids[] = $uid;
                    }
                }
            }
            
            // Filter the roles in $checkUids by the AUTHENTICATOR_ID
            // a) if no AUTHENTICATOR_ID is set, then it is a local/manually assigned role. 
            // Then it should ONLY be added to $deleteUids if the role UID matches any role UID inside $externalRolesData. 
            // Otherwise it is kept instead of deleted.
            // Scenario 1:
            // "Role A" is set as a local/manually assigned role but "Role A" is not found in the external roles to be synchronized.
            // In this case the role is NOT added to $deleteUids to keep the role as a local/manually assigned role.
            // Scenario 2:
            // "Role B" is set as a  local/manually assigned role and "Role B" is also found in the external roles to be sychronized.
            // In this case the role is added to $deleteUids to overwrite the local/manually assigned role with the external synchronized role.
            // b) if AUTHENTICATOR_ID is equal to the current authenticator used, then it is an external role and it should be added to $deleteUids to be deleted.
            if (! empty($checkUids)) {
                $checkSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_USERS');
                $checkSheet->getColumns()->addFromExpression('AUTHENTICATOR_ID');
                $checkSheet->getColumns()->addFromExpression('USER_ROLE');
                $checkCol = $checkSheet->getColumns()->addFromUidAttribute();
                $checkCol->setValues($checkUids);
                $checkSheet->getFilters()->addConditionFromColumnValues($checkCol);
                $checkSheet->getFilters()->addConditionFromValueArray('AUTHENTICATOR_ID', [$this->getId(), null]);
                $checkSheet->dataRead();     

                
                foreach ($checkSheet->getRows() as $internalRow) {
                    // see b) as described in the comment above
                if ($internalRow["AUTHENTICATOR_ID"] === $this->getId()) {
                        $deleteUids[] = $internalRow["UID"];
                    }
                // see a) as described in the comment above
                else if ($internalRow["AUTHENTICATOR_ID"] == null && in_array($internalRow["USER_ROLE"], $externalRoleUids)) {
                        $deleteUids[] = $internalRow["UID"];
                    }
                }
            }

            $deleteUids = array_unique($deleteUids);
            $deleteCol->setValues($deleteUids);
            $logbook->addDataSheet('Local roles to delete', $deleteSheet);
            if ($deleteSheet->countRows() !== 0) {
                $deleteSheet->dataDelete($transaction);
            }
            
            // Add roles matching the current external roles (see above) 
            $newRolesSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_USERS');
            foreach ($externalRoles as $row) {
                if ($row['USER_ROLE'] !== null) {
                    $newRolesSheet->addRow([
                        'USER' => $user->getUid(),
                        'USER_ROLE' => $row['USER_ROLE'],
                        'AUTHENTICATOR_ID' => $this->getId()
                    ]);
                }
            }
            $logbook->addDataSheet('Local roles to add', $newRolesSheet);
            if($newRolesSheet->countRows() !== 0){
                $newRolesSheet->dataCreate(false, $transaction);
            }
            $this->getWorkbench()->getLogger()->notice('Authentication roles synced for user ' . $user->getUsername() . ' with authenticator ' . $this->getName(), [], $logbook);
            $transaction->commit();
        } catch (\Throwable $e) {
            // If roles cannot be synced, do not stop the authentication!
            $this->getWorkbench()->getLogger()->logException(new AuthenticationRuntimeError($this, 'Cannot sync roles for authenticator "' . $this->getId() . '": ' . $e->getMessage(), null, $e));
        }
        
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function hasSyncRoles() : bool
    {
        return $this->hasSyncRolesWithDataSheet();
    }
    
    /**
     * Returns a data sheet of exface.Core.USER_ROLE_EXTERNAL, that the user should have according to the authentication provider
     * 
     * @param AuthenticationTokenInterface $token
     * @return DataSheetInterface
     */
    protected function getExternalRolesForUser(UserInterface $user, AuthenticationTokenInterface $token) : DataSheetInterface
    {
        // Read remote roles from the authenticator
        $currentRemoteRoleNames = $this->getExternalRolesFromRemote($user, $token);
        
        // Now read local external role mappings matching those remote roles
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_ROLE_EXTERNAL');
        $ds->getColumns()->addMultiple([
            'UID',
            'ALIAS',
            'NAME',
            'USER_ROLE'
        ]);
        if (empty($currentRemoteRoleNames)) {
            return $ds;
        }
        $ds->getFilters()->addConditionFromValueArray('ALIAS', $currentRemoteRoleNames);
        $ds->getFilters()->addConditionFromString('ACTIVE_FLAG', 1, ComparatorDataType::EQUALS);
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * Reads the roles ids the user should have from the remote authentication provider.
     * 
     * These ids will later be matched against the ALIAS of the external role mappings in the metamodel
     * 
     * @param AuthenticationTokenInterface $token
     * @return string[]
     */
    protected function getExternalRolesFromRemote(UserInterface $user, AuthenticationTokenInterface $token) : array
    {
        $roleSyncUxon = $this->getSyncRolesWithDataSheetUxon();
        
        $uxonString = $roleSyncUxon->toJson();
        $phs = StringDataType::findPlaceholders($uxonString);
        $phVals = [];
        foreach ($phs as $ph) {
            $phVals[$ph] = $user->getAttribute($ph);
        }
        $uxonString = StringDataType::replacePlaceholders($uxonString, $phVals);
        $roleSyncUxon = UxonObject::fromJson($uxonString);
        
        if ($roleSyncUxon === null) {
            return [];
        }
        
        $dataSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $roleSyncUxon);
        $firstCol = $dataSheet->getColumns()->getFirst();
        $dataSheet->dataRead();
        return $firstCol->getValues();
    }
    
    /**
     * Define a data sheet to select roles assigned to the user to sync them with workbench roles.
     * 
     * If a data sheet is specified here, it will be read each time the user logs in. The user
     * roles assigned in the workbench will be overwritten with those read from this data sheet.
     * This will only be the case for roles assigned through the sync in this authenticator. Any
     * roles assigned explicitly in the workbench configuration will remain untouched. 
     * 
     * The role names returned by this data sheet will be matched agains the external roles
     * configuration for this authenticator.
     * 
     * The data sheet definition may contain placeholders. Any attribute of the `exface.Core.USER`
     * object is available: e.g. `USERNAME`, `EMAIL` as well as related attribute.
     * 
     * ## Example
     * 
     * ```
     *  {
     *     "class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\SQLAuthenticator",
     *     "sync_roles_with_data_sheet": {
     *         "object_alias": "my.App.ROLE",
     *		   "columns": [
     *	           {
     *				  "attribute_alias": "Name"
     *			   }
     *			],
     *			"filters": {
     *				"operator": "AND",
     *				"conditions": [
     *					{
     *						"expression": "RELATION_TO__USER_TABLE",
     *						"comparator": "==",
     *						"value": "[#USERNAME#]"
     *					},
     *					{
     *						"expression": "ACTIVE_FLAG",
     *						"comparator": "==",
     *						"value": "1"
     *					}
     *				]
     *			}
     *		}
     *  },
     * 
     * ```
     *
     * @uxon-property sync_roles_with_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "columns": [{"attribute_alias": ""}]}
     *
     * @param UxonObject $uxonObject
     * @return AuthenticatorInterface
     */
    protected function setSyncRolesWithDataSheet(UxonObject $uxonObject) : AuthenticatorInterface
    {
        $this->syncRolesWithDataSheet = $uxonObject;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    protected function hasSyncRolesWithDataSheet() : bool
    {
        if($this->syncRolesWithDataSheet !== null) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    protected function getSyncRolesWithDataSheetUxon() : ?UxonObject
    {
        return $this->syncRolesWithDataSheet;
    }
}