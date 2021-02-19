<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;

class Model implements ModelInterface
{

    /** @var \exface\Core\CommonLogic\Workbench */
    private $exface;

    /** @var \exface\Core\Interfaces\Model\MetaObjectInterface[] [ id => object ] */
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
     * Returns a copy of the given object freshly read from the meta model.
     * 
     * NOTE: since this method creates a new instance of the object, all behaviors are instatiated as well,
     * eventually registering their events a second time. This may lead to unexpected behavior: e.g. if
     * you disabled/changed a behavior for an object, but this object is being refreshed, it will register 
     * a new behavior, which will not be affected by the changes on the old one.
     * 
     * @param MetaObjectInterface $object
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function reloadObject(MetaObjectInterface $object)
    {
        $obj = $this->getModelLoader()->loadObjectById($this, $object->getId());
        $this->cacheObject($obj);
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
    public function getObject($selectorOrString)
    {
        if ($selectorOrString instanceof MetaObjectSelectorInterface) {
            $selector = $selectorOrString;
        } else {
            $selector = new MetaObjectSelector($this->getWorkbench(), $selectorOrString);
        }
        
        // If the given identifier looks like a UUID, try using it as object id. If this fails, try using it as alias anyway.
        $object = null;
        if ($selector->isUid()) {
            try {
                $object = $this->getObjectById($selector->toString());
            } catch (MetaObjectNotFoundError $e) {
                $object = null;
            }
        }
        
        if (! $object) {
            $alias = substr($selector->toString(), (strlen($selector->getAppAlias())+1));
            $object = $this->getObjectByAlias($alias, $selector->getAppAlias());
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
        if ($id = ($this->object_library[$namespace][$object_alias] ?? null)) {
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
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    private function getObjectFromCache($object_id)
    {
        if ($obj = ($this->loaded_objects[$object_id] ?? null)) {
            return $obj;
        } else {
            return false;
        }
    }

    /**
     * Adds the object to the model cache.
     * Also sets the default namespace, if it is the first object loaded.
     *
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $obj            
     * @return boolean
     */
    private function cacheObject(\exface\Core\Interfaces\Model\MetaObjectInterface $obj)
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
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
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
        if ($sep = strrpos($qualified_alias_with_app, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER)) {
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
        return substr($qualified_alias_with_app, 0, strrpos($qualified_alias_with_app, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER));
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
    public function parseExpression($expression, MetaObjectInterface $object = null)
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