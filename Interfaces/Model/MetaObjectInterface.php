<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\CommonLogic\Model\AttributeList;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\EntityListInterface;
use exface\Core\CommonLogic\Model\AttributeGroup;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\AliasInterface;

interface MetaObjectInterface extends ExfaceClassInterface, AliasInterface
{
    function __construct(ModelInterface $model);
    
    /**
     * Returns all relations as an array with the relation alias as key.
     * If an ALIAS stands for multiple
     * relations (of different types), the respective element of the relations array will be an array in
     * turn.
     *
     * @return Relation[] [relation_alias => relation | relation[]]
     */
    public function getRelationsArray();
    
    /**
     * Returns all direct relations of this object as a flat array.
     * Optionally filtered by relation type.
     *
     * @return Relation[]
     */
    public function getRelations($relation_type = null);
    
    /**
     * Returns a relation specified by it's alias.
     * The alias can also be a relation path consisting of multiple
     * relations. In this case, the last relation will be returned
     *
     * If there are multiple (reverse) relations sharing the alias,
     * a $foreign_key_alias can be specified to select exactly one relation. If there is no
     * $foreign_key_alias specified, and multiple relations are found, the following fallbacks are used:
     * 1) if there is a regular relation with such alias, it will always be selected
     * 2) if all relations are reversed, the first one will be returned
     *
     * IDEA Perhaps we could also mark one relation as default for reverse relations in the model. On the other hand,
     * this would make it more difficult to create relations just to handle a fairly rare exception. I'm not
     * sure, if this is a good idea since one of the main principles of ExFace is a minimum of requirements.
     * Alternatively we could rely on some naming conventions like the main relation should have an alias
     * matchnig the related object alias or so.
     *
     * There are multiple cases, when there could be more than one relation with the same alias.
     * For example, a reverse relation could overwrite some other relation. This can be the case
     * if an Object references another one, while the latter again references the first, but
     * with a different meaning. The metamodel of ExFace has such a circular reference:
     * OBJECT->DATA_SOURCE and DATA_SOURCE->BASE_OBJECT. In these cases the alias of the
     * relation-attribute must not be the same as the alias of the related object. In the
     * above example either the relation DATA_SOURCE or the object DATA_SOURCE must be
     * renamed to something else (the object's alias is currently DATASRC).
     *
     * @param string $alias
     * @param string $foreign_key_alias
     * @throws MetaRelationNotFoundError if no matching relation found
     * @return Relation
     */
    public function getRelation($alias, $foreign_key_alias = '');
    
    /**
     * Returns TRUE if the object has a relation matching the given alias and FALSE otherwise.
     * The alias may include a relation path
     *
     * @see getRelation()
     *
     * @param string $alias
     * @param string $foreign_key_alias
     * @return boolean
     */
    public function hasRelation($alias, $foreign_key_alias = '');
    
    /**
     * Returns a list of all direct attributes of this object (including inherited ones!)
     *
     * @return AttributeList
     */
    public function getAttributes();
    
    /**
     * Returns an attribute matching the given attribute alias.
     *
     * Supports aliases with relations (e.g. CUSTOMER__CUSTOMER_GROUP__LABEL).
     * If an attribute of a related object is requested, it will have a non-empty
     * relation path holding all relations needed to reach the related object
     * (e.g. CUSTOMER__CUSTOMER_GROUP for CUSTOMER__CUSTOMER_GROUP__NAME):
     *
     * @see Attribute::getRelationPath()
     *
     * TODO if a related attribute is request, a copy is created with the first
     * call of the method and that copy is cached. This means, any changes on the
     * original attribute will not affect the copy anymore. This is dangerous!
     * To change this, we should replace all $attribute->getRelationPath() with
     * $object->getRelationPath($attribute) and just use references to attributes
     * in the cache.
     *
     * @param string $alias
     *
     * @throws MetaAttributeNotFoundError if no matching attribute could be found
     *
     * @return Attribute
     */
    public function getAttribute($alias);
    
    /**
     * Returns TRUE if the object has an attribute matching the given alias and FALSE otherwise.
     * The alias may contain a relation path.
     *
     * @param string $alias
     * @return boolean
     */
    public function hasAttribute($alias);
    
    /**
     * Returns the object related to the current one via the given relation path string
     *
     * @param string $relation_path_string
     * @return MetaObjectInterface
     */
    public function getRelatedObject($relation_path_string);
    
    /**
     * Adds a relation to the object.
     *
     * NOTE: Adding multiple relations with the same alias will work differently depending on the relation type:
     * - Regular relations will get overwritten with every new relation with the same alias (important for extending objects!)
     * - Reverse relations will be accumulated in an array
     *
     * TODO When adding reverse relations, it is possible, that there are two relations from the same object,
     * thus having the same aliases (the alias of the reverse relation is currently the alias of the object,
     * where it comes from). I like this kind of naming, but it needs to be extended by the possibility to
     * specify which of the two reverse relation to use (e.g. LOCATION->ADDRESS[SHIPPING_ADDRESS] or something)
     *
     * @param Relation $relation
     * @return MetaObjectInterface
     */
    public function addRelation(Relation $relation);
    
    /**
     * Inherits all attributes, relations and actions from the given parent object.
     * Parts of the parent
     * can be overridden in the extended object by creating an attribute, relation, etc. with the same alias,
     * as the parent has.
     *
     * Inherited elements become property of the extende object and loose any connection to their parents
     * (i.e. changing an attribute on the parent object at window will not effect the respective inherited
     * attribute of the extended object). However, using the method getInheritedFromObjectId() of an
     * inherited element, it can be determined, whether the element is inherited and from which object.
     *
     * @param string $parent_object_id
     */
    public function extendFromObjectId($parent_object_id);
    
    /**
     * Finds a relation to a specific object.
     * If there are multiple reverse relations, the first one will be returned.
     * Setting $prefer_direct_relations to TRUE will check direct (not inherited) relation first and return the first
     * one of them - and only if there are no direct relation, the first inherited relation.
     * If there are regular and reverse relations to the desired object, the first regular relation (n-to-1) will be
     * returned.
     *
     * Returns FALSE if no relation to the given object is found.
     *
     * Note: Currently this will only work for direct relations. Chained relations can be found via find_relation_path().
     *
     * @see find_relation_path()
     *
     * @param MetaObjectInterface $related_object
     * @return Relation
     */
    public function findRelation(MetaObjectInterface $related_object, $prefer_direct_relations = false);
    
    /**
     * Finds all relations to the specified object including those targeting objects it inherits from.
     * If the relation
     * type is not set, all relations will be returned in one array (forward and reverse ones).
     *
     * Example for inheritance handling: concider the object PHP_FILE, which extends FILE and the object FILE_CONTENTS,
     * that has a forward relation to the FILE. FILE_CONTENTS->findRelations(PHP_FILE) will find the relation to the
     * FILE-object because PHP_FILE extends FILE.
     *
     * @param string $related_object_id
     * @param string $relation_type
     *            one of the Relation::RELATION_TYPE_xxx constants
     * @return Relation[]
     */
    public function findRelations($related_object_id, $relation_type = null);
    
    /**
     * Returns the relation path to a given object or FALSE that object is not related to the current one.
     * In contrast to
     * find_relation() this method returns merely the relation path, not the relation itself.
     * FIXME This does not work very well. It would be better to create a single finder method, that would return a relation and
     * to make the relation know its path like the attributes do.
     *
     * @see find_relation()
     *
     * @param MetaObjectInterface $related_object
     * @param number $max_depth
     * @param RelationPath $start_path
     * @return RelationPath | boolean
     */
    public function findRelationPath(MetaObjectInterface $related_object, $max_depth = 3, RelationPath $start_path = null);
    
    /**
     * Returns an array with all attributes of this object having the specified data address (e.g.
     * SQL column name)
     *
     * @param string $data_address
     * @return Attribute[]
     */
    public function findAttributesByDataAddress($data_address);
    
    public function getUidAlias();
    
    public function setUidAlias($value);
    
    /**
     * Returns the meta attribute with the unique ID of the object.
     *
     * @throws MetaObjectHasNoUidAttributeError if no UID attribute defined for this object
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getUidAttribute();
    
    /**
     * Returns TRUE if the object has a UID attribute and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasUidAttribute();
    
    public function getLabelAlias();
    
    public function setLabelAlias($value);
    
    /**
     * Returns the meta attribute with the label of the object
     *
     * @return Attribute
     */
    public function getLabelAttribute();
    
    public function getDataSourceId();
    
    public function setDataSourceId($value);
    
    /**
     * Returns the data source for this object.
     * The data source is fully initialized and the connection is already established.
     *
     * @return \exface\Core\CommonLogic\DataSource
     */
    public function getDataSource();
    
    /**
     * Returns the data connection for this object.
     * The connection is already initialized and established.
     *
     * @return DataConnectionInterface
     */
    public function getDataConnection();
    
    /**
     * Sets a custom data connection to be used for this object.
     * This way, the default connection for the data source can be overridden!
     *
     * @param string $alias
     * @return MetaObjectInterface
     */
    public function setDataConnectionAlias($alias);
    
    /**
     * @return AbstractQueryBuilder
     */
    public function getQueryBuilder();
    
    public function getId();
    
    public function setId($value);
    
    public function setAlias($value);
    
    public function getName();
    
    public function setName($value);
    
    public function getDataAddress();
    
    public function setDataAddress($value);
    
    /**
     * Returns a UXON object with data source specific properties of the object
     *
     * @return UxonObject
     */
    public function getDataAddressProperties();
    
    /**
     * Returns the value of a data source specific object property specified by it's id
     *
     * @param string $id
     */
    public function getDataAddressProperty($id);
    
    /**
     * Sets the value of a data address property specified by it's id
     *
     * @param string $id
     * @param string $value
     * @return MetaObjectInterface
     */
    public function setDataAddressProperty($id, $value);
    
    /**
     *
     * @param UxonObject $uxon
     * @return MetaObjectInterface
     */
    public function setDataAddressProperties(UxonObject $uxon);
    
    /**
     *
     * @return array
     */
    public function getParentObjectsIds();
    
    /**
     * Returns all objects, this one inherits from as an array
     *
     * @return MetaObjectInterface[]
     */
    public function getParentObjects();
    
    public function setParentObjectsIds($value);
    
    public function addParentObjectId($object_id);
    
    /**
     * 
     * @param string $object_alias
     * @return MetaObjectInterface
     */
    public function getParentObject($object_alias);
    
    /**
     * Returns all objects, that inherit from the current one as an array.
     * This includes distant relatives, that inherit
     * from other objects, inheriting from the current one.
     *
     * @return MetaObjectInterface[]
     */
    public function getInheritingObjects();
    
    /**
     *
     * @return EntityListInterface
     */
    public function getDefaultSorters();
    
    public function getModel();
    
    public function getAppId();
    
    public function setAppId($value);
    
    public function getShortDescription();
    
    public function setShortDescription($value);
    
    public function setAliasWithNamespace($value);
    
    public function setNamespace($value);
    
    /**
     * Returns the UXON description of the default editor widget for instances of this object.
     * This can be specified in the meta model
     *
     * @return UxonObject
     */
    public function getDefaultEditorUxon();
    
    /**
     * Sets the UXON description for the default editor widget for this object (e.g.
     * to build the EditObjectDialog)
     *
     * @param UxonObject $value
     * @return MetaObjectInterface
     */
    public function setDefaultEditorUxon(UxonObject $value);
    
    /**
     * Returns an array of placeholders, which the data address of this object contains.
     *
     * A typical example would be an SQL view as an object data address:
     * SELECT [#alias#]tbl1.*, [#alias#]tbl2.* FROM table1 [#alias#]tbl1 LEFT JOIN table2 [#alias#]tbl2
     * The placeholder [#alias#] here prefixes all table aliases with the alias of the meta object, thus making
     * naming collisions with other views put together by the query builder virtually impossible.
     *
     * @return array ["alias"] for the above example
     */
    public function getDataAddressRequiredPlaceholders();
    
    /**
     * Returns the attribute group specified by the given alias or NULL if no such group exists.
     * Apart from explicitly defined attribute groups, built-in groups can be used. Built-in groups have aliases starting with "~".
     * For every built-in alias there is a constant in the AttributeGroup class (e.g. AttributeGroup::ALL, etc.)
     *
     * @param string $alias
     * @return AttributeGroup
     */
    public function getAttributeGroup($alias);
    
    /**
     * Returns TRUE if this object is exactly the one given or inherits from it and FALSE otherwise - similarly to the behavior of PHP instance_of.
     * E.g. if you have an object SPECIAL_FILE, which extends FILE, SPECIAL_FILE->is(FILE) = true, but FILE->is(SPECIAL_FILE) = false.
     *
     * @param MetaObjectInterface|string $object_or_alias_or_id
     * @return boolean
     *
     * @see is_exactly()
     * @see is_extended_from()
     */
    public function is($object_or_alias_or_id);
    
    /**
     * Checks if this object matches the given object identifier: if so, returns TRUE and FALSE otherwise.
     * The identifier may be a qualified alias, a UID or an instantiated object.
     *
     * @param MetaObjectInterface|string $alias_with_relation_path
     * @return boolean
     *
     * @see is()
     * @see is_extended_from()
     */
    public function isExactly($object_or_alias_or_id);
    
    /**
     * Returns TRUE if this object is extended from the given object identifier.
     * The identifier may be a qualified alias, a UID or an instantiated object.
     *
     * @param MetaObjectInterface|string $object_or_alias_or_id
     * @return boolean
     *
     * @see is_exactly()
     * @see is()
     */
    public function isExtendedFrom($object_or_alias_or_id);
    
    public function getBehaviors();
    
    /**
     *
     * @return MetaObjectActionListInterface|ActionInterface[]
     */
    public function getActions();
    
    /**
     * Returns the currently running instance of the app, this object belongs to.
     *
     * @return AppInterface
     */
    public function getApp();
}