<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\Security\Authenticators\RememberMeAuthenticator;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\CommonLogic\Security\SecurityManager;

/**
 * Reads the current authenticator config.
 * 
 * NOTE: For security reasons this query builder cannot change the configuration!
 * 
 * @author Andrej Kabachnik
 *        
 */
class WorkbenchAuthenticatorsReader extends AbstractQueryBuilder
{
    private $authenticators = null;
    
    private $ids = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $rows = [];
        foreach ($this->getAuthenticators() as $pos => $authenticator) {
            $row = [
                'NAME' => $authenticator->getName(),
                'CLASS' => '\\' . get_class($authenticator),
                'ID' => $this->getAuthenticatorId($authenticator),
                'POSITION' => $pos
            ];
            
            $rows[] = $row;
        }
        
        $this->applyFilters($rows);
        $this->applySorting($rows);
        $totalCount = count($rows);
        $this->applyPagination($rows);
        
        return new DataQueryResultData($rows, count($rows), false, $totalCount);
    }
    
    /**
     * The PhpAnnotationsReader can only handle attributes of one object - no relations (JOINs) supported!
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $attribute->getRelationPath()->isEmpty();
    }
    
    /**
     * Same as SecurityManager::initAuthenticators().
     * 
     * The code is dublicated for security reasons - to ensure, the query build has no effect on
     * the real authenticators.
     * 
     * @see SecurityManager::initAuthenticators()
     * @return AuthenticatorInterface[]
     */
    protected function getAuthenticators() : array
    {
        if ($this->authenticators === null) {
            $this->authenticators = [];
            foreach ($this->getWorkbench()->getConfig()->getOption('SECURITY.AUTHENTICATORS') as $authConfig) {
                switch (true) {
                    case is_string($authConfig):
                        $class = $authConfig;
                        $uxon = null;
                        break;
                    case $authConfig instanceof UxonObject:
                        $class = $authConfig->getProperty('class');
                        $uxon = $authConfig->unsetProperty('class');
                        $id = $authConfig->getProperty('id');
                        break;
                    default:
                        throw new UnexpectedValueException('Invalid authenticator configuration in System.config.json: each authenticator can either be a string or an object!');
                }
                $authenticator = new $class($this->getWorkbench());
                if ($uxon !== null && $uxon->isEmpty() === false) {
                    $authenticator->importUxonObject($uxon);
                }
                $this->authenticators[] = $authenticator;
                $this->ids[] = $id;
            }
            $this->authenticators[] = new RememberMeAuthenticator($this->getWorkbench());
            $this->ids[] = 'DEFAULT_REMEMBER_ME_AUTH';
        }
        
        return $this->authenticators;
    }
    
    /**
     * 
     * @param AuthenticatorInterface $authenticator
     * @return string|NULL
     */
    protected function getAuthenticatorId(AuthenticatorInterface $authenticator) : ?string
    {
        return $this->ids[array_search($authenticator, $this->getAuthenticators())];
    }
}
?>