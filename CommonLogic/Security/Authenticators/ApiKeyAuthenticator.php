<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Widgets\Form;
use exface\Core\CommonLogic\Security\AuthenticationToken\ApiKeyAuthToken;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\PasswordDataType;

/**
 * Authenticates API keys against registered API Keys within PowerUI.
 * 
 * Initial ApiKeyAuthToken has no username. 
 * The username is loaded from the registered API Key (User who registered API key) and set within authenticate.
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
    	if ($token instanceof ApiKeyAuthToken === false){
    		throw new AuthenticationFailedError($this, 'Invalid token for this authentication. Please check configuration.');
    	}
    	
    	$correspondingApiKey = $this->getCorrespondingApiKey($token);
    	if ($correspondingApiKey === null) {    		
    		throw new AuthenticationFailedError($this, 'API key not found!');
    	}
    	
    	$authenticatedToken = new ApiKeyAuthToken($token->getApiKey(), $correspondingApiKey['USER__USERNAME']);
    	$this->checkAuthenticatorDisabledForUsername($authenticatedToken->getUsername());
        return $authenticatedToken;
    }
    
	/**
	 * Loads datasheet of all registered api keys and trys to verify token.
	 * 
	 * @param AuthenticationTokenInterface $token
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 * @throws AuthenticationFailedError
	 */
    private function getCorrespondingApiKey(AuthenticationTokenInterface $token) : ?array
	{
		$keys = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_API_KEY');
		$key = $keys->getColumns()->addFromExpression('KEY_HASH');
		$keys->getColumns()->addFromExpression('USER__USERNAME');
        $keyType = $key->getDataType();
        
        if ($keyType instanceof PasswordDataType === false){
        	throw new AuthenticationFailedError($this, 'Corrupted datatype of corresponding API key. '
        		. 'Type of requested API keys \'' . $keyType . '\'. API key type must always be of type ' . get_class(PasswordDataType::class));
        }
        
        // $keyResult->getFilters()->addConditionFromAttribute($key->getAttribute(), $keyType->hash($token->getApiKey()));
        $keys->dataRead();
        foreach ($keys->getRows() as $keyResult) {
        	if ($keyType->verify($token->getApiKey(), $keyResult[$key->getAttribute()->getAlias()]))
        		return $keyResult;
        }
        
        return null;
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
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.SIGN_IN');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool {
        return ($token instanceof ApiKeyAuthToken) && $this->isSupportedFacade($token);
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