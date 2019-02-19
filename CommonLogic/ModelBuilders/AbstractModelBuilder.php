<?php
namespace exface\Core\CommonLogic\ModelBuilders;

use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;

abstract class AbstractModelBuilder implements ModelBuilderInterface
{
    private $data_types = null;
    
    private $modelLanguage = 'en';
    
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
    public function getDataConnection() : DataConnectionInterface
    {
        return $this->data_connector;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelBuilderInterface::generateObjectsForDataSource()
     */
    public function generateObjectsForDataSource(AppInterface $app, DataSourceInterface $source, string $data_address_mask = null) : DataSheetInterface
    {
        throw new NotImplementedError('Creating models for all entities of a data source not yet implemented!');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelBuilderInterface::generateAttributesForObject()
     */
    public function generateAttributesForObject(MetaObjectInterface $object) : DataSheetInterface
    {
        throw new NotImplementedError('Creating models for explicitly specified object not yet implemented!');
    }
    
    /**
     * 
     * @param DataTypeInterface $type
     * @return string|null
     */
    protected function getDataTypeId(DataTypeInterface $type, $useCacheOnly = false) : ?string
    {
        if (is_null($this->data_types)) {
            $this->data_types = DataSheetFactory::createFromObjectIdOrAlias($type->getWorkbench(), 'exface.Core.DATATYPE');
            $this->data_types->getColumns()->addMultiple([
                $this->data_types->getMetaObject()->getUidAttributeAlias(),
                'ALIAS',
                'APP__ALIAS'
            ]);
            $this->data_types->dataRead();
        }
        
        $aliasRows = $this->data_types->getColumns()->get('ALIAS')->findRowsByValue($type->getAlias());
        
        foreach ($aliasRows as $rowNr){
            if ($this->data_types->getCellValue('APP__ALIAS', $rowNr) === $type->getNamespace()) {
                $uid = $this->data_types->getUidColumn()->getCellValue($rowNr);
            }
        }
        
        if ($uid === null && $useCacheOnly === false) {
            $this->data_types->dataRead();
            return $this->getDataTypeId($type, true);
        }
        
        return $uid;
    }
    
    /**
     *
     * @return string
     */
    protected function getModelLanguage() : string
    {
        return $this->modelLanguage;
    }
    
    /**
     *
     * @param string $value
     * @return AbstractModelBuilder
     */
    protected function setModelLanguage(string $value) : AbstractModelBuilder
    {
        $this->modelLanguage = $value;
        return $this;
    }
}