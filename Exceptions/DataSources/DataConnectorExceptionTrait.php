<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * This trait enables an exception to output data connectior specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataConnectorExceptionTrait {
    
    use ExceptionTrait {
		createWidget as createParentWidget;
	}

    private $connector = null;

    /**
     *
     * @param DataConnectionInterface $connector            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(DataConnectionInterface $connector, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setConnector($connector);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::getConnector()
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::setConnector()
     */
    public function setConnector(DataConnectionInterface $connector)
    {
        $this->connector = $connector;
        return $this;
    }
}
?>