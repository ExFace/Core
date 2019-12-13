<?php

namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\UmsXmlAuthToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use exface\Core\Exceptions\Security\AuthenticationFailedError;

class UmsXmlAuthenticator implements AuthenticatorInterface
{
    const CHECKWORD = 'AbCdE13579';
    
    private $authenticatedToken = null;
    
    private $workbench = null;

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
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {
        $test = '';
        try {
            $xml = $token->getXml();
            $xmlEncoded = base64_encode(md5(self::CHECKWORD . $xml . self::CHECKWORD));
            if ($xmlEncoded === $token->getVerifyCode()) {
                $this->authenticatedToken = $token;
            } else {
                throw new AuthenticationFailedError('Invalid verifyCode!');
            }
        } catch (AuthenticationFailedError $e) {
            throw new AuthenticationFailedError($e->getMessage(), null, $e);
        }
        return $token;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getName()
     */
    public function getName(): string
    {
        return 'UmsXml Authentication';
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
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token): bool
    {
        return $this->authenticatedToken === $token;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token): bool
    {
        return $token instanceof UmsXmlAuthToken;
    }    
}