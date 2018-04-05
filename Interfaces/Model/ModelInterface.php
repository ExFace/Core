<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * The model class is the single point of contact for a workbench instance to the metamodel.
 * 
 * Basically it simplifies the use of model loaders and takes care of caching
 * loaded model instances.
 * 
 * @author aka
 *
 */
interface ModelInterface extends WorkbenchDependantInterface
{

    /**
     * 
     * @param \exface\Core\CommonLogic\Workbench $exface
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface);

    /**
     * Fetch object meta data from model by object_id (numeric)
     *
     * @param string $object_id
     * @return MetaObjectInterface
     */
    public function getObjectById($object_id);

    /**
     * Fetch object meta data from model by alias (e.g.
     * EXFACE.ATTRIBUTE, where EXFACE is the namespace and ATTRIBUTE - the object_alias)
     *
     * @param string $object_alias     
     * @param string $namespace       
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getObjectByAlias($object_alias, $namespace = null);

    /**
     * Fetch object meta data from model.
     * This genera method accepts both alias and id.
     * Since full aliases always contain a dot, an alias is always a string. Thus, all
     * numeric parameters are treated as ids.
     *
     * @param MetaObjectSelectorInterface|string $selectorOrString
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getObject($selectorOrString);

    /**
     * TODO Move this method to the ExpressionFactory (need to replace all calls...)
     *
     * @param string $expression            
     * @param MetaObjectInterface $object            
     * @return \exface\Core\Interfaces\Model\ExpressionInterface
     */
    public function parseExpression($expression, MetaObjectInterface $object = null);

    /**
     * @return ModelLoaderInterface
     */
    public function getModelLoader();

    /**
     * 
     * @param ModelLoaderInterface $value
     * @return ModelInterface
     */
    public function setModelLoader(ModelLoaderInterface $value);
}
?>