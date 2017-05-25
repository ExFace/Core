<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;

class Model
{

    /** @var \exface\Core\CommonLogic\Workbench */
    private $exface;

    /** @var \exface\Core\CommonLogic\Model\Object[] [ id => object ] */
    private $loaded_objects = array();

    /** @var array [ namespace => [ object_alias => object_id ] ] */
    private $object_library = array();

    private $default_namespace;

    private $model_loader;

    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Fetch object meta data from model by object_id (numeric)
     *
     * @param int $obj
     *            object id
     * @return \exface\Core\CommonLogic\Model\Object
     */
    function getObjectById($object_id)
    {
        // first look in the cache
        // if nothing found, load the object and save it to cache for future
        if (! $obj = $this->getObjectFromCache($object_id)) {
            $obj = new \exface\Core\CommonLogic\Model\Object($this);
            $obj->setId($object_id);
            $this->getModelLoader()->loadObject($obj);
            $this->cacheObject($obj);
        }
        return $obj;
    }

    /**
     * Fetch object meta data from model by alias (e.g.
     * EXFACE.ATTRIBUTE, where EXFACE is the namespace and ATTRIBUTE - the object_alias)
     *
     * @param string $alias_including_app            
     * @return \exface\Core\CommonLogic\Model\Object
     */
    function getObjectByAlias($object_alias, $namespace = null)
    {
        if (! $namespace)
            $namespace = $this->getDefaultNamespace();
        if (! $obj = $this->getObjectFromCache($this->getObjectIdFromAlias($object_alias, $namespace))) {
            $obj = new \exface\Core\CommonLogic\Model\Object($this);
            $obj->setAlias($object_alias);
            $obj->setNamespace($namespace);
            $obj = $this->getModelLoader()->loadObject($obj);
            $this->cacheObject($obj);
        }
        return $obj;
    }

    /**
     * Fetch object meta data from model.
     * This genera method accepts both alias and id.
     * Since full aliases always contain a dot, an alias is always a string. Thus, all
     * numeric parameters are treated as ids.
     *
     * @param int $obj
     *            object id
     * @return \exface\Core\CommonLogic\Model\Object
     */
    public function getObject($id_or_alias)
    {
        // If the given identifier looks like a UUID, try using it as object id. If this fails, try using it as alias anyway.
        if (strpos($id_or_alias, '0x') === 0 && strlen($id_or_alias) == 34) {
            try {
                $object = $this->getObjectById($id_or_alias);
            } catch (\exface\Core\Exceptions\metaModelObjectNotFoundException $e) {
                $object = null;
            }
        }
        
        if (! $object) {
            $object = $this->getObjectByAlias($this->getObjectAliasFromQualifiedAlias($id_or_alias), $this->getNamespaceFromQualifiedAlias($id_or_alias));
        }
        
        return $object;
    }

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

    public function getDefaultNamespace()
    {
        return $this->default_namespace;
    }

    public function setDefaultNamespace($value)
    {
        $this->default_namespace = $value;
    }

    /**
     * TODO Move this method to the ExpressionFactory (need to replace all calls...)
     *
     * @param string $expression            
     * @param Object $object            
     * @return \exface\Core\CommonLogic\Model\Expression
     */
    function parseExpression($expression, Object $object = null)
    {
        $expr = ExpressionFactory::createFromString($this->exface, $expression, $object);
        return $expr;
    }

    public function getModelLoader()
    {
        return $this->model_loader;
    }

    public function setModelLoader(ModelLoaderInterface $value)
    {
        $this->model_loader = $value;
        return $this;
    }
}
?>