<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Widgets\DebugMessage;

/**
 * This trait enables an exception to output data query specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataQueryExceptionTrait {
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $query = null;

    public function __construct(DataQueryInterface $query, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setQuery($query);
    }

    /**
     *
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::getQuery()
     * @return DataQueryInterface
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::setQuery()
     */
    public function setQuery(DataQueryInterface $query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Exceptions for data queries can add extra tabs (e.g.
     * an SQL-tab). Which tabs will be added depends on the implementation of
     * the data query.
     *
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::createDebugWidget()
     *
     * @param
     *            ErrorMessage
     * @return ErrorMessage
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = $this->parentCreateDebugWidget($error_message);
        return $this->getQuery()->createDebugWidget($error_message);
    }
}
?>