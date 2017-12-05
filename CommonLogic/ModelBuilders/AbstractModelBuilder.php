<?php
namespace exface\Core\CommonLogic\ModelBuilders;

use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

abstract class AbstractModelBuilder implements ModelBuilderInterface
{
    private $data_types = null;
    
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
    
    /**
     * 
     * @param DataTypeInterface $type
     * @return string|null
     */
    protected function getDataTypeId(DataTypeInterface $type)
    {
        if (is_null($this->data_types)) {
            $this->data_types = DataSheetFactory::createFromObject($this->getDataConnection()->getWorkbench()->model()->getObject('exface.Core.DATATYPE'));
            $this->data_types->getColumns()->addMultiple(array(
                $this->data_types->getMetaObject()->getUidAttributeAlias(),
                'ALIAS'
            ));
            $this->data_types->dataRead(0, 0);
        }
        
        return $this->data_types->getUidColumn()->getCellValue($this->data_types->getColumns()->get('ALIAS')->findRowByValue($type->getAlias()));
    }
    
    /**
     * 
     * @param Workbench $workbench
     * @param string $source_data_type
     * @param array $options
     * @return DataTypeInterface
     */
    protected abstract function guessDataType(Workbench $workbench, $source_data_type);
}