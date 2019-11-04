<?php
namespace exface\Core\Exceptions\ModelBuilders;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSources\ModelBuilderInterface;

/**
 * Exception thrown if a model builder encounters runtime errors (e.g. cannot parse a data source).
 * 
 * @author Andrej Kabachnik
 *
 */
class ModelBuilderRuntimeError extends RuntimeException {
    
    private $modelBuilder = null;
    
    /**
     * 
     * @param ModelBuilderInterface $builder
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(ModelBuilderInterface $builder, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setModelBuilder($builder);
    }
    
    /**
     * @return ModelBuilderInterface
     */
    public function getModelBuilder()
    {
        return $this->modelBuilder;
    }

    /**
     * @param ModelBuilderInterface $modelBuilder
     */
    public function setModelBuilder(ModelBuilderInterface $modelBuilder)
    {
        $this->modelBuilder = $modelBuilder;
        return $this;
    }

    
    public function getDefaultAlias()
    {
        return '784JYW8';
    }
}