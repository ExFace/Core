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
use exface\Core\CommonLogic\Model\UiPageTree;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\DataTypes\PolicyTargetDataType;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Factories\AuthorizationPointFactory;
use exface\Core\CommonLogic\Selectors\AuthorizationPointSelector;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\DataTypes\UxonDataType;
use exface\Core\Exceptions\Security\AccessDeniedError;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Events\Model\OnMetaObjectActionLoadedEvent;
use exface\Core\Events\Model\OnUiMenuItemLoadedEvent;
use exface\Core\Events\Model\OnUiPageLoadedEvent;
use exface\Core\Events\Model\OnBeforeMetaObjectActionLoadedEvent;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * Loads metamodel entities from SQL databases supporting the MySQL dialect.
 * 
 * For historical reasons, this generic SQL model loader is actually MySQL
 * oriented. Use database specific loaders if possible!
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
     * Builds a sql statement selecting a "1" as `$sqlAs` if a dataset exists in the database matching the `sqlFrom` and `sqlWhere` options.
     * 
     * @param string $sqlFrom
     * @param string $sqlWhere
     * @param string $sqlAs
     * @return string
     */
    protected function buildSqlExists(string $sqlFrom, string $sqlWhere, string $sqlAs) : string
    {
        return "(CASE WHEN EXISTS (SELECT * FROM {$sqlFrom} WHERE {$sqlWhere} ) THEN 1 ELSE 0 END) AS {$sqlAs}";
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
            $q_where = "a.app_alias = '{$this->buildSqlEscapedString($object->getNamespace())}' AND o.object_alias = '{$this->buildSqlEscapedString($object->getAlias())}'";
        }
        $exists = $this->buildSqlExists('exf_object_behaviors ob', 'ob.object_oid = o.oid', 'has_behaviors');
        $query = $this->getDataConnection()->runSql('
				SELECT
                    o.*,
					' . $this->buildSqlUuidSelector('o.oid') . ' as oid,
					' . $this->buildSqlUuidSelector('o.app_oid') . ' as app_oid,
					' . $this->buildSqlUuidSelector('o.data_source_oid') . ' as data_source_oid,
					' . $this->buildSqlUuidSelector('o.parent_object_oid') . ' as parent_object_oid,
					a.app_alias,
					' . $this->buildSqlUuidSelector('ds.base_object_oid') . ' as base_object_oid,
					' . $exists . '
				FROM exf_object o 
					LEFT JOIN exf_app a ON o.app_oid = a.oid 
					LEFT JOIN exf_data_source ds ON o.data_source_oid = ds.oid
				WHERE ' . $q_where);
        if ($res = $query->getResultArray()) {
            $row = $res[0];
            
            $object->setId($row['oid']);
            $object->setAlias($row['object_alias']);
            $object->setDataSourceId($row['data_source_oid']);
            $object->setAppId($row['app_oid']);
            $object->setNamespace($row['app_alias']);
            
            $object->setName($row['object_name']);
            
            if ($row['has_behaviors']) {
                $load_behaviors = true;
            }
            
            // Take care of inheritance: first the data source base object, than the
            // explicit parent if specified. Also avoid reccurrance with self-inheritance.
            // If an explicit parent is specified, load it first
            if ($row['parent_object_oid'] && $row['parent_object_oid'] !== $object->getId()) {
                $parent = $this->getModel()->getObject($row['parent_object_oid']);
            } else {
                $parent = null;
            }
            // See if the data source has a base object. If so, double-check 
            // - that it was not already inherited by the parent object (should not inherit twice as this would register all behaviors twice too!) 
            // - that base inheritance is not turned off for this particular object 
            if ($row['base_object_oid'] && $row['base_object_oid'] !== $object->getId() && ! ($parent && $parent->isExtendedFrom($row['base_object_oid'])) && ($row['inherit_data_source_base_object'] ?? 1)) {
                $baseObject = $this->getModel()->getObject($row['base_object_oid']);
                $object->extendFromObject($baseObject);
            }
            // Now that we handled the base object, we can extend from the explicit parent.
            // Still double check if it's the same object in case the user accidently specified
            // the same object as base and parent in the metamodel.
            if ($parent && $parent !== $baseObject) {
                $object->extendFromObject($parent);
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
            if ($default_editor_uxon = $row['default_editor_uxon']) {
                if ($default_editor_uxon !== null && $default_editor_uxon !== '' && $default_editor_uxon !== '{}') {
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
                        if ($row['attribute_alias'] != MetaAttributeInterface::OBJECT_LABEL_ALIAS) {
                            $label_attribute = $row;
                            $label_attribute['attribute_alias'] = MetaAttributeInterface::OBJECT_LABEL_ALIAS;
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
                    
                    if ($row['object_uid_flag']) {
                        $attr->setSystem(true);
                    }
                    
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
            $parentObjIds = $object->getParentObjectsIds();
            foreach ($relation_attrs as $data) {
                $attr = $data['attr'];
                $row = $data['row'];
                
                // If we have a reverse (1-n) relation if the attribute belongs to another object and that
                // object is not being extended from. Otherwise it's a normal n-1 relation.
                $thisObjId = $object->getId();
                $attrObjId = $row['object_oid'];
                $attrBelongsToOtherObj = ($thisObjId !== $attrObjId && false === in_array($attrObjId, $parentObjIds));
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
                    $behavior = BehaviorFactory::createFromUxon($object, $row['behavior'], UxonObject::fromJson($row['config_uxon']), $row['app_oid']);
                    $object->getBehaviors()->add($behavior);
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnMetaObjectLoadedEvent($object));
        
        return $object;
    }

    /**
     * 
     * @param MetaObjectInterface $object
     * @param array $row
     * @return \exface\Core\CommonLogic\Model\CompoundAttribute|\exface\Core\CommonLogic\Model\Attribute
     */
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
            $attr->setDefaultEditorUxon($default_editor);
        }
        $default_display = $row['default_display_uxon'];
        if ($default_display && $default_display !== '{}'){
            $attr->setDefaultDisplayUxon($default_display);
        }
        $custom_type = $row['custom_data_type_uxon'];
        if ($custom_type && $custom_type !== '{}') {
            $attr->setCustomDataTypeUxon($custom_type);
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
        $attr->setCopyable($row['attribute_copyable_flag'] ?? $row['attribute_editable_flag']);
        $attr->setHidden($row['attribute_hidden_flag']);
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
     * Builds an sql CASE WHEN-THEN-ELSE statement.
     * 
     * @param string $sqlWhen
     * @param string $sqlThen
     * @param string $sqlElse
     * @return string
     */
    protected function buildSqlCaseWhenThenElse(string $sqlWhen, string $sqlThen, string $sqlElse) : string
    {
        return "(CASE WHEN {$sqlWhen} THEN {$sqlThen} ELSE {$sqlElse} END)";
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
            //$join_on = 'IF (ds.custom_connection_oid IS NOT NULL, ds.custom_connection_oid, ds.default_connection_oid) = dc.oid';
            $join_on = "{$this->buildSqlCaseWhenThenElse('ds.custom_connection_oid IS NOT NULL', 'ds.custom_connection_oid', 'ds.default_connection_oid')} = dc.oid";
        }
        
        // If there is a user logged in, fetch his specific connctor config (credentials)
        $authToken = $exface->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous() === false && $user_name = $authToken->getUsername()) {
            $join_user_credentials = " LEFT JOIN (exf_data_connection_credentials dcc LEFT JOIN exf_user_credentials uc ON dcc.oid = uc.data_connection_credentials_oid INNER JOIN exf_user u ON uc.user_oid = u.oid AND u.username = '{$this->buildSqlEscapedString($user_name)}') ON dcc.data_connection_oid = dc.oid";
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
    
    /**
     * 
     * @param array $row
     * @throws DataConnectionNotFoundError
     * @return DataConnectionInterface
     */
    protected function createDataConnectionFromDbRow(array $row) : DataConnectionInterface
    {
        try {
            $connectorSelector = new DataConnectorSelector($this->getWorkbench(), $row['data_connector']);
        } catch (\Throwable $e) {
            throw new DataConnectionNotFoundError('Invalid or missing connector prototype in data connection "' . $row['data_connection_name'] . '" (' . $row['data_connection_alias'] . ')!');
        }
        // Merge config from the connection and the user credentials
        $datatype = DataTypeFactory::createFromString($this->getWorkbench(), 'exface.Core.Encrypted');
        $configData = $row['data_connector_config'];
        if ($datatype->isValueEncrypted($configData)) {
            $configData = EncryptedDataType::decrypt(EncryptedDataType::getSecret($this->getWorkbench()), $configData, $datatype->getEncryptionPrefix());
        }
        $config = UxonObject::fromJson($configData);
        if ($row['user_connector_config'] !== null && $row['user_connector_config'] !== '' ) {
            $value = $row['user_connector_config'];
            try {
                if ($datatype->isValueEncrypted($value)) {
                    $value = EncryptedDataType::decrypt(EncryptedDataType::getSecret($this->getWorkbench()), $value, $datatype->getEncryptionPrefix());
                }
                UxonDataType::cast($value);
                $config = $config->extend(UxonObject::fromJson($value));
            } catch(\Throwable $e) {                
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadDataConnection()
     */
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
            if ($selector->hasNamespace()) {
                $appAlias = $selector->getAppAlias();
                $alias = substr($selector->toString(), (strlen($appAlias)+1));
                $filter = "dc.alias = '{$this->buildSqlEscapedString($alias)}' AND a.app_alias = '{$this->buildSqlEscapedString($appAlias)}'";
            } else {
                $filter = "dc.alias = '{$this->buildSqlEscapedString($selector->toString())}'";
            }
        }
        
        // If there is a user logged in, fetch his specific connctor config (credentials)
        $authToken = $exface->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous() === false && $user_name = $authToken->getUsername()) {
            $join_user_credentials = " LEFT JOIN (exf_data_connection_credentials dcc LEFT JOIN exf_user_credentials uc ON dcc.oid = uc.data_connection_credentials_oid INNER JOIN exf_user u ON uc.user_oid = u.oid AND u.username = '{$this->buildSqlEscapedString($user_name)}') ON dcc.data_connection_oid = dc.oid";
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
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::getDataConnection()
     */
    public function getDataConnection()
    {
        return $this->data_connection;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::setDataConnection()
     */
    public function setDataConnection(DataConnectionInterface $connection)
    {
        if (! ($connection instanceof SqlDataConnectorInterface)) {
            throw new \RuntimeException('Incompatible connector "' . $connection->getPrototypeClassName() . '" used for the model loader "' . get_class($this) . '": expecting a connector implementing SqlDataConnectorInterface.');
        }
        $this->data_connection = $connection;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadObjectActions()
     */
    public function loadObjectActions(MetaObjectActionListInterface $empty_list)
    {
        $object_id_list = implode(',', $empty_list->getMetaObject()->getParentObjectsIds());
        $object_id_list = $empty_list->getMetaObject()->getId() . ($object_id_list ? ',' . $object_id_list : '');
        $sql_where = 'oa.object_oid IN (' . $object_id_list . ')';
        return $this->loadActionsFromModel($empty_list, $sql_where);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadAppActions()
     */
    public function loadAppActions(AppActionList $empty_list)
    {
        $sql_where = 'a.app_alias = "' . $empty_list->getApp()->getAliasWithNamespace() . '"';
        return $this->loadActionsFromModel($empty_list, $sql_where);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadAction()
     */
    public function loadAction(AppInterface $app, $action_alias, WidgetInterface $trigger_widget = null)
    {
        $sql_where = "a.app_alias = '{$this->buildSqlEscapedString($app->getAliasWithNamespace())}' AND oa.alias = '{$this->buildSqlEscapedString($action_alias)}'";
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
                $action_uxon = UxonObject::fromAnything($row['config_uxon'] ?? '{}');
                $app = $action_list->getWorkbench()->getApp($row['app_alias']);
                $object = $action_list instanceof MetaObjectActionListInterface ? $action_list->getMetaObject() : $action_list->getWorkbench()->model()->getObjectById($row['object_oid']);
                
                if (! $action_uxon->hasProperty('name')) {
                    $action_uxon->setProperty('name', $row['name']);
                }
                if (! $action_uxon->hasProperty('hint')) {
                    $action_uxon->setProperty('hint', $row['short_description']);
                }
                
                $this->getWorkbench()->eventManager()->dispatch(new OnBeforeMetaObjectActionLoadedEvent($row['action'], $row['alias'], $app, $object, $action_uxon, $trigger_widget));
                
                $a = ActionFactory::createFromModel($row['action'], $row['alias'], $app, $object, $action_uxon, $trigger_widget);
                
                $this->getWorkbench()->eventManager()->dispatch(new OnMetaObjectActionLoadedEvent($object, $a));
                
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::getInstaller()
     */
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
            $default_display_uxon = UxonObject::fromJson($cache['default_display_uxon']);
            $data_type = DataTypeFactory::createFromModel($cache['prototype'], $cache['data_type_alias'], $this->getWorkbench()->getApp($cache['app_alias']), $uxon, $cache['name'], $cache['short_description'], $cache['validation_error_code'], $cache['validation_error_text'], $default_editor_uxon, $default_display_uxon);
            $this->data_types_by_uid[$cache['oid']] = $data_type;
            return $data_type;
        } else {
            throw new RuntimeException('Invalid cache state in the SqlModelLoader: unexpected "' . gettype($cache) . '" found in data type cache!');
        }
    }
    
    /**
     * 
     * @param DataTypeSelectorInterface $selector
     * @return mixed
     */
    protected function getDataTypeCache(DataTypeSelectorInterface $selector)
    {
        if ($selector->isUid()){
            return $this->data_types_by_uid[$selector->toString()];
        } else {
            return $this->data_types_by_uid[$this->data_type_uids[$selector->toString()]];
        }
    }
    
    /**
     * 
     * @param DataTypeSelectorInterface $selector
     * @return \exface\Core\ModelLoaders\SqlModelLoader
     */
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
        foreach ($query->getResultArray() as $row) {
            $this->data_types_by_uid[$row['oid']] = $row;
            $this->data_type_uids[$this->addNamespace($row['app_alias'], $row['data_type_alias'])] = $row['oid'];
        }
        return $this;
    }
    
    /**
     * 
     * @param string $app_alias
     * @param string $instance_alias
     * @return string
     */
    protected function addNamespace(string $app_alias, string $instance_alias) : string
    {
        return $app_alias . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $instance_alias;
    }
    
    /**
     * 
     * @return ModelInterface
     */
    protected function getModel() : ModelInterface
    {
        return $this->getWorkbench()->model();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
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
    
    /**
     * Builds sql statement selecting and combining the values of given `sqlColumn` matching the `sqlFrom` and `sqlWhere` into one
     * into one, comma seperated, string. 
     * 
     * @param string $sqlColumn
     * @param string $sqlFrom
     * @param string $sqlWhere
     * @return string
     */
    protected function buildSqlGroupConcat(string $sqlColumn, string $sqlFrom, string $sqlWhere) : string
    {
        return <<<SQL
        
        SELECT GROUP_CONCAT({$sqlColumn}, ',')
        FROM {$sqlFrom}
        WHERE {$sqlWhere}
SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadUserData()
     */
    public function loadUserData(UserInterface $user, DataSheetInterface $userData = null) : UserInterface
    {
        $groupConcat = $this->buildSqlGroupConcat($this->buildSqlUuidSelector('uru.user_role_oid'), 'exf_user_role_users uru', 'uru.user_oid = u.oid');
        if ($user->isAnonymous()) {
            $sqlWhere = "u.oid = " . UserSelector::ANONYMOUS_USER_OID;
        } else {
            $sqlWhere = "u.username = '{$this->buildSqlEscapedString($user->getUsername())}'";
        }
        $sql = <<<SQL
SELECT
    u.*,
    {$this->buildSqlUuidSelector('u.oid')} AS oid,
    (
        {$groupConcat}
    ) as role_oids
FROM
    exf_user u
WHERE
    {$sqlWhere}
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
                $user->setDisabled(BooleanDataType::cast($row['disabled_flag']) ?? false);
                if ($row['password'] !== null) {
                    $user->setPassword($row['password']);
                }
                if ($row['role_oids']) {
                    foreach (explode(',', rtrim($row['role_oids'], ",")) as $roleUid) {
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
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadAuthorizationPoints()
     */
    public function loadAuthorizationPoints() : array
    {        
        $sql = <<<SQL
SELECT 
    apt.*, 
    {$this->buildSqlUuidSelector('apt.oid')} AS oid,
    {$this->buildSqlUuidSelector('apt.app_oid')} AS app_oid
FROM 
    exf_auth_point apt
SQL;
        
        $result = $this->getDataConnection()->runSql($sql)->getResultArray();
        $array = [];
        foreach ($result as $row) {
            $authPoint = AuthorizationPointFactory::createFromSelector(new AuthorizationPointSelector($this->getWorkbench(), ltrim($row['class'], "\\")));
            $authPoint
                ->setName($row['name'])
                ->setUid($row['oid'])
                ->setDisabled(BooleanDataType::cast($row['disabled_flag']))
                ->setDefaultPolicyEffect(PolicyEffectDataType::fromValue($authPoint->getWorkbench(), ($row['default_effect_local'] ? $row['default_effect_local'] : $row['default_effect_in_app'])))
                ->setPolicyCombiningAlgorithm(PolicyCombiningAlgorithmDataType::fromValue($authPoint->getWorkbench(), ($row['combining_algorithm_local'] ? $row['combining_algorithm_local'] : $row['combining_algorithm_in_app'])));
            $array[] = $authPoint;
        }
        return $array;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::loadAuthorizationPolicies()
     */
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
                u.username = '{$this->buildSqlEscapedString($userOrToken->getUsername())}'
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
    {$this->buildSqlUuidSelector('apol.target_object_oid')} AS target_object_oid,
    {$this->buildSqlUuidSelector('apol.target_object_action_oid')} AS target_object_action_oid,
    baction.alias AS target_object_action_alias,
    capp.app_alias AS target_object_action_app
FROM
    exf_auth_policy apol
LEFT JOIN exf_object_action baction ON apol.target_object_action_oid = baction.oid
LEFT JOIN exf_app capp ON baction.action_app_oid = capp.oid
WHERE
    apol.auth_point_oid = {$authPoint->getUid()}
    AND apol.disabled_flag = 0
    AND (
        {$userFilter}
        apol.target_user_role_oid IS NULL
    )
SQL;
        foreach ($this->getDataConnection()->runSql($sql)->getResultArray() as $row) {
            $action = null;
            if ($row['target_object_action_oid'] !== null && $row['target_action_class_path'] !== null && $row['target_action_class_path'] !== '') {
                throw new RuntimeException('Policy cant have object action and action prototype values!');
            }
            if ($row['target_action_class_path'] !== null && $row['target_action_class_path'] !== '') {
                $action = $row['target_action_class_path'];
            } else if ($row['target_object_action_oid'] !== null) {
                $action = $row['target_object_action_app'] . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $row['target_object_action_alias'];
            }
            $authPoint->addPolicy(
                [
                    PolicyTargetDataType::USER_ROLE => $row['target_user_role_oid'],
                    PolicyTargetDataType::PAGE_GROUP => $row['target_page_group_oid'],
                    PolicyTargetDataType::META_OBJECT => $row['target_object_oid'],
                    PolicyTargetDataType::ACTION => $action,
                    PolicyTargetDataType::FACADE => $row['target_facade_class_path'],
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
            $err = 'alias "' . $selector->toString() . '"';
        } elseif ($selector->isUid()) {
            if ($uiPage = $this->pages_loaded[$selector->toString()]) {
                return $uiPage;
            }
            $where = "p.oid = " . $selector->toString();
            $err = 'UID "' . $selector->toString() . '"';
        } else {
            throw new UiPageNotFoundError('Unsupported page selector ' . $selector->toString() . '!');
        }
        
        $groupConcat = $this->buildSqlGroupConcat($this->buildSqlUuidSelector('pgp.page_group_oid'), 'exf_page_group_pages pgp', 'pgp.page_oid = p.oid');
        $query = $this->getDataConnection()->runSql("
            SELECT 
                p.*,
                {$this->buildSqlUuidSelector('p.oid')} as oid,
                {$this->buildSqlUuidSelector('p.app_oid')} as app_oid,
                {$this->buildSqlUuidSelector('p.parent_oid')} as parent_oid,
                {$this->buildSqlUuidSelector('p.default_menu_parent_oid')} as default_menu_parent_oid,
                {$this->buildSqlUuidSelector('p.page_template_oid')} as page_template_oid,
                {$this->buildSqlUuidSelector('p.created_by_user_oid')} as created_by_user_oid,
                {$this->buildSqlUuidSelector('p.modified_by_user_oid')} as modified_by_user_oid,
                pt.facade_filepath, 
                pt.facade_uxon,
                (
                    {$groupConcat}
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
        $uiPage->setMenuHome($row['menu_home'] ? true : false);
        $uiPage->setPublished($row['published'] ? true : false);
        
        if ($row['parent_oid']) {
            $uiPage->setParentPageSelector($row['parent_oid']);
        }
        
        $uiPage->setUpdateable($row['auto_update_with_app'] ? true : false);
        $uiPage->setReplacesPageSelector($row['replace_page_oid']);
        $uiPage->setContents($row['content'] ?? new UxonObject());
        
        $uiPage->setCreatedOn($row['created_on']);
        $uiPage->setCreatedByUserSelector($row['created_by_user_oid']);
        $uiPage->setModifiedOn($row['modified_on']);
        $uiPage->setModifiedByUserSelector($row['modified_by_user_oid']);
        
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
       
        $this->pages_loaded[$uiPage->getUid()] = $uiPage;
        
        $this->getWorkbench()->eventManager()->dispatch(new OnUiMenuItemLoadedEvent($uiPage));
        $this->getWorkbench()->eventManager()->dispatch(new OnUiPageLoadedEvent($uiPage));
        
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
                try {
                    $rootNode = $this->loadPageTreeCreateNodeFromDbRow($row);
                    $nodes[] = $rootNode;
                    $this->nodes_loaded[$rootNode->getUid] = $rootNode;
                } catch (AccessDeniedError $e) {
                    //$this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                }
            }
            
            $tree->setStartRootNodes($nodes);
        }        
        if ($tree->hasExpandPathToPage()) {            
            return $this->loadPageTreeParentNodes($tree);
        } else {
            $treeRootNodes = $tree->getStartRootNodes();
            $nodes = [];
            foreach ($treeRootNodes as $rootNode) {
                $nodes[] = $this->loadPageTreeNodeSubNodes($rootNode, $tree->getExpandDepth());
            }
            return $nodes;
        }
    }
    
    /**
     * 
     * @param array $row
     * @param UiPageTreeNodeInterface $parentNode
     * @return UiPageTreeNodeInterface
     */
    protected function loadPageTreeCreateNodeFromDbRow(array $row, UiPageTreeNodeInterface $parentNode = null) : UiPageTreeNodeInterface
    {
        $node = UiPageTreeFactory::createNode(
            $this->getWorkbench(),
            $row['alias'],
            $row['name'],
            $row['oid'],
            ($row['published'] ? true : false),
            $parentNode,
            $row['description'],
            $row['intro'],
            $row['group_oids'] ? explode(',', $row['group_oids']) : null,
            $row['app_oid']
        );
        
        $node->setCreatedOn($row['created_on']);
        $node->setCreatedByUserSelector($row['created_by_user_oid']);
        $node->setModifiedOn($row['modified_on']);
        $node->setModifiedByUserSelector($row['modified_by_user_oid']);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnUiMenuItemLoadedEvent($node));
        
        return $node;
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
        if ($loadedtree !== null && $loadedtree->isLoaded() === true && $loadedtree->getStartRootNodes() === $treeRootNodes) {
            return $loadedtree->getRootNodes();
        }
        $nodeId = $tree->getExpandPathToPage()->getUid();
        $oldNode = null;
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
                            try {
                                $parentNode = $this->loadPageTreeCreateNodeFromDbRow($row);
                                $this->nodes_loaded[$parentNode->getUid()] = $parentNode;
                            } catch (AccessDeniedError $e) {
                                //$this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                                $parentNode = null;
                            }
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
                            try {
                                $childNode = $this->loadPageTreeCreateNodeFromDbRow($row, $parentNode);
                            } catch (AccessDeniedError $e) {
                                //$this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                                continue;
                            }
                        }
                        $this->nodes_loaded[$childNode->getUid()] = $childNode;
                        $parentNode->addChildNode($childNode);                        
                        $parentNode->setChildNodesLoaded(true);
                    }
                }
                $this->nodes_loaded[$parentNode->getUid()] = $parentNode;
            }
            if ($parentNode !== null && $tree->nodeInRootNodes($parentNode)) {
                $nodeId = null;
                for ($i = 0; $i < count($treeRootNodes); $i++) {
                    if ($treeRootNodes[$i]->getUid() === $parentNode->getUid()) {
                        $treeRootNodes[$i] = $parentNode;
                        break;
                    }
                }
            } elseif ($parentNode === null && $oldNode !== null) {
                $treeRootNodes[] = $oldNode;
                $nodeId = null;
            } else {
                $nodeId = $parentNodeId;
                $oldNode = $parentNode;
            }
        }
        $this->menu_tress_loaded[$tree->getExpandPathToPage()->getUid()] = $tree;
        return $treeRootNodes;
    }
    
    /**
     * Loads all subnodes for the given node till depth is reached. If depth is null, loads every subnode for given node.
     * 
     * @param UiPageTreeNodeInterface $node
     * @param UiPageTreeNodeInterface[] $childNodes
     * @return UiPageTreeNodeInterface
     */
    protected function loadPageTreeNodeSubNodes(UiPageTreeNodeInterface $node, ?int $depth) : UiPageTreeNodeInterface
    {
        // no subnodes need to be loaded if depth is not null or greater than 0
        if (! ($depth === null || $depth > 0)) {
            return $node;
        }
        $childNodes = null;
        if ($this->nodes_loaded[$node->getUid()] !== null && $this->nodes_loaded[$node->getUid()]->getChildNodesLoaded() === true) {
            $childNodes = $this->nodes_loaded[$node->getUid()]->getChildnodes();
            $node->resetChildNodes();
            foreach ($childNodes as $childNode) {
                //load child nodes for child node if child node level is not wanted depth yet
                if ($depth === null || $depth - 1 > 0) {
                    $childNode = $this->loadPageTreeNodeSubNodes($childNode, $depth != null ? ($depth - 1) : NULL);
                }
                $childNode->setParentNode($node);
                $node->addChildNode($childNode);                    
            }
            $node->setChildNodesLoaded(true);
            $this->nodes_loaded[$node->getUid()] = $node;
            return $node;
        } else {
            // load data for 1 level of sub nodes if next sub level of the current node is wanted depth
            if (! ($depth === null || $depth - 1 >= 0)) {
                $rows = $this->loadPageTreeLevel($node->getUid());
            } else {
                // else load data for 2 sublevels right away
                $rows = $this->loadPageTreeLevel($node->getUid(), true);
            }
            $childIds = [];
            foreach ($rows as $row) {
                //build first level child nodes
                if ($row['parent_oid'] === $node->getUid()  && !in_array($row['oid'], $childIds)) {
                    if ($this->nodes_loaded[$row['oid']] !== null) {
                        $childNode = $this->nodes_loaded[$row['oid']];                            
                        $childIds[] = $childNode->getUid();
                        $node->addChildNode($childNode);
                    } else {
                        try {
                            $childNode = $this->loadPageTreeCreateNodeFromDbRow($row, $node);                                
                            $childIds[] = $childNode->getUid();
                            $node->addChildNode($childNode);
                            $this->nodes_loaded[$childNode->getUid()] = $childNode;
                        } catch (AccessDeniedError $e) {
                            //$this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                        }
                    }
                }
            }                
            $node->setChildNodesLoaded(true);
            $childNodes = $node->getChildNodes();
        }
        
        // return node if wanted depth is level of child nodes
        if (! ($depth === null || $depth - 1 > 0)) {
            return $node;
        }
            
        $node->resetChildNodes();
        //build second level of child nodes
        foreach ($childNodes as $childNode) {
            foreach ($rows as $row) {
                if ($childNode->getUid() === $row['parent_oid']) {
                    if ($this->nodes_loaded[$row['oid']] !== null) {
                        $childChildNode = $this->nodes_loaded[$row['oid']];
                    } else {
                        try {
                            $childChildNode = $this->loadPageTreeCreateNodeFromDbRow($row, $childNode);
                        } catch (AccessDeniedError $e) {
                            //$this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                            continue;
                        }
                    }
                    //load sub levels for child child nodes if needed
                    if ($depth === null || $depth - 2 > 0) {
                        $childChildNode = $this->loadPageTreeNodeSubNodes($childChildNode, $depth != null ? ($depth - 2) : NULL);
                    }
                    $this->nodes_loaded[$childChildNode->getUid()] = $childChildNode;
                    $childNode->addChildNode($childChildNode);
                }
            }
            $childNode->setChildNodesLoaded(true);
            $this->nodes_loaded[$childNode->getUid()] = $childNode;                    
            $node->addChildNode($childNode);
        }                
        $node->setChildNodesLoaded(true);
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
            ORDER BY parent_oid ASC, menu_index ASC, name ASC";
        $groupConcat = $this->buildSqlGroupConcat($this->buildSqlUuidSelector('pgp.page_group_oid'), 'exf_page_group_pages pgp', 'pgp.page_oid = p.oid');
        $sql = "
            SELECT
                {$this->buildSqlUuidSelector('p.oid')} as oid,
                {$this->buildSqlUuidSelector('p.parent_oid')} as parent_oid,
                {$this->buildSqlUuidSelector('p.app_oid')} as app_oid,
                p.name,
                p.alias,
                p.description,
                p.intro,
                p.published,
                p.menu_index,
                p.created_on,
                {$this->buildSqlUuidSelector('p.created_by_user_oid')} as created_by_user_oid,
                p.modified_on,
                {$this->buildSqlUuidSelector('p.modified_by_user_oid')} as modified_by_user_oid,
                (
                    {$groupConcat}
                ) as group_oids
            FROM exf_page p";
                
        if ($loadTwoLevels === true) {
            $sqlUnionInnerWhere = '';
            if ($id === null) {
                $sqlUnionInnerWhere = $sqlWhere;
            } else {
                $sqlUnionInnerWhere = "
                WHERE parent_oid = {$id} AND menu_visible = 1";
            }
            $sqlUnionWhere = "
               WHERE p.parent_oid IN (SELECT
	           oid
	           FROM exf_page {$sqlUnionInnerWhere}) AND menu_visible = 1";
            $sql .= "            
            {$sqlWhere}
            UNION ALL
                {$sql}
                {$sqlUnionWhere}";
                
        } else {
            $sql .= $sqlWhere;
        }
        
        $sql = "/*load UiPageTree Data*/" . $sql . $sqlOrder;
        $query = $this->getDataConnection()->runSql($sql);
        $rows = $query->getResultArray();
        return $rows;        
    }
    
    protected function buildSqlEscapedString(string $string) : string
    {
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
        } else {
            return preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
        }
    }
}