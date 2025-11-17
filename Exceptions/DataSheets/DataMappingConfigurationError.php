<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Exceptions\DataMappingExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown if the configuration of a data mapping is invalid.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataMappingConfigurationError extends LogicException implements DataMappingExceptionInterface
{
    private $mapping = null;
    
    /**
     * 
     * @param DataMappingInterface $mapping
     * @param string $message
     * @param string|NULL $alias
     * @param \Throwable|NULL $previous
     */
    public function __construct(DataMappingInterface $mapping, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->mapping = $mapping;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface::getMapper()
     */
    public function getMapping() : DataMappingInterface
    {
        return $this->mapping;
    }
    
    /**
     * 
     * @return DataSheetMapperInterface
     */
    public function getMapper() : DataSheetMapperInterface
    {
        return $this->mapping->getMapper();
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = parent::getLinks();
        $links['Data mapper configuration options'] = DocsFacade::buildUrlToDocsForUxonPrototype(get_class($this->getMapper()));
        $mapping = $this->getMapping();
        $mappingName = PhpClassDataType::findClassNameWithoutNamespace($mapping);
        $links['Mapping "' . $mappingName . '"'] = DocsFacade::buildUrlToDocsForUxonPrototype(get_class($mapping));
        return $links;
    }
}