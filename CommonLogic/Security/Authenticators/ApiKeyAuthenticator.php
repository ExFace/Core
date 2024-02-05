<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Widgets\Form;
use exface\Core\CommonLogic\Security\AuthenticationToken\ApiKeyAuthToken;
use exface\Core\Factories\DataSheetFactory;

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
        $this->checkAuthenticatorDisabledForUsername($token->getUsername());
        
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'USER_API_KEY');
        $keyCol = $sheet->getColumns()->addFromExpression('KEY');
        $sheet->getColumns()->addFromExpression('USER__USERNAME');
        /* @var $keyTyp \exface\Core\DataTypes\PasswordDataType */
        $keyType = $keyCol->getDataType();
        $sheet->getFilters()->addConditionFromAttribute($keyCol->getAttribute(), $keyType->hash($token->getApiKey()));
        $sheet->dataRead();
        
        switch (true) {
            case $sheet->countRows() === 0:
                throw new AuthenticationFailedError($this, 'API key not found!');
            case $sheet->countRows() > 1:
                throw new AuthenticationFailedError($this, 'Ambiguous API key!');
            case $sheet->countRows() === 1:
                // Everything fine
                
        }
        
        $authenticatedToken = new ApiKeyAuthToken($token->getApiKey(), $sheet->getCellValue('USER__USERNAME', 0));
        
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