<?php
namespace exface\Core\CommonLogic\ModelBuilders;

use exface\Core\CommonLogic\Debugger\LogBooks\MarkdownLogBook;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\ModelBuilders\AbstractSqlModelBuilder;

abstract class AbstractModelBuilder implements ModelBuilderInterface
{
    use ImportUxonObjectTrait;
    
    private $data_types = null;
    
    private $modelLanguage = 'en';

    private $data_connector = null;

    private $updateAttributePropAliases = [];
    private $updateDataTypePropNames = [];
    
    private $logbook = null;
    
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
    public function generateAttributesForObject(MetaObjectInterface $object, string $addressPattern = '') : DataSheetInterface
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

    /**
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }
    
    protected function getUpdateAttributeDataTypeProperties() : array
    {
        return $this->updateDataTypePropNames;
    }

    /**
     * Array of custom data type property names to update for attributes
     *
     * @uxon-property update_attribute_data_type_properties
     * @uxon-type array
     * @uxon-template ["length_min","length_max"]
     *
     * @param bool $trueOrFalse
     * @return AbstractSqlModelBuilder
     */
    protected function setUpdateAttributeDataTypeProperties(UxonObject $arrayOrPropNames) : AbstractSqlModelBuilder
    {
        $this->updateDataTypePropNames = $arrayOrPropNames->toArray();
        return $this;
    }

    protected function setUpdateAttributeProperties(UxonObject|array $attributeAliases) : AbstractSqlModelBuilder
    {
        $this->updateAttributePropAliases = $attributeAliases instanceof UxonObject ? $attributeAliases->toArray() : $attributeAliases;
        return $this;
    }

    protected function willUpdateDataTypeConfigs() : bool
    {
        return ! empty($this->updateDataTypePropNames);
    }

    protected function willUpdateAttributes() : bool
    {
        return $this->willUpdateDataTypeConfigs() || ! empty($this->updateAttributePropAliases);
    }

    /**
     * {@inheritDoc}
     * @see ModelBuilderInterface::getLogbook()
     */
    public function getLogbook() : LogBookInterface
    {
        if ($this->logbook === null) {
            $this->logbook = new MarkdownLogBook('Model builder');
        }
        return $this->logbook;
    }
}