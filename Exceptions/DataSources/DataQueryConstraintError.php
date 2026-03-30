<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a query fails due to a constraint violation within the data source.
 *
 * This exception will generate a user-friendly error message if some additional information is provided
 * to the constructor:
 * 
 * - $obj - the object, whose data storage caused the error
 * - $attributeValues - array with attribute aliases as keys and the respective erroneous values
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataQueryConstraintError extends DataQueryFailedError implements DataConnectorExceptionInterface
{
    private DataConnectionInterface $connection;
    private ?MetaObjectInterface $obj;
    private ?array $keyValues;

    public function __construct(DataQueryInterface $query, DataConnectionInterface $connection, $message, $alias = null, $previous = null, ?MetaObjectInterface $obj = null, ?array $attributeValues = null)
    {
        $this->connection = $connection;
        $this->obj = $obj;
        $this->keyValues = $attributeValues;

        if ($obj && null !== $customMessage = $this->generateMessage($obj)) {
            $message = $customMessage;
            $this->setUseExceptionMessageAsTitle(true);
            $this->setLogLevel($this->getDefaultLogLevel());
        }

        parent::__construct($query, $message, $alias ?? $this->getDefaultAlias(), $previous);
    }

    /**
     * {@inheritDoc}
     * @see DataConnectorExceptionInterface::getConnector()
     */
    public function getConnector() : DataConnectionInterface
    {
        return $this->connection;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '8557PH7';
    }

    /**
     * @return MetaObjectInterface|null
     */
    public function getMetaObject() : ?MetaObjectInterface
    {
        return $this->obj;
    }

    /**
     * @return array|null
     */
    public function getAttributeValues() : ?array
    {
        return $this->keyValues;
    }

    protected function generateMessage(MetaObjectInterface $obj) : ?string
    {
        return null;
    }
}