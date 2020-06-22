<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Authentication token for the username+password authentication via a data connection.
 * 
 * @author Ralf Mulansky
 *
 */
class DataConnectionUsernamePasswordAuthToken extends UsernamePasswordAuthToken
{
    private $dataConnectionAlias = null;
    
    /**
     * 
     * @param string $domain
     * @param string $username
     * @param string $password
     * @param FacadeInterface $facade
     */
    public function __construct(string $dataConnectionAlias, string $username, string $password, FacadeInterface $facade = null)
    {
        parent::__construct($username, $password, $facade);
        $this->dataConnectionAlias = $dataConnectionAlias;
    }
    
    /**
     * 
     * @return string
     */
    public function getDataConnectionAlias() : string
    {
        return $this->dataConnectionAlias;
    }
}