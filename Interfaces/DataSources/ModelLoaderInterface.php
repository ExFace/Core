<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Model\AppActionList;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Model\MetaObjectActionListInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\SelectorInstallerInterface;

interface ModelLoaderInterface
{

    /**
     * 
     * @param NameResolverInterface $model
     * @return ModelLoaderInterface
     */
    public function __construct(NameResolverInterface $nameResolver);
    
    /**
     * @return NameResolverInterface
     */
    public function getNameResolver();
    
    /**
     * 
     * @param AppInterface $app
     * @param string $object_alias
     * 
     * @throws MetaObjectNotFoundError
     * 
     * @return MetaObjectInterface            
     */
    public function loadObjectByAlias(AppInterface $app, $object_alias);
    
    /**
     * 
     * @param string $uid
     * 
     * @throws MetaObjectNotFoundError
     * 
     * @return MetaObjectInterface
     */
    public function loadObjectById(ModelInterface $model, $uid);
    
    /**
     * 
     * 
     * @param MetaObjectInterface $object
     * 
     * @throws MetaAttributeNotFoundError
     * 
     * @return MetaAttributeInterface
     */
    public function loadAttribute(MetaObjectInterface $object, $attribute_alias);
    
    /**
     *
     *
     * @param MetaObjectInterface $object
     * 
     * @throws MetaRelationNotFoundError
     * 
     * @return MetaRelationInterface
     */
    public function loadRelation(MetaObjectInterface $object, $relation_alias);

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
     * @param MetaObjectActionListInterface $empty_list            
     * @return MetaObjectActionListInterface
     */
    public function loadObjectActions(MetaObjectActionListInterface $empty_list);

    /**
     * Loads the object specific action definitions into the action list.
     *
     * @param AppActionList $empty_list            
     * @return AppActionList
     */
    public function loadAppActions(AppActionList $empty_list);
    
    /**
     * Loads the data type matching the passed UID from the given model
     * 
     * @param string $uid_or_alias
     * 
     * @return DataTypeInterface
     */
    public function loadDataType($uid_or_alias);

    /**
     * Loads an action defined in the meta model.
     * Returns NULL if the action is not found
     *
     * @param AppInterface $app            
     * @param string $action_alias            
     * @param WidgetInterface $trigger_widget            
     * @return ActionInterface
     */
    public function loadAction(AppInterface $app, $action_alias, WidgetInterface $trigger_widget = null);

    /**
     * Returns the Installer, that will take care of setting up the model data source, keeping in upto date, etc.
     *
     * @return SelectorInstallerInterface
     */
    public function getInstaller();
}
?>