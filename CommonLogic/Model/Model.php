<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;

class Model implements ModelInterface
{

    /** @var \exface\Core\CommonLogic\Workbench */
    private $exface;

    /** @var \exface\Core\CommonLogic\Model\Object[] [ id => object ] */
    private $loaded_objects = array();

    /** @var array [ namespace => [ object_alias => object_id ] ] */
    private $object_library = array();

    private $default_namespace;

    private $model_loader;

    /**
     * 
     * @param \exface\Core\CommonLogic\Workbench $exface
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getObjectById()
     */
    public function getObjectById($object_id)
    {
        // first look in the cache
        // if nothing found, load the object and save it to cache for future
        if (! $obj = $this->getObjectFromCache($object_id)) {
            $obj = $this->getModelLoader()->loadObjectById($this, $object_id);
            $this->cacheObject($obj);
        }
        return $obj;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getObjectByAlias()
     */
    public function getObjectByAlias($object_alias, $namespace = null)
    {
        if ($namespace){
            $app_alias = $namespace;
        } else {
            $app_alias = $this->getDefaultNamespace();
        }
        
        if (! $obj = $this->getObjectFromCache($this->getObjectIdFromAlias($object_alias, $app_alias))) {
            try {
                $obj = $this->getModelLoader()->loadObjectByAlias($this->getWorkbench()->getApp($app_alias), $object_alias);
            } catch (MetaObjectNotFoundError $e){
                if (!$namespace){
                    throw new MetaObjectNotFoundError('Requested meta object "' . $object_alias . '" without an namespace (app alias)! Currently running app "' . $app_alias . '" did not contain the object either.', null, $e);
                } else {
                    throw $e;
                }
            }
            $this->cacheObject($obj);
        }
        return $obj;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getObject()
     */
    public function getObject($id_or_alias)
    {
        // If the given identifier looks like a UUID, try using it as object id. If this fails, try using it as alias anyway.
        if (strpos($id_or_alias, '0x') === 0 && strlen($id_or_alias) == 34) {
            try {
                $object = $this->getObjectById($id_or_alias);
            } catch (MetaObjectNotFoundError $e) {
                $object = null;
            }
        }
        
        if (! $object) {
            $object = $this->getObjectByAlias($this->getObjectAliasFromQualifiedAlias($id_or_alias), $this->getNamespaceFromQualifiedAlias($id_or_alias));
        }
        
        return $object;
    }

    /**
     * 
     * @param string $object_alias
     * @param string $namespace
     * @return string|boolean
     */
    private function getObjectIdFromAlias($object_alias, $namespace)
    {
        if ($id = $this->object_library[$namespace][$object_alias]) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * Checks if the object is loaded already and returns the cached version.
     * Returns false if the object is not in the cache.
     *
     * @param int $object_id            
     * @return \exface\Core\CommonLogic\Model\Object
     */
    private function getObjectFromCache($object_id)
    {
        if ($obj = $this->loaded_objects[$object_id]) {
            return $obj;
        } else {
            return false;
        }
    }

    /**
     * Adds the object to the model cache.
     * Also sets the default namespace, if it is the first object loaded.
     *
     * @param \exface\Core\CommonLogic\Model\Object $obj            
     * @return boolean
     */
    private function cacheObject(\exface\Core\CommonLogic\Model\Object $obj)
    {
        $this->loaded_objects[$obj->getId()] = $obj;
        $this->object_library[$obj->getNamespace()][$obj->getAlias()] = $obj->getId();
        if (! $this->getDefaultNamespace()) {
            $this->setDefaultNamespace($obj->getNamespace());
        }
        return true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns the object part of a full alias ("CUSTOMER" from "CRM.CUSTOMER")
     *
     * @param string $qualified_alias_with_app            
     * @return string
     */
    public function getObjectAliasFromQualifiedAlias($qualified_alias_with_app)
    {
        if ($sep = strrpos($qualified_alias_with_app, NameResolver::NAMESPACE_SEPARATOR)) {
            return substr($qualified_alias_with_app, $sep + 1);
        } else {
            return $qualified_alias_with_app;
        }
    }

    /**
     * Returns the app part of a full alias ("CRM" from "CRM.CUSTOMER")
     *
     * @param string $qualified_alias_with_app            
     * @return string
     */
    public function getNamespaceFromQualifiedAlias($qualified_alias_with_app)
    {
        return substr($qualified_alias_with_app, 0, strrpos($qualified_alias_with_app, NameResolver::NAMESPACE_SEPARATOR));
    }

    /**
     * 
     * @return string
     */
    public function getDefaultNamespace()
    {
        return $this->default_namespace;
    }

    /**
     * 
     * @param string $value
     */
    public function setDefaultNamespace($value)
    {
        $this->default_namespace = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::parseExpression()
     */
    public function parseExpression($expression, Object $object = null)
    {
        $expr = ExpressionFactory::createFromString($this->exface, $expression, $object);
        return $expr;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getModelLoader()
     */
    public function getModelLoader()
    {
        return $this->model_loader;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::setModelLoader()
     */
    public function setModelLoader(ModelLoaderInterface $value)
    {
        $this->model_loader = $value;
        return $this;
    }
}
?>