<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;

/**
 * This trait enables an exception to output data type specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataTypeExceptionTrait {
    
    use ExceptionTrait {
		createWidget as createWidgetViaTrait;
	}

    private $dataType = null;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
     */
    public function __construct(DataTypeInterface $dataType, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataType($dataType);
    }
    
    /**
     * {@inheritDoc}
     * @see DataTypeExceptionInterface::getDataType()
     */
    public function getDataType() : DataTypeInterface
    {
        return $this->dataType;
    }

    /**
     * @param DataTypeInterface $dataType
     * @return DataTypeExceptionInterface
     */
    protected function setDataType(DataTypeInterface $dataType) : DataTypeExceptionInterface
    {
        $this->dataType = $dataType;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = parent::getLinks();
        $type = $this->getDataType();
        $links['Data type ' . $type->getAliasWithNamespace()] = DocsFacade::buildUrlToDocsForUxonPrototype(get_class($type));
        return $links;
    }
}