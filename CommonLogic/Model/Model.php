<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

class Model implements ModelInterface
{

    /** @var \exface\Core\CommonLogic\Workbench */
    private $exface;

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
     * @deprecated use MetaObjectFactory::createFromUid()
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getObjectById()
     */
    public function getObjectById($object_id)
    {
        return MetaObjectFactory::createFromUid($this->getWorkbench(), $object_id);
    }
    
    /**
     * Returns a copy of the given object freshly read from the meta model.
     * 
     * @param MetaObjectInterface $object
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function reloadObject(MetaObjectInterface $object)
    {
        return MetaObjectFactory::reload($object);
    }
    	
    /**
     * @deprecated use MetaObjectFactory::createFromAliasAndNamespace()
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getObjectByAlias()
     */
    public function getObjectByAlias($object_alias, $namespace = null)
    {
        if ($namespace === null) {
            throw new InvalidArgumentException('Invalid objects alias "' . $object_alias . '": cannot use meta objects without app namespaces!');
        }
        return MetaObjectFactory::createFromAliasAndNamespace($this->getWorkbench(), $object_alias, $namespace);
    }

    /**
     * @deprecated use MetaObjectFactory::create()
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::getObject()
     */
    public function getObject($selectorOrString)
    {        
        return MetaObjectFactory::create($this->getWorkbench(), $selectorOrString);
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ModelInterface::clearCache()
     */
    public function clearCache() : ModelInterface
    {
        MetaObjectFactory::clearCache();
        $this->getModelLoader()->clearCache();
        return $this;
    }
}