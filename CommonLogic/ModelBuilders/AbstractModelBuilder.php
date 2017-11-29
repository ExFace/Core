<?php
namespace exface\Core\CommonLogic\ModelBuilders;

use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;

abstract class AbstractModelBuilder implements ModelBuilderInterface
{
    public function __construct(DataConnectionInterface $data_connector)
    {
        $this->data_connector = $data_connector;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelBuilderInterface::getDataConnection()
     */
    public function getDataConnection()
    {
        return $this->data_connector;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelBuilderInterface::generateObjectsForDataSource()
     */
    public function generateObjectsForDataSource(AppInterface $app, DataSourceInterface $source, $data_address_mask = null)
    {
        throw new NotImplementedError('Creating models for all entities of a data source not yet implemented!');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelBuilderInterface::generateAttributesForObject()
     */
    public function generateAttributesForObject(MetaObjectInterface $object)
    {
        throw new NotImplementedError('Creating models for explicitly specified object not yet implemented!');
    }
}