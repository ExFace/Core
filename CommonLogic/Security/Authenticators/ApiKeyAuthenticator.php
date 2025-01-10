<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Widgets\Form;
use exface\Core\CommonLogic\Security\AuthenticationToken\ApiKeyAuthToken;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\PasswordDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Security\ApiKeyAuthenticationTokenInterface;

/**
 * Compares provided API key with those stored in the `USER_API_KEY` meta object.
 * 
 * The provided tokes may or may not have a username. You can authenticate by using
 * the API key only - in this case, the username will be automatically determined.
 * 
 * After authentication the resulting authenticated token will have the correct
 * username in any case!
 * 
 * @author miriam.seitz
 *
 */
class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private $authenticatedToken = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {       
    	if (($token instanceof ApiKeyAuthenticationTokenInterface) === false){
    		throw new AuthenticationFailedError($this, 'Invalid token for this authentication. Please check configuration.', null, null, $token);
    	}
    	
    	$username = $token->getUsername();
    	$usernameVerified = null;
    	
    	// Read add tokens, that could match, with corresponding usernames
    	$keySheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_API_KEY');
    	$usernameCol = $keySheet->getColumns()->addFromExpression('USER__USERNAME');
    	$keyCol = $keySheet->getColumns()->addFromExpression('KEY_HASH');
    	
    	$keyType = $keyCol->getDataType();
    	if (($keyType instanceof PasswordDataType) === false){
    	    throw new AuthenticationFailedError($this, 'Corrupted datatype of corresponding API key. '
    	        . 'Type of requested API keys \'' . $keyType . '\'. API key type must always be of type ' . get_class(PasswordDataType::class));
    	}
    	
    	// If the token already contains a user, read tokens of this user only!
    	if (null !== $username) {
    	    $keySheet->getFilters()->addConditionFromAttribute($usernameCol->getAttribute(), $username, ComparatorDataType::EQUALS);
    	}
    	
    	// $keySheet->getFilters()->addConditionFromAttribute($key->getAttribute(), $keyType->hash($token->getApiKey()));
    	$keySheet->dataRead();
    	
    	// Check if any of the read keys match the one from the token
    	foreach ($keySheet->getRows() as $keyResult) {
    	    if ($keyType->verify($token->getApiKey(), $keyResult[$keyCol->getName()])) {
    	        if ($usernameVerified !== null) {
    	            throw new AuthenticationFailedError($this, 'Ambiguous API key provided!', null, null, $token);
    	        }
    	        $usernameVerified = $keyResult[$usernameCol->getName()];
    	    }
    	}
    	
    	// If no user could be verified, the login attempt failed
    	if ($usernameVerified === null) {    		
    		throw new AuthenticationFailedError($this, 'API key not found!', null, null, $token);
    	}
    	
    	$authenticatedToken = new ApiKeyAuthToken($token->getApiKey(), $usernameVerified, $token->getFacade());
    	$this->checkAuthenticatorDisabledForUsername($authenticatedToken->getUsername());
    	$this->authenticatedToken = $authenticatedToken;
        return $authenticatedToken;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $this->authenticatedToken === $token;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'API Keys';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool {
        return ($token instanceof ApiKeyAuthenticationTokenInterface) && $this->isSupportedFacade($token);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::createLoginForm()
     */
    protected function createLoginForm(Form $container) : Form
    {
        return $container;
    }
}