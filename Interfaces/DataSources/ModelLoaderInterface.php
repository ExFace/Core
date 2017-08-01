<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\AppActionList;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\Model\ObjectActionList;
use exface\Core\Interfaces\NameResolverInstallerInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;

interface ModelLoaderInterface
{

    /**
     * 
     * @param AppInterface $app
     * @param string $object_alias
     * 
     * @throws MetaObjectNotFoundError
     * 
     * @return Object            
     */
    public function loadObjectByAlias(AppInterface $app, $object_alias);
    
    /**
     * 
     * @param string $uid
     * 
     * @throws MetaObjectNotFoundError
     * 
     * @return Object
     */
    public function loadObjectById(ModelInterface $model, $uid);
    
    /**
     * 
     * 
     * @param Object $object
     * 
     * @throws MetaAttributeNotFoundError
     * 
     * @return Attribute
     */
    public function loadAttribute(Object $object, $attribute_alias);
    
    /**
     *
     *
     * @param Object $object
     * 
     * @throws MetaRelationNotFoundError
     * 
     * @return Relation
     */
    public function loadRelation(Object $object, $relation_alias);

    /**
     * Fills th given data source with model data (query builder, connection configuration, user credentials, etc.)
     *
     * @param DataSourceInterface $data_source            
     * @param string $data_connection_id_or_alias   
     *          
     * @return DataSourceInterface
     */
    public function loadDataSource(DataSourceInterface $data_source, $data_connection_id_or_alias = null);

    /**
     * Returns the data connection, that is used to fetch model data
     *
     * @return DataConnectionInterface
     */
    public function getDataConnection();

    /**
     * Sets the data connection to fetch model data from
     *
     * @param DataConnectionInterface $connection            
     * @return ModelLoaderInterface
     */
    public function setDataConnection(DataConnectionInterface $connection);

    /**
     * Loads the object specific action definitions into the given meta object.
     *
     * @param ObjectActionList $empty_list            
     * @return ObjectActionList
     */
    public function loadObjectActions(ObjectActionList $empty_list);

    /**
     * Loads the object specific action definitions into the action list.
     *
     * @param AppActionList $empty_list            
     * @return AppActionList
     */
    public function loadAppActions(AppActionList $empty_list);

    /**
     * Loads an action defined in the meta model.
     * Returns NULL if the action is not found
     *
     * @param AppInterface $app            
     * @param string $action_alias            
     * @param WidgetInterface $called_by_widget            
     * @return ActionInterface
     */
    public function loadAction(AppInterface $app, $action_alias, WidgetInterface $called_by_widget = null);

    /**
     * Returns the Installer, that will take care of setting up the model data source, keeping in upto date, etc.
     *
     * @return NameResolverInstallerInterface
     */
    public function getInstaller();
}
?>