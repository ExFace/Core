<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

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
    
    private $syncRoles = false;
    
    private $syncRolesWithDataSheet = null;
    
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
        if (null === $this->userAuthData[$username] ?? null) {
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
     * Checks if a user with the username given in the token does  already exists, if so returns true.
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
    protected function syncUserRoles(UserInterface $user, AuthenticationTokenInterface $token)
    {
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
    
    /**
     *
     * @param AuthenticationTokenInterface $token
     * @return DataSheetInterface
     */
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
    
    /**
     * Set to TRUE to synchronize roles with DataSheet
     *
     * @uxon-property sync_roles_with_data_sheet
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $trueOrFalse
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
    
    protected function getSyncRolesConfigUxon() : UxonObject
    {
        return $this->syncRolesWithDataSheet;
    }
    
    /**
     * 
     * @return array
     */
    public function getExternalSyncRoles($name) : array
    {
        $dataSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->getSyncRolesConfigUxon());
        
        // roles sync for Azure AD
        if($dataSheet->getMetaObject()->getAlias() === "meGroups") {
            // gets group IDs from attribute_alias "displayName"
            $dataSheet->dataRead();
            return $dataSheet->getColumns()->getFirst()->getValues();
        }

        // roles sync for LogBase
        if($dataSheet->getMetaObject()->getAlias() === "testLogbase") {
            $dataSheet->dataRead();
            $columns = $dataSheet->getColumns()->getAll();
            $rolesArray = [];
            foreach($columns["Aktiv"]->getValues() as $key => $activeColumn) {
                //checks if role is set to active
                if($activeColumn !== 1) {
                    continue;
                }
                // filters table on full name of authenticated user
                if($columns["Benutzername"]->getValues()[$key] === $name) {
                    // adds readable group names from attribute_alias "Rollenname" to array
                    array_push($rolesArray, $dataSheet->getColumns()->getFirst()->getValues()[$key]);
                }
            }
            return $rolesArray;
        }
        
        return null;
    }
}