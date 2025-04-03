<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\DataSource;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Selectors\DataSourceSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\CommonLogic\Selectors\QueryBuilderSelector;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
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
    private static $cacheByAlias = [];

    private static $cacheByUid = [];

    private static $tempObjects = [];

    /**
     * 
     * @param \exface\Core\Interfaces\WorkbenchInterface $workbench
     * @param MetaObjectSelectorInterface|string $selectorOrString
     * @throws \exface\Core\Exceptions\InvalidArgumentException
     * @return MetaObjectInterface
     */
    public static function create(WorkbenchInterface $workbench, $selectorOrString) : MetaObjectInterface
    {
        switch (true) {
            case is_string($selectorOrString):
                $selectorString = $selectorOrString;
                $selector = null;
                break;
            case $selectorOrString instanceof MetaObjectSelectorInterface:
                $selectorString = $selectorOrString->toString();
                $selector = $selectorOrString;
                break;
            default:
                throw new InvalidArgumentException('Invalid meta object selector provided: expecting string or instantiated MetaObjectSelector, received ' . gettype($selectorOrString));
        }

        if (null !== $cache = static::getCache($selectorString)) {
            return $cache;
        }

        if ($selector === null) {
            $selector = new MetaObjectSelector($workbench, $selectorString);
        }
        
        return static::createFromSelector($selector);
    }

    /**
     *
     * @param MetaObjectSelectorInterface $selector
     * @return MetaObjectInterface
     */
    public static function createFromSelector(MetaObjectSelectorInterface $selector) : MetaObjectInterface
    {
        
        if ($selector->isUid()) {
            $object = self::createFromUid($selector->getWorkbench(), $selector->__toString());
        } else {
            $namespace = $selector->getAppAlias();
            $alias = mb_substr($selector->__toString(), mb_strlen($namespace) + 1);
            $object = self::createFromAliasAndNamespace($selector->getWorkbench(), $alias, $namespace);
        }
        return $object;
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
        $fullAlias = $namespace . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $alias;
        if (null !== $cache = static::getCache($fullAlias)) {
            return $cache;
        }
        try {
            $obj = $workbench->model()->getModelLoader()->loadObjectByAlias($workbench->getApp($namespace), $alias);
        } catch (AppNotFoundError $e) {
            throw new MetaObjectNotFoundError('Meta object "' . $fullAlias . '" not found! Invalid app namespace "' . $namespace . '"!');
        }
        static::setCache($obj);
        return $obj;
    }
    
    /**
     * 
     * @param AppInterface $app
     * @param string $alias
     * @return MetaObjectInterface
     */
    public static function createFromApp(AppInterface $app, string $alias) : MetaObjectInterface
    {
        $fullAlias = $app->getAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $alias;
        if (null !== $cache = static::getCache($fullAlias)) {
            return $cache;
        }
        return $app->getWorkbench()->model()->getModelLoader()->loadObjectByAlias($app, $alias);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uid
     * @return MetaObjectInterface
     */
    public static function createFromUid(WorkbenchInterface $workbench, string $uid) : MetaObjectInterface
    {
        if (null !== $cache = static::getCache($uid)) {
            return $cache;
        }
        $obj = $workbench->model()->getModelLoader()->loadObjectById($workbench->model(), $uid);
        static::setCache($obj);
        return $obj;
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
        $tmpAlias = 'tmp_' . str_replace('.', '', uniqid("", true));
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
        $obj->setNamespace('exface.Core');
        $obj->setId(UUIDDataType::generateSqlOptimizedUuid());
        $obj->setDataSource($ds);
        $obj->setDataAddress($dataAddress);
        $obj->setReadable($readable);
        $obj->setWritable($writable);

        self::setCache($obj);
        self::$tempObjects[] = $obj;

        return $obj;
    }

    /**
     * Creates a virtual attribute (not stored in the metamodel) to the given object.
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $obj
     * @param string $alias
     * @param string $name
     * @param string $dataAddress
     * @param DataTypeInterface|string $dataTypeOrSelector
     * @throws \exface\Core\Exceptions\InvalidArgumentException
     * @return Attribute
     */
    public static function addAttributeTemporary(
        MetaObjectInterface $obj,
        string $alias,
        string $name,
        string $dataAddress,
        mixed $dataTypeOrSelector = null,
    ) : MetaAttributeInterface
    {
        $attr = new Attribute($obj, $name, $alias);
        
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
        $attr->setDataAddress($dataAddress);
        $obj->getAttributes()->add($attr);

        static::setCache($obj);
        
        return $attr;
    }

    public static function reload(MetaObjectInterface $object) : MetaObjectInterface
    {
        // Cannot reload temporary objects!
        if (in_array($object, self::$tempObjects)) {
            return $object;
        }

        $oId = $object->getId();
        $model = $object->getModel();
        static::clearCache($object);
        unset($object);
        $object = $model->getModelLoader()->loadObjectById($model, $oId);
        static::setCache($object);
        return $object;
    }

    private static function getCache(string $uidOrAlias) : ?MetaObjectInterface
    {
        // Check cache
        if (null !== $cache = static::$cacheByUid[$uidOrAlias] ?? null) {
            return $cache;
        }
        if (null !== $cache = static::$cacheByAlias[$uidOrAlias] ?? null) {
            return $cache;
        }
        return null;
    }

    private static function setCache(MetaObjectInterface $obj) : void
    {
        static::$cacheByAlias[$obj->getAliasWithNamespace()] = $obj;
        static::$cacheByUid[$obj->getId()] = $obj;
        return;
    }

    public static function clearCache(MetaObjectInterface $obj = null) : void
    {
        if ($obj === null) {
            foreach (static::$cacheByAlias as $obj) {
                static::clearCache($obj);
                unset($obj);
            }
        } else {
            unset(static::$cacheByAlias[$obj->getAliasWithNamespace()]);
            unset(static::$cacheByUid[$obj->getId()]);
        }
        return;
    }
}