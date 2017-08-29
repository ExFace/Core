<?php

namespace exface\Core\ModelLoaders;

use exface\Core\Interfaces\DataSources\ModelLoaderInterface;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSorterFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaModelLoadingFailedError;
use exface\Core\CommonLogic\Model\ObjectActionList;
use exface\Core\CommonLogic\Model\ActionList;
use exface\Core\CommonLogic\Model\AppActionList;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\AppInstallers\SqlSchemaInstaller;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\Model\ModelInterface;

class SqlModelLoader implements ModelLoaderInterface
{

    private $data_connection = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadObjectById($object_id)
     */
    public function loadObjectById(ModelInterface $model, $object_id)
    {
        $obj = new \exface\Core\CommonLogic\Model\Object($model);
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
        $obj = new \exface\Core\CommonLogic\Model\Object($app->getWorkbench()->model());
        $obj->setAlias($object_alias);
        $obj->setNamespace($app->getAliasWithNamespace());
        return $this->loadObject($obj);        
    }

    /**
     * Loads metamodel data into the given object
     * 
     * @param Object $object
     * @return Object
     */
    protected function loadObject(Object $object)
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
					' . $this->generateSqlUuidSelector('o.oid') . ' as oid,
					' . $this->generateSqlUuidSelector('o.app_oid') . ' as app_oid,
					a.app_alias,
					o.object_name,
					o.object_alias,
					o.data_address,
					o.data_address_properties,
					' . $this->generateSqlUuidSelector('o.data_source_oid') . ' as data_source_oid,
					' . $this->generateSqlUuidSelector('o.parent_object_oid') . ' as parent_object_oid,
					o.short_description,
					o.long_description,
					o.default_editor_uxon,
					' . $this->generateSqlUuidSelector('ds.base_object_oid') . ' as base_object_oid,
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
            if ($row['base_object_oid'] && $row['base_object_oid'] != $object->getId()) {
                $object->extendFromObjectId($row['base_object_oid']);
            }
            if ($row['parent_object_oid']) {
                $object->extendFromObjectId($row['parent_object_oid']);
            }
            
            // Overwrite inherited properties
            if (is_null($object->getDataAddress()) || $object->getDataAddress() == '' || (! is_null($row['data_address']) && ! $row['data_address'] == '')) {
                $object->setDataAddress($row['data_address']);
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
					' . $this->generateSqlUuidSelector('a.oid') . ' as oid,
					' . $this->generateSqlUuidSelector('a.object_oid') . ' as object_oid,
					' . $this->generateSqlUuidSelector('a.related_object_oid') . ' as related_object_oid,
					' . $this->generateSqlUuidSelector('a.related_object_special_key_attribute_oid') . ' as related_object_special_key_attribute_oid,
					d.data_type_alias,
					d.default_widget_uxon AS default_data_type_editor,
					o.object_alias as rev_relation_alias,
					o.object_name AS rev_relation_name
				FROM exf_attribute a LEFT JOIN exf_object o ON a.object_oid = o.oid LEFT JOIN exf_data_type d ON d.oid = a.data_type_oid
				WHERE a.object_oid = ' . $object->getId() . ' OR a.related_object_oid = ' . $object->getId());
        if ($res = $query->getResultArray()) {
            // use a for here instead of foreach because we want to extend the array from within the loop on some occasions
            $l = count($res);
            for ($i = 0; $i < $l; $i ++) {
                $row = $res[$i];
                // Only create attributes, that really belong to this object. Inherited attributes are already there.
                if ($row['object_oid'] == $object->getId()) {
                    // save the label attribute alias in object head
                    if ($row['object_label_flag']) {
                        $object->setLabelAlias($row['attribute_alias']);
                        // always add a LABEL attribute if it is not already called LABEL (widgets always need to show the LABEL!)
                        // IDEA cleaner code does not work for some reason. Didn't have time to check out why...
                        /*
                         * if ($row['attribute_alias'] != $object->getModel()->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS')){
                         * $label_attribute = attribute::from_db_row($row);
                         * $label_attribute->setAlias($object->getModel()->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS'));
                         * $label_attribute->setDefaultDisplayOrder(-1);
                         * $object->getAttributes()->add($label_attribute);
                         * }
                         */
                        if ($row['attribute_alias'] != $object->getModel()->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS')) {
                            $label_attribute = $row;
                            $label_attribute['attribute_alias'] = $object->getModel()->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS');
                            $label_attribute['attribute_hidden_flag'] = '1';
                            $label_attribute['attribute_required_flag'] = '0';
                            $label_attribute['attribute_editable_flag'] = '0';
                            // The special label attribute should not be marked as label because it then would be returned by get_label..(),
                            // which instead should return the original attribute
                            $label_attribute['object_label_flag'] = 0;
                            // If label and UID are one attribute, make sure the special LABEL attribute will not be treated as a second UID!
                            $label_attribute['object_uid_flag'] = '0';
                            unset($label_attribute['default_display_order']);
                            $res[] = $label_attribute;
                            $l ++;
                        }
                    }
                    
                    // check if an attribute is marked as unique id for this object
                    if ($row['object_uid_flag']) {
                        $object->setUidAlias($row['attribute_alias']);
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
                }
                
                // Now populate relations, if the attribute is a relation. This is done for own attributes as well as inherited ones because
                // the latter may be involved in reverse relations. But this means also, that direct relations can only be created from direct
                // attributes.
                if ($row['related_object_oid']) {
                    // we have a reverse (1-n) relation if the attribute belongs to another object and that object is not being extended from
                    // Otherwise it's a normal n-1 relation
                    // IDEA What if we also create relations between parent and child objects. The inheriting object should probably get a direct
                    // relation to the parent. Would that be usefull for objects sharing attributes but using different data_addresses?
                    if ($object->getId() != $row['object_oid'] && ! in_array($row['object_oid'], $object->getParentObjectsIds())) {
                        // FIXME what is the related_object_key_alias for reverse relations?
                        $rel = new Relation(
                            $exface, 
                            $row['oid'], // id
                            $row['rev_relation_alias'], // alias
                            $row['rev_relation_name'], // name (used for captions)
                            $row['related_object_oid'], // main object
                            $row['attribute_alias'], // foreign key in the main object
                            $row['object_oid'], // related object
                            null, // related object key attribute (uid)
                            Relation::RELATION_TYPE_REVERSE); // relation type
                    } elseif ($attr) {
                        // At this point, we know, it is a direct relation. This can only happen if the object has a corresponding direct
                        // attribute. This is why the elseif($attr) is there.
                        $rel = new Relation($exface, $attr->getId(), $attr->getAlias(), $attr->getName(), $object->getId(), $attr->getAlias(), $row['related_object_oid'], $row['related_object_special_key_attribute_oid'], Relation::RELATION_TYPE_FORWARD);
                    }
                    
                    if ($rel) {
                        $object->addRelation($rel);
                    }
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

    protected function createAttributeFromDbRow(Object $object, array $row)
    {
        $model = $object->getModel();
        $attr = new Attribute($model);
        // ensure the attributes all have the correct parent object (because inherited attributes actually would
        // have another object_id in their row data)
        $attr->setObjectId($object->getId());
        $attr->setId($row['oid']);
        $attr->setAlias($row['attribute_alias']);
        $attr->setName($row['attribute_name']);
        $attr->setDataAddress($row['data']);
        $attr->setDataAddressProperties(UxonObject::fromJson($row['data_properties']));
        $attr->setFormatter($row['attribute_formatter']);
        $attr->setDataType($row['data_type_alias']);
        $attr->setRequired($row['attribute_required_flag']);
        $attr->setEditable($row['attribute_editable_flag']);
        $attr->setHidden($row['attribute_hidden_flag']);
        $attr->setSystem($row['system_flag']);
        $attr->setSortable($row['attribute_sortable_flag']);
        $attr->setFilterable($row['attribute_filterable_flag']);
        $attr->setAggregatable($row['attribute_aggregatable_flag']);
        $attr->setDefaultDisplayOrder($row['default_display_order']);
        $attr->setRelationFlag($row['related_object_oid'] ? true : false);
        $attr->setDefaultValue($row['default_value']);
        $attr->setFixedValue($row['fixed_value']);
        $attr->setFormula($row['attribute_formula']);
        if ($row['default_sorter_dir']){
            $attr->setDefaultSorterDir($row['default_sorter_dir']);
        }
        $attr->setShortDescription($row['attribute_short_description']);
        $attr->setDefaultAggregateFunction($row['default_aggregate_function']);
        $attr->setValueListDelimiter($row['value_list_delimiter']);
        
        // Create the UXON for the default editor widget
        // Start with the data type widget
        $uxon = UxonObject::fromJson($row['default_data_type_editor']);
        // If anything goes wrong, create a blank widget with the overall default widget type (from the config)
        if (! $uxon) {
            $uxon = new UxonObject();
            $uxon->setProperty('widget_type', $model->getWorkbench()->getConfig()->getOption('TEMPLATES.WIDGET_FOR_UNKNOWN_DATA_TYPES'));
        }
        // Extend by the specific uxon for this attribute if specified
        if ($row['default_editor_uxon']) {
            $uxon = $uxon->extend(UxonObject::fromJson($row['default_editor_uxon']));
        }
        $attr->setDefaultWidgetUxon($uxon);
        
        return $attr;
    }

    public function loadDataSource(DataSourceInterface $data_source, $data_connection_id_or_alias = NULL)
    {
        $exface = $data_source->getWorkbench();
        // If the data connector was not set for this data source previously, load it now
        if (! $data_source->getDataConnectorAlias()) {
            if ($data_connection_id_or_alias) {
                // See if a (hex-)ID is given or an alias. The latter will need to be wrapped in qotes!
                if (strpos($data_connection_id_or_alias, '0x') !== 0) {
                    $data_connection_id_or_alias = '"' . $data_connection_id_or_alias . '"';
                }
                $join_on = "(dc.oid = " . $data_connection_id_or_alias . " OR dc.alias = " . $data_connection_id_or_alias . ")";
            } else {
                $join_on = 'IF (ds.custom_connection_oid IS NOT NULL, ds.custom_connection_oid, ds.default_connection_oid) = dc.oid';
            }
            
            // If there is a user logged in, fetch his specific connctor config (credentials)
            if ($user_name = $data_source->getWorkbench()->context()->getScopeUser()->getUserName()) {
                $join_user_credentials = ' LEFT JOIN (exf_data_connection_credentials dcc LEFT JOIN exf_user_credentials uc ON dcc.user_credentials_oid = uc.oid INNER JOIN exf_user u ON uc.user_oid = u.oid AND u.username = "' . $user_name . '") ON dcc.data_connection_oid = dc.oid';
                $select_user_credentials = ', uc.data_connector_config AS user_connector_config';
            }
            
            $sql = '
				SELECT 
					ds.custom_query_builder,
					ds.default_query_builder, 
					ds.read_only_flag AS data_source_read_only, 
					dc.read_only_flag AS connection_read_only, 
					CONCAT(\'0x\', HEX(dc.oid)) AS data_connection_oid, 
					dc.name, 
					dc.data_connector, 
					dc.data_connector_config, 
					dc.filter_context_uxon' . $select_user_credentials . ' 
				FROM exf_data_source ds LEFT JOIN exf_data_connection dc ON ' . $join_on . $join_user_credentials . ' 
				WHERE ds.oid = ' . $data_source->getId();
            $query = $this->getDataConnection()->runSql($sql);
            $ds = $query->getResultArray();
            if (count($ds) > 1) {
                throw new RangeException('Multiple user credentials found for data connection "' . $data_connection_id_or_alias . '" and user "' . $user_name . '"!', '6T4R8UM');
            } elseif (count($ds) != 1) {
                throw new RangeException('Cannot find data connection "' . $data_connection_id_or_alias . '"!', '6T4R97R');
            }
            $ds = $ds[0];
            $data_source->setDataConnectorAlias($ds['data_connector']);
            $data_source->setConnectionId($ds['data_connection_oid']);
            $data_source->setReadOnly(($ds['data_source_read_only'] || $ds['connection_read_only']) ? true : false);
            // Some data connections may have their own filter context. Add them to the application context scope
            if ($ds['filter_context_uxon'] && $filter_context = json_decode($ds['filter_context_uxon'])) {
                if (! is_array($filter_context)) {
                    $filter_context = array(
                        $filter_context
                    );
                }
                foreach ($filter_context as $filter) {
                    $condition = ConditionFactory::createFromObjectOrArray($exface, $filter);
                    $data_source->getWorkbench()->context()->getScopeApplication()->getFilterContext()->addCondition($condition);
                }
            }
        }
        
        // The query builder: if not given, use the default one from the data source configuration
        if (! $data_source->getQueryBuilderAlias()) {
            $data_source->setQueryBuilderAlias($ds['custom_query_builder'] ? $ds['custom_query_builder'] : $ds['default_query_builder']);
        }
        
        // The configuration of the connection: if not given, get the configuration from DB
        $data_source->setConnectionId($ds['data_connection_oid']);
        $config = UxonObject::fromJson($ds['data_connector_config']);
        $config = $config->extend(UxonObject::fromJson($ds['user_connector_config']));
        if (is_object($config)) {
            $config = (array) $config;
        }
        $data_source->setConnectionConfig($config);
        
        return $data_source;
    }

    /**
     * Ensures that binary UID fields are selected as 0xNNNNN to be compatible with the internal binary notation in ExFace
     * 
     * @param string $field_name            
     * @return string
     */
    protected function generateSqlUuidSelector($field_name)
    {
        return 'CONCAT(\'0x\', HEX(' . $field_name . '))';
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
        if (! ($connection instanceof SqlDataConnectorInterface)) {
            throw new MetaModelLoadingFailedError('Cannot use data connection "' . get_class($connection) . '" for the SQL model loader: the connection must implement the SqlDataConnector interface!');
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
    public function loadObjectActions(ObjectActionList $empty_list)
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

    public function loadAction(AppInterface $app, $action_alias, WidgetInterface $called_by_widget = null)
    {
        $sql_where = 'a.app_alias = "' . $app->getAliasWithNamespace() . '" AND oa.alias = "' . $action_alias . '"';
        $actions = $this->loadActionsFromModel(new AppActionList($app->getWorkbench(), $app), $sql_where, $called_by_widget);
        return $actions->getFirst();
    }

    /**
     *
     * @param ActionList $action_list            
     * @param string $sql_where            
     * @return \exface\Core\CommonLogic\Model\ActionList
     */
    protected function loadActionsFromModel(ActionList $action_list, $sql_where, WidgetInterface $called_by_widget = null)
    {
        $basket_aliases = ($action_list instanceof ObjectActionList) ? $action_list->getObjectBasketActionAliases() : array();
        
        $query = $this->getDataConnection()->runSql('
				SELECT
					' . $this->generateSqlUuidSelector('oa.object_oid') . ' AS object_oid,
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
                $object = $action_list instanceof ObjectActionList ? $action_list->getMetaObject() : $action_list->getWorkbench()->model()->getObjectById($row['object_oid']);
                $a = ActionFactory::createFromModel($row['action'], $row['alias'], $app, $object, $action_uxon, $called_by_widget);
                $a->setName($row['name']);
                $action_list->add($a);
                
                if ($row['use_in_object_basket_flag']) {
                    $basket_aliases[] = $a->getAliasWithNamespace();
                }
            }
        }
        
        if ($action_list instanceof ObjectActionList) {
            $action_list->setObjectBasketActionAliases($basket_aliases);
        }
        
        return $action_list;
    }

    public function getInstaller()
    {
        $installer = new SqlSchemaInstaller(NameResolver::createFromString('exface.Core', NameResolver::OBJECT_TYPE_APP, $this->getDataConnection()->getWorkbench()));
        $installer->setDataConnection($this->getDataConnection());
        return $installer;
    }
    
    /**
     *
     *
     * @param Object $object
     */
    public function loadAttribute(Object $object, $attribute_alias)
    {
        return $object->getAttribute($attribute_alias);
    }
    
    /**
     *
     *
     * @param Object $object
     */
    public function loadRelation(Object $object, $relation_alias)
    {
        return $object->getRelation($relation_alias);   
    }
}

?>