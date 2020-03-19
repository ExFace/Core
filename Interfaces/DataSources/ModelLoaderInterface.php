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
use exface\Core\Interfaces\SelectorInstallerInterface;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\Selectors\ModelLoaderSelectorInterface;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\UserNotUniqueError;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\CommonLogic\Model\UiPageTree;

interface ModelLoaderInterface
{

    /**
     * 
     * @param ModelLoaderSelectorInterface $selector
     * @return ModelLoaderInterface
     */
    public function __construct(ModelLoaderSelectorInterface $selector);
    
    /**
     * @return ModelLoaderSelectorInterface
     */
    public function getSelector() : ModelLoaderSelectorInterface;
    
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
     * Loads component attributes for the given compound attribute
     * 
     * @param CompoundAttributeInterface $attribute
     * 
     * @return CompoundAttributeInterface
     */
    public function loadAttributeComponents(CompoundAttributeInterface $attribute) : CompoundAttributeInterface;
    
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
     * 
     * @param UiPageSelectorInterface $selector
     * @return UiPageInterface
     */
    public function loadPage(UiPageSelectorInterface $selector, bool $ignoreReplacements = false) : UiPageInterface;

    /**
     * Loads the models for the data source and the corresponding connection and returns the resulting instances.
     * 
     * @param DataSourceSelectorInterface $selector
     * @param DataConnectionSelectorInterface $connectionSelector
     * @return DataSourceInterface
     */
    public function loadDataSource(DataSourceSelectorInterface $selector, DataConnectionSelectorInterface $connectionSelector = null) : DataSourceInterface;

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
     * @param DataTypeSelectorInterface $selector
     * 
     * @return DataTypeInterface
     */
    public function loadDataType(DataTypeSelectorInterface $selector) : DataTypeInterface;

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
    
    public function loadUser(UserSelectorInterface $selector) : UserInterface;
    
    /**
     * 
     * @param UserInterface $user
     * 
     * @throws UserNotFoundError
     * @throws UserNotUniqueError
     * 
     * @return UserInterface
     */
    public function loadUserData(UserInterface $user) : UserInterface;
    
    /**
     * Creates the passed Exface user.
     *
     * @param UserInterface $user
     * @return ModelLoaderInterface
     */
    public function createUser(UserInterface $user) : ModelLoaderInterface;
    
    /**
     * Updates the passed Exface user.
     *
     * @param UserInterface $user
     * @return ModelLoaderInterface
     */
    public function updateUser(UserInterface $user) : ModelLoaderInterface;
    
    /**
     * Deletes the passed Exface user.
     *
     * @param UserInterface $user
     * @return ModelLoaderInterface
     */
    public function deleteUser(UserInterface $user) : ModelLoaderInterface;
    
    /**
     * Loads data from database and builds the tree structure for the given tree, returning an array of root nodes for the tree.
     *
     * @param UiPageTree $tree
     * @return array
     */
    public function loadUiPageTree(UiPageTree $tree) : array;
    
}
?>