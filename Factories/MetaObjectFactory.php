<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\DataSource;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Selectors\DataSourceSelector;
use exface\Core\CommonLogic\Selectors\QueryBuilderSelector;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Instantiates meta objects
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class MetaObjectFactory extends AbstractStaticFactory
{
    /**
     *
     * @param MetaObjectSelectorInterface $selector
     * @return MetaObjectInterface
     */
    public static function create(MetaObjectSelectorInterface $selector) : MetaObjectInterface
    {
        return $selector->getWorkbench()->model()->getObject($selector);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return MetaObjectInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $uidOrAlias) : MetaObjectInterface
    {
        return $workbench->model()->getObject($uidOrAlias);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $alias
     * @param string $namespace
     * @return MetaObjectInterface
     */
    public static function createFromAliasAndNamespace(WorkbenchInterface $workbench, string $alias, string $namespace) : MetaObjectInterface
    {
        return $workbench->model()->getObjectByAlias($alias, $namespace);
    }
    
    /**
     * 
     * @param AppInterface $app
     * @param string $alias
     * @return MetaObjectInterface
     */
    public static function createFromApp(AppInterface $app, string $alias) : MetaObjectInterface
    {
        return $app->getWorkbench()->model()->getObjectByAlias($alias, $app->getAliasWithNamespace());
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uid
     * @return MetaObjectInterface
     */
    public static function createFromUid(WorkbenchInterface $workbench, string $uid) : MetaObjectInterface
    {
        return $workbench->model()->getObjectById($uid);
    }

    /**
     * Instantiates a virtual object (not stored in the meta model)
     * 
     * @param \exface\Core\Interfaces\WorkbenchInterface $workbench
     * @param string $name
     * @param string $dataAddress
     * @param string $queryBuilderSelector
     * @param DataConnectionInterface|string|\exface\Core\CommonLogic\Selectors\DataConnectionSelector $dataConnectionOrAlias
     * @param bool $readable
     * @param bool $writable
     * @return MetaObjectInterface
     */
    public static function createTemporary(
        WorkbenchInterface $workbench, 
        string $name, 
        string $dataAddress, 
        string $queryBuilderSelector, 
        $dataConnectionOrAlias,
        bool $readable = true,
        bool $writable = false
    ) : MetaObjectInterface
    {
        // Create a data source
        $tmpAlias = 'tmp_' . uniqid("", true);
        $qbSelector = new QueryBuilderSelector($workbench, $queryBuilderSelector);
        switch (true) {
            case $qbSelector->isAlias():
                $qbName = StringDataType::substringAfter($qbSelector->__toString(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $qbSelector->__toString());
                break;
            default:
                $qbName = FilePathDataType::findFileName($qbSelector->__toString(), false);
                break;
        }
        $dsSelector = new DataSourceSelector($workbench, 'exface.Core.' . $tmpAlias);
        $ds = DataSourceFactory::createEmpty($dsSelector);
        $ds->setName('Temp. ' . $qbName);
        $ds->setQueryBuilderAlias($queryBuilderSelector);
        if ($dataConnectionOrAlias instanceof DataConnectionInterface) {
            $ds->setConnection($dataConnectionOrAlias);
        } else {
            $ds->setConnection(DataConnectionFactory::createFromModel($workbench, $dataConnectionOrAlias));
        }

        $obj = new MetaObject($workbench->model());
        $obj->setAlias($tmpAlias);
        $obj->setName($name);
        $obj->setAppId('0x31000000000000000000000000000000'); // exface.Core
        $obj->setId(UUIDDataType::generateSqlOptimizedUuid());
        $obj->setDataSource($ds);
        $obj->setDataAddress($dataAddress);
        $obj->setReadable($readable);
        $obj->setWritable($writable);

        return $obj;
    }

    /**
     * Creates a virtual attribute (not stored in the meta model) to the given object.
     *
     * @param MetaObjectInterface $obj
     * @param string              $name
     * @param string              $alias
     * @param string              $dataAddress
     * @param mixed|null          $dataTypeOrSelector
     * @param string|null         $attributeClass
     * @return MetaAttributeInterface
     */
    public static function addAttributeTemporary(
        MetaObjectInterface $obj,
        string $name,
        string $alias,
        string $dataAddress,
        mixed $dataTypeOrSelector = null,
        ?string $attributeClass = null
    ) : MetaAttributeInterface
    {
        if(!empty($attributeClass) && 
            is_subclass_of($attributeClass, MetaAttributeInterface::class)) {
            $attr = new $attributeClass($obj);
        } else {
            $attr = new Attribute($obj);
        }

        $attr->setId(UUIDDataType::generateSqlOptimizedUuid());
        $attr->setAlias($alias);
        $attr->setName($name);
        $attr->setDataAddress($dataAddress);
        switch (true) {
            case $dataTypeOrSelector instanceof DataTypeInterface:
                $type = $dataTypeOrSelector;
                break;
            case $dataTypeOrSelector instanceof DataTypeSelectorInterface:
                $type = DataTypeFactory::createFromSelector($dataTypeOrSelector);
                break;
            case is_string($dataTypeOrSelector):
                $type = DataTypeFactory::createFromString($obj->getWorkbench(), $dataTypeOrSelector);
                break;
            case $dataTypeOrSelector === null:
                $type = DataTypeFactory::createBaseDataType($obj->getWorkbench());
                break;
            default:
                throw new InvalidArgumentException('Invalid data type supplied for temporary attribute: expecting data type instance or selector, received ' . get_class($dataTypeOrSelector));
        }
        $attr->setDataType($type);
        
        $obj->getAttributes()->add($attr);
        return $attr;
    }
}