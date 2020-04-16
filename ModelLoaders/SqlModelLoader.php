<?php

namespace exface\Core\ModelLoaders;

use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSorterFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaModelLoadingFailedError;
use exface\Core\Interfaces\Model\MetaObjectActionListInterface;
use exface\Core\CommonLogic\Model\AppActionList;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\Interfaces\ActionListInterface;
use exface\Core\Exceptions\DataTypes\DataTypeNotFoundError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\Selectors\ModelLoaderSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;
use exface\Core\Exceptions\Model\MetaRelationBrokenError;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\UserNotUniqueError;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\CommonLogic\Selectors\DataConnectorSelector;
use exface\Core\Exceptions\DataSources\DataConnectionNotFoundError;
use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;
use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
use exface\Core\DataConnectors\MySqlConnector;
use exface\Core\Exceptions\DataSources\DataSourceNotFoundError;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\CommonLogic\Model\CompoundAttribute;
use exface\Core\Interfaces\Model\UiPageTreeNodeInterface;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\CommonLogic\Model\UiPageTree;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\DataTypes\PolicyTargetDataType;
use exface\Core\Exceptions\LogicException;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class SqlModelLoader implements ModelLoaderInterface
{
    const ATTRIBUTE_TYPE_COMPOUND = 'C';
    
    private $data_connection = null;
    
    private $data_types_by_uid = [];
    
    private $data_type_uids = [];
    
    private $connections_loaded = [];
    
    private $selector = null;
    
    private $installer = null;
    
    private $pages_loaded = [];
    
    private $nodes_loaded = [];
    
    private $menu_tress_loaded = [];
    
    
    /**
     * 
     * @param ModelLoaderSelectorInterface $selector
     */
    public function __construct(ModelLoaderSelectorInterface $selector)
    {
        $this->selector = $selector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::getSelector()
     */
    public function getSelector() : ModelLoaderSelectorInterface
    {
        return $this->selector;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadObjectById($object_id)
     */
    public function loadObjectById(ModelInterface $model, $object_id)
    {
        $obj = new MetaObject($model);
        $obj->setId($object_id);
        return $this->loadObject($obj);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadObjectByAlias($app, $object_alias)
     */
    public function loadObjectByAlias(AppInterface $app, $object_alias)
    {
        $obj = new MetaObject($app->getWorkbench()->model());
        $obj->setAlias($object_alias);
        $obj->setNamespace($app->getAliasWithNamespace());
        return $this->loadObject($obj);        
    }

    /**
     * Loads metamodel data into the given object
     * 
     * @param MetaObjectInterface $object
     * @return MetaObjectInterface
     */
    protected function loadObject(MetaObjectInterface $object)
    {
        $exface = $object->getWorkbench();
        $load_behaviors = false;
        if ($object->getId()) {
            $q_where = 'o.oid = ' . $object->getId();
        } else {
            $q_where = 'a.app_alias = "' . $object->getNamespace() . '" AND o.object_alias = "' . $object->getAlias() . '"';
        }
        $query = $this->getDataConnection()->runSql('
				SELECT
                    o.*,
					' . $this->buildSqlUuidSelector('o.oid') . ' as oid,
					' . $this->buildSqlUuidSelector('o.app_oid') . ' as app_oid,
					' . $this->buildSqlUuidSelector('o.data_source_oid') . ' as data_source_oid,
					' . $this->buildSqlUuidSelector('o.parent_object_oid') . ' as parent_object_oid,
					a.app_alias,
					' . $this->buildSqlUuidSelector('ds.base_object_oid') . ' as base_object_oid,
					EXISTS (SELECT 1 FROM exf_object_behaviors ob WHERE ob.object_oid = o.oid) AS has_behaviors
				FROM exf_object o 
					LEFT JOIN exf_app a ON o.app_oid = a.oid 
					LEFT JOIN exf_data_source ds ON o.data_source_oid = ds.oid
				WHERE ' . $q_where);
        if ($res = $query->getResultArray()) {
            $row = $res[0];
            
            $object->setId($row['oid']);
            $object->setName($row['object_name']);
            $object->setAlias($row['object_alias']);
            $object->setDataSourceId($row['data_source_oid']);
            $object->setAppId($row['app_oid']);
            $object->setNamespace($row['app_alias']);
            if ($row['has_behaviors']) {
                $load_behaviors = true;
            }
            
            // find all parents
            // When loading a data source base object, make sure not to inherit from itself to avoid recursion.
            if ($row['base_object_oid'] && $row['base_object_oid'] != $object->getId() && ($row['inherit_data_source_base_object'] ?? 1)) {
                $object->extendFromObjectId($row['base_object_oid']);
            }
            if ($row['parent_object_oid']) {
                $object->extendFromObjectId($row['parent_object_oid']);
            }
            
            // Overwrite inherited properties
            if (is_null($object->getDataAddress()) || $object->getDataAddress() == '' || (! is_null($row['data_address']) && ! $row['data_address'] == '')) {
                $object->setDataAddress($row['data_address']);
            }
            if (! is_null($row['readable_flag'])){
                $object->setReadable($row['readable_flag']);
            }
            if (! is_null($row['writable_flag'])){
                $object->setWritable($row['writable_flag']);
            }
            if (! $object->getShortDescription()) {
                $object->setShortDescription($row['short_description']);
            }
            if ($data_address_properties = UxonObject::fromJson($row['data_address_properties'])) {
                if (! $data_address_properties->isEmpty()) {
                    $object->setDataAddressProperties($data_address_properties);
                }
            }
            if ($default_editor_uxon = UxonObject::fromJson($row['default_editor_uxon'])) {
                if (! $default_editor_uxon->isEmpty()) {
                    $object->setDefaultEditorUxon($default_editor_uxon);
                }
            }
        } else {
            throw new MetaObjectNotFoundError('Object with alias "' . $object->getAliasWithNamespace() . '" or id "' . $object->getId() . '" not found!');
        }
        
        // select all attributes for this object
        $query = $this->getDataConnection()->runSql('
				SELECT
					a.*,
					' . $this->buildSqlUuidSelector('a.oid') . ' as oid,
					' . $this->buildSqlUuidSelector('a.object_oid') . ' as object_oid,
					' . $this->buildSqlUuidSelector('a.data_type_oid') . ' as data_type_oid,
					' . $this->buildSqlUuidSelector('a.related_object_oid') . ' as related_object_oid,
					' . $this->buildSqlUuidSelector('a.related_object_special_key_attribute_oid') . ' as related_object_special_key_attribute_oid,
					o.object_alias as rev_relation_alias
				FROM exf_attribute a LEFT JOIN exf_object o ON a.object_oid = o.oid
				WHERE a.object_oid = ' . $object->getId() . ' OR a.related_object_oid = ' . $object->getId());
        if ($res = $query->getResultArray()) {
            $relation_attrs = [];
            // use a for here instead of foreach because we want to extend the array from within the loop on some occasions
            $l = count($res);
            for ($i = 0; $i < $l; $i ++) {
                $row = $res[$i];
                // Only create attributes, that really belong to this object. Inherited attributes are already there.
                if ($row['object_oid'] == $object->getId()) {
                    // save the label attribute alias in object head
                    if ($row['object_label_flag']) {
                        $object->setLabelAttributeAlias($row['attribute_alias']);
                        // always add a LABEL attribute if it is not already called LABEL (widgets always need to show the LABEL!)
                        // IDEA why does the reference from the object then go to the original attribute instead of the extra
                        // created one?
                        if ($row['attribute_alias'] != $object->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS')) {
                            $label_attribute = $row;
                            $label_attribute['attribute_alias'] = $object->getModel()->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS');
                            $label_attribute['attribute_hidden_flag'] = '1';
                            $label_attribute['attribute_required_flag'] = '0';
                            $label_attribute['attribute_editable_flag'] = '0';
                            $label_attribute['attribute_writable_flag'] = '0';
                            // The special label attribute should not be marked as label because it then would be returned by get_label..(),
                            // which instead should return the original attribute
                            $label_attribute['object_label_flag'] = 0;
                            // If label and UID are one attribute, make sure the special LABEL attribute will not be treated as a second UID!
                            $label_attribute['object_uid_flag'] = '0';
                            // Auto-generated LABELs cannot be part of the default display
                            unset($label_attribute['default_display_order']);
                            // Auto-generated LABELs cannot be realtions
                            unset($label_attribute['related_object_oid']);
                            $res[] = $label_attribute;
                            $l ++;
                        }
                    }
                    
                    // check if an attribute is marked as unique id for this object
                    if ($row['object_uid_flag']) {
                        $object->setUidAttributeAlias($row['attribute_alias']);
                        $row['system_flag'] = true;
                    }
                    
                    // check if the attribute is part of the default sorting
                    if ($row['default_sorter_order']) {
                        $sorter = DataSorterFactory::createEmpty($exface);
                        $sorter->setAttributeAlias($row['attribute_alias']);
                        $sorter->setDirection($row['default_sorter_dir']);
                        $object->getDefaultSorters()->add($sorter, $row['default_sorter_order']);
                    }
                    
                    // populate attributes
                    $attr = $this->createAttributeFromDbRow($object, $row);
                    // Add the attribute to the object giving the alias as key explicitly, because automatic key generation will
                    // fail here in an infinite loop, because it uses get_relation_path(), etc.
                    // TODO Check if get_alias_with_relation_path() really will cause loops inevitably. If not, remove the explicit key
                    // here.
                    $object->getAttributes()->add($attr, $attr->getAlias());
                } else {
                    $attr = null;
                }
                
                // If the attribute is a relation, save it for later processing. We can't create relations here right away because we need to
                // instantiate all attributes first - otherwise we may not be able to find left keys of reverse relations!
                if ($row['related_object_oid']) {
                    $relation_attrs[] = [
                        'attr' => $attr,
                        'row' => $row
                    ];
                }
            }
            
            // Now populate the relations of the object 
            foreach ($relation_attrs as $data) {
                $attr = $data['attr'];
                $row = $data['row'];
                
                // If we have a reverse (1-n) relation if the attribute belongs to another object and that
                // object is not being extended from. Otherwise it's a normal n-1 relation.
                $thisObjId = $object->getId();
                $attrObjId = $row['object_oid'];
                $attrBelongsToOtherObj = ($thisObjId !== $attrObjId && false === in_array($attrObjId, $object->getParentObjectsIds()));
                $attrIsSelfRelation = ($attrObjId === $row['related_object_oid']);
                
                // IDEA What if we also create relations between parent and inheriting objects. The 
                // inheriting object should probably get a direct relation to the parent. Would that 
                // be usefull for objects sharing attributes but using different data_addresses?
                switch (true) {
                    // Create reverse-relations for attributes belonging to other objects.
                    // NOTE: that a self-relation is a reverse-relation and a regular relation at the
                    // same time, so we add it here as a reverse relation and will add it as 
                    // a regular relation later on too!
                    case $attrBelongsToOtherObj === true || $attrIsSelfRelation === true:
                        if ($leftKeyId = $row['related_object_special_key_attribute_oid']) {
                            $leftKeyAttr = $object->getAttributes()->getByAttributeId($leftKeyId);
                        } else {
                            try {
                                $leftKeyAttr = $object->getUidAttribute();
                            } catch (MetaObjectHasNoUidAttributeError $e) {
                                try {
                                    $rightObject = $this->getModel()->getObjectById($row['object_oid']);
                                    $error = 'Broken relation "' . $row['attribute_alias'] . '" from ' . $rightObject->getAliasWithNamespace() . ' to ' . $object->getAliasWithNamespace() . ': ' . $e->getMessage();
                                } catch (\Throwable $ee) {
                                    throw new MetaModelLoadingFailedError('No relations to default key attribute of object ' . $object->getAliasWithNamespace() . ' possible: ' . $e->getMessage(), '70U52B4', $e);
                                }
                                throw new MetaRelationBrokenError($rightObject, $error, '70U52B4', $e);
                            }
                        }
                        
                        if ($row['rev_relation_alias'] === '' || $row['rev_relation_alias'] === null) {
                            throw new MetaModelLoadingFailedError('Object with UID "' . $row['object_oid'] . '" does not exist, but is referenced by the attribute "' . $row['attribute_alias'] . '" (UID "' . $row['uid'] . '"). Please repair the model or delete the orphaned attribute!', '70UJ2GV');
                        }
                        
                        switch ($row['relation_cardinality']) {
                            case 'NM':
                                $cardinality = RelationCardinalityDataType::N_TO_M($exface);
                                break;
                            case '11': 
                                $cardinality = RelationCardinalityDataType::ONE_TO_ONE($exface);
                                break;
                            // case '1N':
                                // There cannot be an attribute for a 1-to-n relation in the DB because
                                // this relation would need to be a defined as n-to-1 at it's other end then.
                            default: 
                                // An regular n-to-1 relation pointing to our attribute is a reversed one (1-to-n)
                                // from it's point of view.
                                $cardinality = RelationCardinalityDataType::ONE_TO_N($exface);
                        }
                        
                        $rel = new Relation(
                            $exface,
                            $cardinality,
                            $row['oid'], // relation id
                            $row['rev_relation_alias'], // relation alias
                            $row['attribute_alias'], // relation modifier: the alias of the right key attribute
                            null, // the name cannot be specified at this point, as it depends on what other reverse relations will exist
                            $object, // left object
                            $leftKeyAttr, // left key in the main object
                            $row['object_oid'], // right object UID
                            $row['oid'] // right object key attribute id
                        );
                        
                        if ($row['delete_with_related_object'] == 1) {
                            $rel->setRightObjectToBeDeletedWithLeftObject(true);
                        }
                        if ($row['copy_with_related_object'] == 1) {
                            $rel->setRightObjectToBeCopiedWithLeftObject(true);
                        }
                        
                        // Add the relation
                        $object->addRelation($rel);
                        
                        // Check if the attribute is a self-relation. If so, do not break here, but
                        // continue adding a forward relation for it.
                        if ($attrIsSelfRelation === false) {
                            break;
                        }
                    case $attr !== null && $attrBelongsToOtherObj === false:
                        // At this point, we know, it is a direct relation. This can only happen if the object has a corresponding direct
                        // attribute. This is why the elseif($attr) is there.
                        
                        // Relation cardinality in the DB is empty if it's a regular n-to-1 relation!
                        $cardinality = $row['relation_cardinality'] ? RelationCardinalityDataType::fromValue($exface, $row['relation_cardinality']) : RelationCardinalityDataType::N_TO_ONE($exface);
                        
                        $rel = new Relation(
                            $exface,
                            $cardinality,
                            $attr->getId(), // relation id
                            $attr->getAlias(), // relation alias
                            '', // alias modifier allways empty for direct regular relations
                            $attr->getName(),
                            $object, //  left object
                            $attr, // left key attribute
                            $row['related_object_oid'], // right object UID
                            $row['related_object_special_key_attribute_oid'] // related object key attribute (UID will be used if not set)
                        );
                        
                        if ($row['delete_with_related_object'] == 1) {
                            $rel->setLeftObjectToBeDeletedWithRightObject(true);
                        }
                        if ($row['copy_with_related_object'] == 1) {
                            $rel->setLeftObjectToBeCopiedWithRightObject(true);
                        }
                        
                        // Add the relation
                        $object->addRelation($rel);
                        
                        break;
                }
            }
        }
        
        // Load behaviors if needed
        if ($load_behaviors) {
            $query = $this->getDataConnection()->runSql('
				SELECT * FROM exf_object_behaviors WHERE object_oid = ' . $object->getId());
            if ($res = $query->getResultArray()) {
                foreach ($res as $row) {
                    $behavior = BehaviorFactory::createFromUxon($object, $row['behavior'], UxonObject::fromJson($row['config_uxon']));
                    $object->getBehaviors()->add($behavior);
                }
            }
        }
        
        return $object;
    }

    protected function createAttributeFromDbRow(MetaObjectInterface $object, array $row)
    {
        if ($row['attribute_type'] === self::ATTRIBUTE_TYPE_COMPOUND) {
            $attr = new CompoundAttribute($object);
        } else {
            $attr = new Attribute($object);
        }
        $attr->setId($row['oid']);
        $attr->setAlias($row['attribute_alias']);
        $attr->setName($row['attribute_name']);
        $attr->setDataAddress($row['data']);
        $attr->setDataAddressProperties(UxonObject::fromJson($row['data_properties']));
        $attr->setRelationFlag($row['related_object_oid'] ? true : false);
        $attr->setDataType($row['data_type_oid']);
        
        if ($calcExpr = $row['attribute_formatter']) {
            $attr->setCalculation($calcExpr);
        }
        
        $default_editor = $row['default_editor_uxon'];
        if ($default_editor && $default_editor !== '{}'){
            $attr->setDefaultEditorUxon(UxonObject::fromJson($default_editor));
        }
        $default_display = $row['default_display_uxon'];
        if ($default_display && $default_display !== '{}'){
            $attr->setDefaultDisplayUxon(UxonObject::fromJson($default_display));
        }
        $custom_type = $row['custom_data_type_uxon'];
        if ($custom_type && $custom_type !== '{}') {
            $attr->setCustomDataTypeUxon(UxonObject::fromJson($custom_type));
        }
        
        // Control flags
        if (! is_null($row['attribute_readable_flag'])){
            $attr->setReadable($row['attribute_readable_flag']);
        }
        if (! is_null($row['attribute_writable_flag'])){
            $attr->setWritable($row['attribute_writable_flag']);
        }
        $attr->setRequired($row['attribute_required_flag']);
        $attr->setEditable($row['attribute_editable_flag']);
        $attr->setHidden($row['attribute_hidden_flag']);
        $attr->setSystem($row['system_flag']);
        $attr->setSortable($row['attribute_sortable_flag']);
        $attr->setFilterable($row['attribute_filterable_flag']);
        $attr->setAggregatable($row['attribute_aggregatable_flag']);
        
        // Defaults
        $attr->setDefaultDisplayOrder($row['default_display_order']);
        if ($row['default_value'] !== null && $row['default_value'] !== '') {
            $attr->setDefaultValue($row['default_value']);
        }
        if ($row['fixed_value'] !== null && $row['fixed_value'] !== '') {
            $attr->setFixedValue($row['fixed_value']);
        }
        $attr->setFormula($row['attribute_formula']);
        if ($row['default_sorter_dir']){
            $attr->setDefaultSorterDir($row['default_sorter_dir']);
        }
        $attr->setDefaultAggregateFunction($row['default_aggregate_function']);
        $attr->setValueListDelimiter($row['value_list_delimiter']);
        
        // Descriptions
        $attr->setShortDescription($row['attribute_short_description']);
        
        return $attr;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadDataSource()
     */
    public function loadDataSource(DataSourceSelectorInterface $selector, DataConnectionSelectorInterface $connectionSelector = null) : DataSourceInterface
    {
        $exface = $selector->getWorkbench();
        
        if ($connectionSelector !== null) {
            // Join data source and connection on alias or UID depending on the type of connection selector
            // Note, that the alias needs to be wrapped in quotes and the UID does not!
            if (false === $connectionSelector->isUid()) {
                $join_on = 'dc.alias = "' . $connectionSelector->toString() . '"';
            } else {
                $join_on = 'dc.oid = ' . $connectionSelector->toString();
            }
        } else {
            $join_on = 'IF (ds.custom_connection_oid IS NOT NULL, ds.custom_connection_oid, ds.default_connection_oid) = dc.oid';
        }
        
        // If there is a user logged in, fetch his specific connctor config (credentials)
        $authToken = $exface->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous() === false && $user_name = $authToken->getUsername()) {
            $join_user_credentials = ' LEFT JOIN (exf_data_connection_credentials dcc LEFT JOIN exf_user_credentials uc ON dcc.oid = uc.data_connection_credentials_oid INNER JOIN exf_user u ON uc.user_oid = u.oid AND u.username = "' . $user_name . '") ON dcc.data_connection_oid = dc.oid';
            $select_user_credentials = ', dcc.data_connector_config AS user_connector_config';
        }
        
        if ($selector->isUid() === true) {
            $selectorFilter = 'ds.oid = ' . $selector->toString();
        } else {
            $selectorFilter = 'ds.alias = "' . $selector->toString() . '"';
        }
        $sql = '
			SELECT
				ds.name as data_source_name,
				ds.alias as data_source_alias,
				ds.custom_query_builder,
				ds.default_query_builder,
				ds.readable_flag AS data_source_readable,
				ds.writable_flag AS data_source_writable,
				dc.read_only_flag AS connection_read_only,
				' . $this->buildSqlUuidSelector('dc.oid') . ' AS data_connection_oid,
				dc.alias AS data_connection_alias,
				dc.name AS data_connection_name,
				dc.data_connector,
				dc.data_connector_config,
				dc.filter_context_uxon,
                a.app_alias AS data_connection_app_alias' . $select_user_credentials . '
			FROM exf_data_source ds 
                LEFT JOIN exf_data_connection dc ON ' . $join_on . '
                ' . $join_user_credentials . '
                LEFT JOIN exf_app a ON dc.app_oid = a.oid
			WHERE ' . $selectorFilter;
        
        $query = $this->getDataConnection()->runSql($sql);
        $ds = $query->getResultArray();
        if (count($ds) > 1) {
            throw new RangeException('Multiple user credentials found for data source "' . $ds[0]['data_connection_alias'] . '" and user "' . $user_name . '"!', '6T4R8UM');
        } elseif (count($ds) != 1) {
            throw new DataSourceNotFoundError('Cannot find data source "' . $selector->toString() . '" in metamodel!', '6T4R97R');
        }
        $ds = $ds[0];
        
        $data_source = DataSourceFactory::createEmpty($selector);
        $data_source->setName($ds['data_source_name']);
        
        if (! is_null($ds['data_source_readable'])){
            $data_source->setReadable($ds['data_source_readable']);
        }
        if (! is_null($ds['data_source_writable'])){
            $data_source->setWritable($ds['data_source_writable'] && ! $ds['connection_read_only']);
        }
        
        // Some data connections may have their own filter context. Add them to the application context scope
        if ($ds['filter_context_uxon'] && $filter_context = UxonObject::fromJson($ds['filter_context_uxon'])) {
            // If there is only one filter, make an array out of it (needed for backwards compatibility)
            if (! $filter_context->isArray()){
                $filter_context = new UxonObject([$filter_context->toArray()]);
            }
            // Register the filters in the application context scope
            foreach ($filter_context as $filter) {
                $condition = ConditionFactory::createFromUxonOrArray($exface, $filter);
                $data_source->getWorkbench()->getContext()->getScopeApplication()->getFilterContext()->addCondition($condition);
            }
        }
        
        // The query builder
        if (strtolower($data_source->getId()) === '0x32000000000000000000000000000000') {
            $data_source->setQueryBuilderAlias($this->getWorkbench()->getConfig()->getOption('METAMODEL.QUERY_BUILDER'));
        } else {
            $data_source->setQueryBuilderAlias($ds['custom_query_builder'] ? $ds['custom_query_builder'] : $ds['default_query_builder']);
        }
        
        if (! $ds['data_connection_alias']) {
            throw new DataConnectionNotFoundError('No data connection found for data source "' . $ds['data_source_name'] . '" (' . $ds['data_source_alias'] . ')');
        }
        
        // Give the data source a connection
        // First see, if the connection had been already loaded previously
        foreach ($this->connections_loaded as $conn) {
            if ($conn->getSelector() && $conn->getSelector()->toString() === $ds['data_connection_oid']) {
                $data_source->setConnection($conn);
                return $data_source;
            }
        }
        
        // If not cached, instantiate the connection and put it into the cache
        $connection = $this->createDataConnectionFromDbRow($ds);
        $data_source->setConnection($connection);
        $this->connections_loaded[] = $connection;
        
        return $data_source;
    }
    
    protected function createDataConnectionFromDbRow(array $row) : DataConnectionInterface
    {
        try {
            $connectorSelector = new DataConnectorSelector($this->getWorkbench(), $row['data_connector']);
        } catch (\Throwable $e) {
            throw new DataConnectionNotFoundError('Invalid or missing connector prototype in data connection "' . $row['data_connection_name'] . '" (' . $row['data_connection_alias'] . ')!');
        }
        // Merge config from the connection and the user credentials
        $config = UxonObject::fromJson($row['data_connector_config']);
        $config = $config->extend(UxonObject::fromJson($row['user_connector_config']));
        // Instantiate the connection
        $connection = DataConnectionFactory::create(
            $connectorSelector,
            $config,
            $row['data_connection_oid'],
            $row['data_connection_alias'],
            $row['data_connection_app_alias'],
            $row['data_connection_name'],
            $row['connection_read_only']
        );
        return $connection;
    }
    
    public function loadDataConnection(DataConnectionSelectorInterface $selector) : DataConnectionInterface
    {
        foreach ($this->connections_loaded as $conn) {
            if ($conn->getSelector() && $conn->getSelector()->toString() === $selector->toString()) {
                return $conn;
            }
        }
        
        $exface = $selector->getWorkbench();
        
        if ($selector->isUid()) {
            $filter = 'dc.oid = ' . $selector->toString();
        } else {
            $filter = 'dc.alias = "' . $selector->toString() . '"';
        }
        
        // If there is a user logged in, fetch his specific connctor config (credentials)
        $authToken = $exface->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous() === false && $user_name = $authToken->getUsername()) {
            $join_user_credentials = ' LEFT JOIN (exf_data_connection_credentials dcc LEFT JOIN exf_user_credentials uc ON dcc.oid = uc.data_connection_credentials_oid INNER JOIN exf_user u ON uc.user_oid = u.oid AND u.username = "' . $user_name . '") ON dcc.data_connection_oid = dc.oid';
            $select_user_credentials = ', dcc.data_connector_config AS user_connector_config';
        }
        
        // The following IF is needed to install SQL update 8 introducing new columns in the
        // data source table. If the updated had not yet been installed, these columns are
        // not selected.
        $sql = '
			SELECT
				dc.read_only_flag AS connection_read_only,
				' . $this->buildSqlUuidSelector('dc.oid') . ' AS data_connection_oid,
				dc.alias AS data_connection_alias,
				dc.name AS data_connection_name,
				dc.data_connector,
				dc.data_connector_config,
				dc.filter_context_uxon,
                a.app_alias AS data_connection_app_alias' . $select_user_credentials . '
			FROM exf_data_connection dc
                ' . $join_user_credentials . '
                LEFT JOIN exf_app a ON dc.app_oid = a.oid
			WHERE ' . $filter;
        $query = $this->getDataConnection()->runSql($sql);
        $ds = $query->getResultArray();
        if (count($ds) > 1) {
            throw new RangeException('Multiple user credentials found for data connection "' . $selector . '" and user "' . $user_name . '"!', '6T4R8UM');
        } elseif (count($ds) != 1) {
            throw new RangeException('Cannot find data connection "' . $selector . '"!', '6T4R97R');
        }
        $ds = $ds[0];
        
        $conn = $this->createDataConnectionFromDbRow($ds);
        
        $this->connections_loaded[] = $conn;
        return $conn;
    }

    /**
     * Ensures that binary UID fields are selected as 0xNNNNN to be compatible with the internal binary notation in ExFace
     * 
     * @param string $field_name            
     * @return string
     */
    protected function buildSqlUuidSelector($field_name)
    {
        return 'CONCAT(\'0x\', LOWER(HEX(' . $field_name . ')))';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::getDataConnection()
     */
    public function getDataConnection()
    {
        return $this->data_connection;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::setDataConnection()
     */
    public function setDataConnection(DataConnectionInterface $connection)
    {
        if (! ($connection instanceof MySqlConnector)) {
            throw new \RuntimeException('Incompatible connector "' . $connection->getPrototypeClassName() . '" used for the model loader "' . get_class($this) . '": expecting a MySqlConnector or a drivative.');
        }
        $this->data_connection = $connection;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadObjectActions()
     */
    public function loadObjectActions(MetaObjectActionListInterface $empty_list)
    {
        $object_id_list = implode(',', $empty_list->getMetaObject()->getParentObjectsIds());
        $object_id_list = $empty_list->getMetaObject()->getId() . ($object_id_list ? ',' . $object_id_list : '');
        $sql_where = 'oa.object_oid IN (' . $object_id_list . ')';
        return $this->loadActionsFromModel($empty_list, $sql_where);
    }

    public function loadAppActions(AppActionList $empty_list)
    {
        $sql_where = 'a.app_alias = "' . $empty_list->getApp()->getAliasWithNamespace() . '"';
        return $this->loadActionsFromModel($empty_list, $sql_where);
    }

    public function loadAction(AppInterface $app, $action_alias, WidgetInterface $trigger_widget = null)
    {
        $sql_where = 'a.app_alias = "' . $app->getAliasWithNamespace() . '" AND oa.alias = "' . $action_alias . '"';
        $actions = $this->loadActionsFromModel(new AppActionList($app->getWorkbench(), $app), $sql_where, $trigger_widget);
        return $actions->getFirst();
    }

    /**
     *
     * @param ActionListInterface $action_list            
     * @param string $sql_where            
     * @return \exface\Core\CommonLogic\Model\ActionList
     */
    protected function loadActionsFromModel(ActionListInterface $action_list, $sql_where, WidgetInterface $trigger_widget = null)
    {
        $basket_aliases = ($action_list instanceof MetaObjectActionListInterface) ? $action_list->getObjectBasketActionAliases() : array();
        
        $query = $this->getDataConnection()->runSql('
				SELECT
					' . $this->buildSqlUuidSelector('oa.object_oid') . ' AS object_oid,
					oa.action, 
					oa.alias, 
					oa.name, 
					oa.short_description, 
					oa.config_uxon, 
					oa.use_in_object_basket_flag, 
					a.app_alias
				FROM exf_object_action oa LEFT JOIN exf_app a ON a.oid = oa.action_app_oid
				WHERE ' . $sql_where);
        if ($res = $query->getResultArray()) {
            foreach ($res as $row) {
                if ($row['config_uxon']) {
                    $action_uxon = UxonObject::fromAnything($row['config_uxon']);
                }
                $app = $action_list->getWorkbench()->getApp($row['app_alias']);
                $object = $action_list instanceof MetaObjectActionListInterface ? $action_list->getMetaObject() : $action_list->getWorkbench()->model()->getObjectById($row['object_oid']);
                $a = ActionFactory::createFromModel($row['action'], $row['alias'], $app, $object, $action_uxon, $trigger_widget);
                $a->setName($row['name']);
                $action_list->add($a);
                
                if ($row['use_in_object_basket_flag']) {
                    $basket_aliases[] = $a->getAliasWithNamespace();
                }
            }
        }
        
        if ($action_list instanceof MetaObjectActionListInterface) {
            $action_list->setObjectBasketActionAliases($basket_aliases);
        }
        
        return $action_list;
    }

    public function getInstaller()
    {
        if ($this->installer === null) {
            $coreAppSelector = new AppSelector($this->getDataConnection()->getWorkbench(), 'exface.Core');
            $installer = new AppInstallerContainer($coreAppSelector);
            
            // Init the SQL installer
            $modelConnection = $this->getDataConnection();
            $dbInstaller = new MySqlDatabaseInstaller($coreAppSelector);
            $dbInstaller
                ->setFoldersWithMigrations(['InitDB','Migrations'])
                ->setFoldersWithStaticSql(['Views'])
                ->setDataConnection($modelConnection);
            
            $installer->addInstaller($dbInstaller);
            $this->installer = $installer;
        }
        return $this->installer;
    }
    
    /**
     *
     *
     * @param MetaObjectInterface $object
     */
    public function loadAttribute(MetaObjectInterface $object, $attribute_alias)
    {
        return $object->getAttribute($attribute_alias);
    }
    
    /**
    *
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadAttributeComponents()
    */
    public function loadAttributeComponents(CompoundAttributeInterface $attribute) : CompoundAttributeInterface
    {
        $query = $this->getDataConnection()->runSql("
            SELECT
                ac.*,
                {$this->buildSqlUuidSelector('ac.attribute_oid')} as attribute_oid,
                {$this->buildSqlUuidSelector('ac.compound_attribute_oid')} as compound_attribute_oid
            FROM exf_attribute_compound ac
            WHERE ac.compound_attribute_oid = {$attribute->getId()}
            ORDER BY ac.sequence_index ASC
        ");
        foreach ($query->getResultArray() as $row) {
            $attribute->addComponentAttribute(
                $attribute->getObject()->getAttributes()->getByAttributeId($row['attribute_oid']),
                $row['value_prefix'] ?? '',
                $row['value_suffix'] ?? ''
            );
        }
        return $attribute;
    }
    
    /**
     *
     *
     * @param MetaObjectInterface $object
     */
    public function loadRelation(MetaObjectInterface $object, $relation_alias)
    {
        return $object->getRelation($relation_alias);   
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadDataType()
     */
    public function loadDataType(DataTypeSelectorInterface $selector) : DataTypeInterface
    {
        $cache = $this->getDataTypeCache($selector);
        if (empty($cache) === true){
            $this->cacheDataType($selector);
            $cache = $this->getDataTypeCache($selector);
        }
        
        if (empty($cache)) {
            throw new DataTypeNotFoundError('No data type "' . $selector . '" found!');
        }
        
        if ($cache instanceof DataTypeInterface) {
            return $cache->copy();
        } elseif (! empty($cache)) {
            $uxon = UxonObject::fromJson($cache['config_uxon']);
            $default_editor_uxon = UxonObject::fromJson($cache['default_editor_uxon']);
            $data_type = DataTypeFactory::createFromModel($cache['prototype'], $cache['data_type_alias'], $this->getWorkbench()->getApp($cache['app_alias']), $uxon, $cache['name'], $cache['short_description'], $cache['validation_error_code'], $cache['validation_error_text'], $default_editor_uxon);
            $this->data_types_by_uid[$cache['oid']] = $data_type;
            return $data_type;
        } else {
            throw new RuntimeException('Invalid cache state in the SqlModelLoader: unexpected "' . gettype($cache) . '" found in data type cache!');
        }
    }
    
    protected function getDataTypeCache(DataTypeSelectorInterface $selector)
    {
        if ($selector->isUid()){
            return $this->data_types_by_uid[$selector->toString()];
        } else {
            return $this->data_types_by_uid[$this->data_type_uids[$selector->toString()]];
        }
    }
    
    protected function cacheDataType(DataTypeSelectorInterface $selector)
    {
        if ($selector->isUid()){
            $where = 'dt.app_oid = (SELECT fd.app_oid FROM exf_data_type fd WHERE fd.oid = ' . $selector->toString() . ')';
        } else {
            $where = "dt.app_oid = (SELECT fa.oid FROM exf_app fa WHERE fa.app_alias = '" . $selector->getAppAlias() . "')";
        }
        $query = $this->getDataConnection()->runSql('
				SELECT
					dt.*,
					' . $this->buildSqlUuidSelector('dt.oid') . ' as oid,
                    a.app_alias,
                    ve.code as validation_error_code,
                    ve.title as validation_error_text
				FROM exf_data_type dt LEFT JOIN exf_message ve ON dt.validation_error_oid = ve.oid LEFT JOIN exf_app a ON a.oid = dt.app_oid
				WHERE ' . $where);
        foreach ($query->getResultArray() as $dt) {
            $this->data_types_by_uid[$dt['oid']] = $dt;
            $this->data_type_uids[$this->getFullAlias($dt['app_alias'], $dt['data_type_alias'])] = $dt['oid'];
        }
        return $this;
    }
    
    protected function getFullAlias($app_alias, $instance_alias)
    {
        return $app_alias . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $instance_alias;
    }
    
    protected function isUid($string)
    {
        return substr($string, 0, 2) === '0x';
    }
    
    protected function getModel()
    {
        return $this->getWorkbench()->model();
    }
    
    public function getWorkbench()
    {
        return $this->selector->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadUser()
     */
    public function loadUser(UserSelectorInterface $selector) : UserInterface
    {
        $userMetaObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $userData = DataSheetFactory::createFromObject($userMetaObj);
        foreach ($userMetaObj->getAttributes() as $attr) {
            $userData->getColumns()->addFromAttribute($attr);
        }
        if ($selector->isUid() === true) {
            $userData->getFilters()->addConditionFromString('UID', $selector->toString(), EXF_COMPARATOR_EQUALS);
        } else {
            $userData->getFilters()->addConditionFromString('USERNAME', $selector->toString(), EXF_COMPARATOR_EQUALS);
        }
        $userData->dataRead();
        
        if ($userData->countRows() === 0) {
            throw new UserNotFoundError('No user with ' . ($selector->isUid() ? 'UID' : 'username') . ' "' . $selector->toString() . '" found!');
        }
        
        if ($userData->countRows() > 1) {
            throw new UserNotUniqueError('Multiple users with ' . ($selector->isUid() ? 'UID' : 'username') . ' "' . $selector->toString() . '" found!');
        }
        
        $user = UserFactory::createFromModel($this->getWorkbench(), $userData->getCellValue('USERNAME', 0));
        // load the user right away, because we already have all data - it just needst to be loaded into
        // the user object.
        return $this->loadUserData($user, $userData);
    }
    
    public function loadUserData(UserInterface $user, DataSheetInterface $userData = null) : UserInterface
    {
        $sql = <<<SQL
SELECT
    u.*,
    {$this->buildSqlUuidSelector('u.oid')} AS oid,
    (
        SELECT GROUP_CONCAT({$this->buildSqlUuidSelector('uru.user_role_oid')}, ',')
        FROM exf_user_role_users uru
        WHERE uru.user_oid = u.oid
    ) as role_oids
FROM
    exf_user u
WHERE
    u.username = '{$user->getUsername()}'
SQL;
        
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        
        switch (count($rows)) {
            case 0:
                throw new UserNotFoundError('No user "' . $user->getUsername() . '" exists in the metamodel.');
            case 1:
                $row = $rows[0];
                $user->setUid($row['oid']);
                $user->setLocale($row['locale']);
                $user->setFirstName($row['first_name']);
                $user->setLastName($row['last_name']);
                $user->setEmail($row['email']);
                if ($row['password'] !== null) {
                    $user->setPassword($row['password']);
                }
                if ($row['role_oids']) {
                    foreach (explode(',', $row['role_oids']) as $roleUid) {
                        $user->addRoleSelector($roleUid);
                    }
                }
                break;
            default:
                throw new UserNotUniqueError('More than one user exist in the metamodel for username "' . $user->getUsername() . '".');
        }
        
        return $user;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadAuthorizationPoint()
     */
    public function loadAuthorizationPoint(AuthorizationPointInterface $authPoint) : AuthorizationPointInterface
    {        
        $sql = <<<SQL
SELECT 
    apt.*, 
    {$this->buildSqlUuidSelector('apt.oid')} AS oid,
    {$this->buildSqlUuidSelector('apt.app_oid')} AS app_oid
FROM 
    exf_auth_point apt
WHERE 
    apt.alias = '{$authPoint->getAlias()}'
SQL;
        
        // Since we only filter on (non-namespaced) alias in the SQL, there may be 
        // multiple rows here although it's extremely unprobable
        $result = $this->getDataConnection()->runSql($sql)->getResultArray();
        switch (count($result)) {
            case 0:
                throw new LogicException('Authorization point "' . $authPoint->getAliasWithNamespace() . '" not found in metamodel!');
            case 1:
                $checkApp = false;
                break;
            default:
                $checkApp = true;
                $apAppUid = $authPoint->getApp()->getUid();
                break;
        }
        
        foreach ($result as $row) {
            if ($checkApp === true && $apAppUid !== $row['app_oid']) {
                continue;
            }
            $authPoint
                ->setName($row['name'])
                ->setUid($row['oid'])
                ->setDisabled(BooleanDataType::cast($row['disabled_flag']))
                ->setDefaultPolicyEffect(PolicyEffectDataType::fromValue($authPoint->getWorkbench(), ($row['default_effect_local'] ? $row['default_effect_local'] : $row['default_effect_in_app'])))
                ->setPolicyCombiningAlgorithm(PolicyCombiningAlgorithmDataType::fromValue($authPoint->getWorkbench(), ($row['combining_algorithm_local'] ? $row['combining_algorithm_local'] : $row['combining_algorithm_in_app'])));
        }
        
        return $authPoint;
    }
    
    public function loadAuthorizationPolicies(AuthorizationPointInterface $authPoint, UserImpersonationInterface $userOrToken) : AuthorizationPointInterface
    {
        if ($userOrToken->isAnonymous()) {
            // Load all policies of the anonymous user
            // + all policies without a user group
            $anonymouseUserOid = UserSelector::ANONYMOUS_USER_OID;
            $userFilter = <<<SQL
            
        apol.target_user_role_oid IN (
            SELECT
                turu.user_role_oid
            FROM
                exf_user_role_users turu
            WHERE
                turu.user_oid = $anonymouseUserOid
        )
        OR
SQL;
        } else {
            // Load all policies of this user's group
            // + all policies of the built-in group exface.Core.AUTHENTICATED
            // + all policies without a user group
            $authenticatedGroupOid = UserRoleSelector::AUTHENTICATED_USER_ROLE_OID;
            $userFilter = <<<SQL
            
        apol.target_user_role_oid IN (
            SELECT
                turu.user_role_oid
            FROM
                exf_user_role_users turu
                INNER JOIN exf_user u ON turu.user_oid = u.oid
            WHERE
                u.username = '{$userOrToken->getUsername()}'
        )
        OR apol.target_user_role_oid = $authenticatedGroupOid
        OR
SQL;
        }
        
        $sql = <<<SQL
SELECT
    apol.*,
    {$this->buildSqlUuidSelector('apol.target_page_group_oid')} AS target_page_group_oid,
    {$this->buildSqlUuidSelector('apol.target_user_role_oid')} AS target_user_role_oid,
    {$this->buildSqlUuidSelector('apol.target_object_oid')} AS target_object_oid
FROM
    exf_auth_policy apol
WHERE
    apol.auth_point_oid = {$authPoint->getUid()}
    AND apol.disabled_flag = 0
    AND (
        {$userFilter}
        apol.target_user_role_oid IS NULL
    )
SQL;
        
        foreach ($this->getDataConnection()->runSql($sql)->getResultArray() as $row) {            
            $authPoint->addPolicy(
                [
                    PolicyTargetDataType::USER_ROLE => $row['target_user_role_oid'],
                    PolicyTargetDataType::PAGE_GROUP => $row['target_page_group_oid'],
                    PolicyTargetDataType::META_OBJECT => $row['target_object_oid'],
                    PolicyTargetDataType::ACTION => $row['target_action_selector'],
                    PolicyTargetDataType::FACADE => $row['target_facade_selector'],
                ],
                PolicyEffectDataType::fromValue($this->getWorkbench(), $row['effect']),
                $row['name'],
                UxonObject::fromAnything($row['condition_uxon'] ?? [])
            );
        }
        
        return $authPoint;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadPage()
     */
    public function loadPage(UiPageSelectorInterface $selector, bool $ignoreReplacements = false) : UiPageInterface
    {
        if ($selector->isAlias()) {
            foreach ($this->pages_loaded as $uiPage) {
                if ($uiPage->getAliasWithNamespace() === $selector->toString()) {
                    return $uiPage;
                }
            }
            $where = "p.alias = '" . $selector->toString() . "'";
            $err = 'alias ' . $selector->toString();
        } elseif ($selector->isUid()) {
            if ($uiPage = $this->pages_loaded[$selector->toString()]) {
                return $uiPage;
            }
            $where = "p.oid = " . $selector->toString();
            $err = 'UID ' . $selector->toString();
        } else {
            throw new UiPageNotFoundError('Unsupported page selector ' . $selector->toString() . '!');
        }
        
        $query = $this->getDataConnection()->runSql("
            SELECT 
                p.*,
                {$this->buildSqlUuidSelector('p.oid')} as oid,
                {$this->buildSqlUuidSelector('p.parent_oid')} as parent_oid,
                {$this->buildSqlUuidSelector('p.page_template_oid')} as page_template_oid,
                {$this->buildSqlUuidSelector('p.created_by_user_oid')} as created_by_user_oid,
                {$this->buildSqlUuidSelector('p.modified_by_user_oid')} as modified_by_user_oid,
                pt.facade_filepath, 
                pt.facade_uxon,
                (
                    SELECT GROUP_CONCAT({$this->buildSqlUuidSelector('pgp.page_group_oid')}, ',')
                    FROM exf_page_group_pages pgp
                    WHERE pgp.page_oid = p.oid
                ) as group_oids
            FROM exf_page p 
                LEFT JOIN exf_page_template pt ON p.page_template_oid = pt.oid
            WHERE " . $where
            
        );
        $row = $query->getResultArray()[0];
        if (empty($row) === true) {
            throw new UiPageNotFoundError('UI Page with ' . $err . ' not found!');
        }
        
        $uiPage = UiPageFactory::createBlank($this->getWorkbench(), $row['alias']);
        $uiPage->setUid($row['oid']);
        if ($row['app_oid']) {
            $uiPage->setApp(SelectorFactory::createAppSelector($this->getWorkbench(), $row['app_oid']));
        }
        $uiPage->setName($row['name']);
        $uiPage->setDescription($row['description'] ?? '');
        $uiPage->setIntro($row['intro'] ?? '');
        $uiPage->setMenuIndex(intval($row['menu_index']));
        $uiPage->setMenuVisible($row['menu_visible'] ? true : false);
        $uiPage->setPublished($row['published'] ? true : false);
        
        if ($row['parent_oid']) {
            $uiPage->setParentPageSelector($row['parent_oid']);
        }
        
        $uiPage->setUpdateable($row['auto_update_with_app'] ? true : false);
        $uiPage->setReplacesPageSelector($row['replace_page_oid']);
        $uiPage->setContents($row['content'] ?? new UxonObject());
        
        $uiPage->setFacadeSelector($row['facade_filepath']);
        if ($row['facade_uxon']) {
            $uiPage->setFacadeConfig(new UxonObject(json_decode($row['facade_uxon'], true)));
        }
        
        if ($row['default_menu_parent_oid'] !== null) {
            $uiPage->setParentPageSelectorDefault($row['default_menu_parent_oid']);
        }
        if ($row['default_menu_index'] !== null) {
            $uiPage->setMenuIndexDefault($row['default_menu_index']);
        }
        
        if ($row['group_oids']) {
            foreach (explode(',', $row['group_oids']) as $groupUid) {
                $uiPage->addGroupSelector($groupUid);
            }
        }
        
        if ($row['created_on'] !== null) {            
            $uiPage->setCreatedOn($row['created_on']);
        }
        if ($row['created_by_user_oid'] !== null) {
            $uiPage->setCreatedByUser($row['created_by_user_oid']);
        }
        if ($row['modified_on'] !== null) {
            $uiPage->setModifiedOn($row['modified_on']);
        }
        if ($row['modified_by_user_oid'] !== null) {
            $uiPage->setModifiedByUser($row['modified_by_user_oid']);
        }
       
        $this->pages_loaded[$uiPage->getUid()] = $uiPage;
        
        return $uiPage;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadPageTree()
     */
    public function loadPageTree(UiPageTree $tree) : array
    {
        if (empty($tree->getStartRootNodes())) {
            $rows = $this->loadPageTreeLevel();
            $nodes = [];
            foreach ($rows as $row) {
                $rootNode = $this->loadPageTreeCreateNodeFromDbRow($row);
                $nodes[] = $rootNode;
                $this->nodes_loaded[$rootNode->getUid] = $rootNode;
            }
            
            $tree->setStartRootNodes($nodes);
        }        
        if ($tree->hasExpandPathToPage()) {            
            return $this->loadPageTreeParentNodes($tree);
        } else {
            $treeRootNodes = $tree->getStartRootNodes();
            $nodes = [];
            foreach ($treeRootNodes as $rootNode) {
                $nodes[] = $this->loadPageTreeChildNodes($tree, $rootNode, 0);
            }
            return $nodes;
        }
    }
    
    private function loadPageTreeCreateNodeFromDbRow(array $row, UiPageTreeNodeInterface $parentNode = null, $skipUnauthorized = false) : UiPageTreeNodeInterface
    {
        try {
            return UiPageTreeFactory::createNode(
                $this->getWorkbench(),
                $row['alias'],
                $row['name'],
                $row['oid'],
                ($row['published'] ? true : false),
                $parentNode,
                $row['description'],
                $row['intro'],
                $row['group_oids'] ? explode(',', $row['group_oids']) : null
            );
        } catch (AccessPermissionDeniedError $e) {
            if ($skipUnauthorized === true) {
                return null;
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Returns the root nodes for the given tree to a page set in `setExpandPathToPage` in the given tree
     * 
     * @param UiPageTree $tree
     * @return array
     */
    protected function loadPageTreeParentNodes(UiPageTree $tree) : array
    {
        $treeRootNodes = $tree->getStartRootNodes();
        $loadedtree = $this->menu_tress_loaded[$tree->getExpandPathToPage()->getUid()];
        if ($loadedtree !== null && $loadedtree->isLoaded() === true) {
            return $loadedtree->getRootNodes();
        }
        $nodeId = $tree->getExpandPathToPage()->getUid();
        while ($nodeId !== null) {
            $parentNode = null;
            $parentNodeId = null;
            if ($this->nodes_loaded[$nodeId] !== null && $this->nodes_loaded[$nodeId]->getChildNodesLoaded() === true && $this->nodes_loaded[$nodeId]->hasParent() === true) {
                // if node is already loaded with its childs and also has a parentNode, no need to load it again
                $parentNode = $this->nodes_loaded[$nodeId];
                $parentNodeId = $parentNode->getParentNode()->getUid();
            } else {
                // load node and its childs
                $rows = $this->loadPageTreeLevel($nodeId);
                foreach ($rows as $row) {
                    if ($row['oid'] === $nodeId) {
                        if ($this->nodes_loaded[$row['oid']] !== null) {
                            // if node already was loaded before, take that
                            $parentNode = $this->nodes_loaded[$row['oid']];
                        } else {
                            $parentNode = $this->loadPageTreeCreateNodeFromDbRow($row);
                            $this->nodes_loaded[$parentNode->getUid()] = $parentNode;
                        }
                        $parentNodeId = $row['parent_oid'];
                        break;
                        // when parent node was created no need to search in the rest of the rows
                    }
                }
            }
            if ($parentNode !== null && $parentNode->getChildNodesLoaded() === false) {
                foreach ($rows as $row) {
                    if ($parentNode !== null && $row['oid'] !== $nodeId) {
                        if ($this->nodes_loaded[$row['oid']] !== null && $this->nodes_loaded[$row['oid']]->getChildNodesLoaded() === true) {
                            //if the child node was already loaded before and also it's child, take that node
                            $childNode = $this->nodes_loaded[$row['oid']];
                            $childNode->setParentNode($parentNode);
                        } else {
                            $childNode = $this->loadPageTreeCreateNodeFromDbRow($row, $parentNode);
                        }
                        $this->nodes_loaded[$childNode->getUid()] = $childNode;
                        $parentNode->addChildNode($childNode);
                        $parentNode->setChildNodesLoaded(true);
                        $this->nodes_loaded[$parentNode->getUid()] = $parentNode;
                    }
                }
            }
            if ($tree->nodeInRootNodes($parentNode)) {
                $nodeId = null;
                for ($i = 0; $i < count($treeRootNodes); $i++) {
                    if ($treeRootNodes[$i]->getUid() === $parentNode->getUid()) {
                        $treeRootNodes[$i] = $parentNode;
                        break;
                    }
                }
            } else {
                $nodeId = $parentNodeId;
            }
        }
        $this->menu_tress_loaded[$tree->getExpandPathToPage()->getUid()] = $tree;
        return $treeRootNodes;
    }
    
    /**
     * 
     * @param UiPageTreeNodeInterface $node
     * @param UiPageTreeNodeInterface[] $childNodes
     * @return UiPageTreeNodeInterface
     */
    protected function loadPageTreeChildNodes(UiPageTree $tree, UiPageTreeNodeInterface $node, ?int $level) : UiPageTreeNodeInterface
    {
        $exface = $this->getWorkbench();
        $depth = $tree->getExpandDepth();        
        if ($level === null || $level < $depth) {
            $childNodes = null;
            if ($this->nodes_loaded[$node->getUid()] !== null && $this->nodes_loaded[$node->getUid()]->getChildNodesLoaded() === true) {
                $childNodes = $this->nodes_loaded[$node->getUid()];
                $node->resetChildNodes();
                foreach ($childNodes as $childNode) {
                    $childNode = $this->loadPageTreeChildNodes($tree, $childNode, $level + 1);
                    $node->addChildNode($childNode);                    
                }
                $node->setChildNodesLoaded(true);
                $this->nodes_loaded[$node->getUid()] = $node;
                return $node;
            } else {
                $rows = $this->loadPageTreeLevel($node->getUid(), true);                
                $childIds = [];
                foreach ($rows as $row) {
                    //build first level child nodes
                    if ($row['parent_oid'] === $node->getUid()  && !in_array($row['oid'], $childIds)) {
                        if ($this->nodes_loaded[$row['oid']] !== null) {
                            $childNode = $this->nodes_loaded[$row['oid']];
                        } else {
                            $childNode = $this->loadPageTreeCreateNodeFromDbRow($row, $node);
                            $this->nodes_loaded[$childNode->getUid()] = $childNode;
                        }
                        $childIds[] = $childNode->getUid();
                        $node->addChildNode($childNode);
                    }
                }                
                $node->setChildNodesLoaded(true);
                $childNodes = $node->getChildNodes();
            }
            if ($level === null || $level + 1 < $depth) {                
                $node->resetChildNodes();
                //build second level of child nodes
                foreach ($childNodes as $childNode) {
                    foreach ($rows as $row) {
                        if ($childNode->getUid() === $row['parent_oid']) {
                            if ($this->nodes_loaded[$row['oid']] !== null) {
                                $childChildNode = $this->nodes_loaded[$row['oid']];
                            } else {
                                $childChildNode = $this->loadPageTreeCreateNodeFromDbRow($row, $childNode);
                            }
                            //load sub levels for child child tree nodes if needed
                            $childChildNode = $this->loadPageTreeChildNodes($tree, $childChildNode, $level + 2);
                            $this->nodes_loaded[$childChildNode->getUid()] = $childChildNode;
                            $childNode->addChildNode($childChildNode);
                        }
                    }
                    $childNode->setChildNodesLoaded(true);
                    $this->nodes_loaded[$childNode->getUid()] = $childNode;                    
                    $node->addChildNode($childNode);
                }                
                $node->setChildNodesLoaded(true);
            }
        }
        return $node;
    }
    
    /**
     * Returns data for node with given `id` and all childs of that node, if `loadTwoLevels` is true returns data for two child levels for given `id` instead.
     * 
     * @param string $sqlWhere
     * @return array
     */
    protected function loadPageTreeLevel(string $id = null, bool $loadTwoLevels = false) : array
    {
        $sqlWhere = '';
        if ($id === null) {
            $sqlWhere = "
            WHERE parent_oid IS NULL AND menu_visible = 1";
        } else {
            $sqlWhere = "
            WHERE (oid = {$id} OR parent_oid = {$id}) AND menu_visible = 1";
        }
        $sqlOrder = "
            ORDER BY parent_oid, menu_index";
        $sql = "
            SELECT
                {$this->buildSqlUuidSelector('p.oid')} as oid,
                {$this->buildSqlUuidSelector('p.parent_oid')} as parent_oid,
                p.name,
                p.alias,
                p.description,
                p.intro,
                p.menu_index,
                (
                    SELECT GROUP_CONCAT({$this->buildSqlUuidSelector('pgp.page_group_oid')}, ',')
                    FROM exf_page_group_pages pgp
                    WHERE pgp.page_oid = p.oid
                ) as group_oids
            FROM exf_page p";
                
        if ($loadTwoLevels === true) {
            $sqlUnionInnerWhere = '';
            if ($id === null) {
                $sqlUnionInnerWhere = $sqlWhere;
            } else {
                $sqlUnionInnerWhere = "
                WHERE p.parent_oid = {$id} AND menu_visible = 1";
            }
            $sqlUnionWhere = "
               WHERE p.parent_oid IN (SELECT
	           oid
	           FROM exf_page {$sqlUnionInnerWhere})";
            $sql .= "            
            {$sqlWhere}
            UNION ALL
                {$sql}
                {$sqlUnionWhere}";
                
        } else {
            $sql .= $sqlWhere;
        }
        
        $sql = "#load UiPageTree Data" . $sql . $sqlOrder;
        $query = $this->getDataConnection()->runSql($sql);
        $rows = $query->getResultArray();
        return $rows;        
    }
}

?>