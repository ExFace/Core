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
    
    private $lifetime = null;
    
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
     * @uxon-property token_liftetime_seconds
     * @uxon-type integer
     * @uxon-default 604800
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
     * Checks if the authenticator is flagged as disabled for the given username.
     * Throws exception if it is flagged as disabled.
     * 
     * @param string $username
     * @throws AuthenticationFailedError
     * @return AbstractAuthenticator
     */
    protected function checkAuthenticatorDisabledForUsername(string $username) : AbstractAuthenticator
    {
        $exface = $this->getWorkbench();
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_AUTHENTICATOR');
        $dataSheet->getColumns()->addFromExpression('DISABLED_FLAG');
        $filterGroup = ConditionGroupFactory::createEmpty($exface, EXF_LOGICAL_AND, $dataSheet->getMetaObject());
        $filterGroup->addConditionFromString('AUTHENTICATOR_USERNAME', $username, ComparatorDataType::EQUALS);
        $filterGroup->addConditionFromString('AUTHENTICATOR', $this->getId(), ComparatorDataType::EQUALS);
        $dataSheet->getFilters()->addNestedGroup($filterGroup);
        $dataSheet->dataRead();
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
     * Writes/Updates log for successful login for this authenticator and given user and username.
     * 
     * @param UserInterface $user
     * @param string $username
     */
    protected function logSuccessfulAuthentication(UserInterface $user, string $username) : AbstractAuthenticator
    {
        $exface = $this->getWorkbench();
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.USER_AUTHENTICATOR');
        $row = [];
        $row['USER'] = $user->getUid();
        $row['AUTHENTICATOR_USERNAME'] = $username;
        $row['AUTHENTICATOR'] = $this->getId();
        $row['LAST_AUTHENTICATED_ON'] = DateTimeDataType::now();
        $dataSheet->addRow($row);
        $dataSheet->dataCreate();
        return $this;
    }
    
    /**
     * Returns data sheet with rows containing the data for user in the given token.
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
            $userFilterGroup->addConditionFromString('USERNAME', $token->getUsername(), ComparatorDataType::EQUALS);
            
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
                throw new UserDisabledError("User with the username '{$token->getUsername()}' is disabled!");
            }
            $this->userData[$token->getUsername()] = $userDataSheet;
        }        
        return $userDataSheet;
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
}