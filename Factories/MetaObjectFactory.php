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
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Instantiates meta objects
 * 
 * This factory maintains an internal cache, so objects are only really loaded once. Every subsequent
 * request to create the object will return the existing instance.
 * 
 * In rare cases, it might be necessary to reload the object from the meta model via `reload()`. This
 * might have side-effects though. Similarly you can `clearCache()` entirely.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class MetaObjectFactory extends AbstractStaticFactory
{
    private static $cacheByAlias = [];
    private static $cacheByUid = [];
    private static $tempObjects = [];
    private static $cacheLoading = [];

    /**
     * 
     * @param \exface\Core\Interfaces\WorkbenchInterface $workbench
     * @param MetaObjectSelectorInterface|string $selectorOrString
     * @throws \exface\Core\Exceptions\Model\MetaObjectNotFoundError
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
                throw new MetaObjectNotFoundError('Invalid meta object selector provided: expecting string or instantiated MetaObjectSelector, received ' . gettype($selectorOrString));
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
            $obj = new MetaObject($workbench->model());
            $obj->setAlias($alias);
            $obj->setNamespace($namespace);
            // Set cache BEFORE loading to ensure any relations or behaviors do not trigger loading
            // more instances of the same object
            static::setCache($obj, true);
            $obj = $workbench->model()->getModelLoader()->loadObjectByAlias($workbench->getApp($namespace), $alias);
            static::setCache($obj);
        } catch (AppNotFoundError $e) {
            throw new MetaObjectNotFoundError('Meta object "' . $fullAlias . '" not found! Invalid app namespace "' . $namespace . '"!', null, $e);
        }
        return $obj;
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

        $obj = new MetaObject($workbench->model());
        $obj->setId($uid);
        // Set cache BEFORE loading to ensure any relations or behaviors do not trigger loading
        // more instances of the same object
        self::setCache($obj, true);
        $obj = $workbench->model()->getModelLoader()->loadObject($obj);
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

        // Create the minimum of an object
        $obj = new MetaObject($workbench->model());
        $obj->setAlias($tmpAlias);
        $obj->setNamespace('exface.Core');
        $obj->setId(UUIDDataType::generateSqlOptimizedUuid());

        // Cache the object as soon as possible to ensure it is found if required by any relations
        // or behavirs while being initialized.
        self::setCache($obj);
        // Also store the object in a separate list to ensure the factory will not try to
        // (re)load its content via model loader.
        self::$tempObjects[] = $obj;

        // Fill the object with other data
        $obj->setName($name);
        $obj->setDataSource($ds);
        $obj->setDataAddress($dataAddress);
        $obj->setReadable($readable);
        $obj->setWritable($writable);

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
                throw new InvalidArgumentException('Invalid data type supplied for temporary attribute: expecting data type instance or selector, received ' . gettype($dataTypeOrSelector));
        }

        $attr->setDataType($type);
        $attr->setDataAddress($dataAddress);
        $obj->getAttributes()->add($attr);
        
        return $attr;
    }

    /**
     * Reloads the given object from the meta model and returns a fresh copy.
     * 
     * CAUTION: currently a new instance of the object is created. Be very careful! The old
     * instance still remains, but will not be used anymore.
     * 
     * TODO this is not good enough as the old copy is not reloaded. So part of the code
     * has the old one an part of the code a new copy. In particular, this means, that changes
     * undertaken on attributes, behaviors, etc. of the old copy do not affect the new one.
     * For example, if behaviors are enabled/disabled it only affects one of the copies. 
     * Perhaps, it would be better to empty the object (remove attributes, relations, etc.)
     * and reload the same instance.
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @return MetaObjectInterface
     */
    public static function reload(MetaObjectInterface $object) : MetaObjectInterface
    {
        // Cannot reload temporary objects!
        if (in_array($object, self::$tempObjects)) {
            return $object;
        }

        $oId = $object->getId();
        $workbench = $object->getWorkbench();
        // Can't really unset the object here because it might be still referenced by other things
        // like widgets or relations. Instead, we remove it from the cache, disable all event
        // listeners and load a new copy of it. Everything created AFTER this will get the new
        // copy. 
        static::clearCache($object, true);
        return static::createFromUid($workbench, $oId);
    }

    /**
     * 
     * @param string $uidOrAlias
     */
    private static function getCache(string $uidOrAlias) : ?MetaObjectInterface
    {
        // Check cache
        if (null !== $cache = static::$cacheByUid[static::getCacheKey($uidOrAlias)] ?? null) {
            return $cache;
        }
        if (null !== $cache = static::$cacheByAlias[static::getCacheKey($uidOrAlias)] ?? null) {
            return $cache;
        }
        // If not found in the regular caches, check the temporary cache for loading objects.
        // We don't have keys here, so we iterate over the entire array. Since it is a temporary
        // array, it should not be more than a few objects.
        foreach (static::$cacheLoading as $obj) {
            if ($obj->getAliasWithNamespace() === $uidOrAlias || $obj->getId() === $uidOrAlias) {
                return $obj;
            }
        }
        return null;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $obj
     * @param bool $notFullyLoaded
     * @return void
     */
    private static function setCache(MetaObjectInterface $obj, bool $notFullyLoaded = false) : void
    {
        if ($notFullyLoaded === false) {
            static::$cacheByAlias[static::getCacheKey($obj->getAliasWithNamespace())] = $obj;
            static::$cacheByUid[static::getCacheKey($obj->getId())] = $obj;
            foreach (static::$cacheLoading as $key => $loadingObj) {
                if ($loadingObj->getAliasWithNamespace() === $obj->getAliasWithNamespace() || $loadingObj->getId() === $obj->getId()) {
                    unset(static::$cacheLoading[$key]);
                }
            }
        } else {
            // Can't use a reference here, because the object is not fully loaded yet and only "knows"
            // its UID or its alias, but not both. We keep these objects in a separate small array,
            // that can be searched on getCache(). After the object is loaded completely, it will be
            // cached in the regular key-value arrays.
            static::$cacheLoading[] = $obj;
        }
        return;
    }

    /**
     * Clears the cache of the factory forcing it to load objects from the metamodel again.
     * 
     * This will not affect temporary objects, that do not exist in the metamodel - unless
     * explicitly requested.
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface|null $obj
     * @param bool $disableBehaviors
     * @return void
     */
    public static function clearCache(MetaObjectInterface $obj = null, bool $disableBehaviors = true, bool $dropTempObjects = false) : void
    {
        if ($obj === null) {
            foreach (static::$cacheByAlias as $obj) {
                static::clearCache($obj, $disableBehaviors);
            }
        } else {
            if ($dropTempObjects === false && in_array($obj, self::$tempObjects)) {
                return;
            }
            unset(static::$cacheByAlias[static::getCacheKey($obj->getAliasWithNamespace())]);
            unset(static::$cacheByUid[static::getCacheKey($obj->getId())]);
            if ($disableBehaviors === true) {
                $obj->getBehaviors()->disableTemporarily(true);
            }
        }
        return;
    }

    /**
     * Normalizes the given UID or alias to a cache key.
     * 
     * @param string $uidOrAlias
     * @return string
     */
    private static function getCacheKey(string $uidOrAlias) : string
    {
        return mb_strtoupper($uidOrAlias);
    }
}