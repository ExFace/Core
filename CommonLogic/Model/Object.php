<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Factories\AttributeGroupFactory;
use exface\Core\Factories\AttributeListFactory;
use exface\Core\CommonLogic\DataSheets\DataAggregator;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Factories\EntityListFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;
use exface\Core\Exceptions\InvalidArgumentException;

class Object implements ExfaceClassInterface, AliasInterface
{

    private $id;

    private $name;

    private $alias;

    private $data_address;

    private $data_address_properties;

    private $label;

    private $uid_alias;

    private $attributes = array();
    
    private $attributes_alias_cache = array();

    private $relations = array();

    private $data_source_id;

    private $data_connection_alias = NULL;

    private $parent_objects_ids = array();

    private $default_sorters = array();

    private $model;

    private $app_id;

    private $namespace;

    private $short_description = '';

    private $default_editor_uxon = null;

    private $attribute_groups = array();

    private $behaviors = array();

    private $actions = array();

    function __construct(\exface\Core\CommonLogic\Model\Model $model)
    {
        $exface = $model->getWorkbench();
        $this->model = $model;
        $this->attributes = AttributeListFactory::createForObject($this);
        $this->default_sorters = EntityListFactory::createEmpty($exface, $this);
        $this->behaviors = new ObjectBehaviorList($exface, $this);
    }

    /**
     * Returns all relations as an array with the relation alias as key.
     * If an ALIAS stands for multiple
     * relations (of different types), the respective element of the relations array will be an array in
     * turn.
     *
     * @return relation[] [relation_alias => relation | relation[]]
     */
    function getRelationsArray()
    {
        return $this->relations;
    }

    /**
     * Returns all direct relations of this object as a flat array.
     * Optionally filtered by relation type.
     *
     * @return Relation[]
     */
    function getRelations($relation_type = null)
    {
        $result = array();
        foreach ($this->getRelationsArray() as $rel) {
            if (is_null($relation_type) || (is_array($rel) && $relation_type == Relation::RELATION_TYPE_REVERSE) || $relation_type == $rel->getType()) {
                if (is_array($rel)) {
                    $result = array_merge($result, $rel);
                } else {
                    $result[] = $rel;
                }
            }
        }
        return $result;
    }

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
    public function getRelation($alias, $foreign_key_alias = '')
    {
        if ($rel = RelationPath::relationPathParse($alias, 1)) {
            $relation = $this->getRelatedObject($rel[0])->getRelation($rel[1]);
            return $relation;
        }
        
        $rel = $this->relations[$alias];
        // If the object does not have a relation with a matching alias
        if (! $rel) {
            // Check, if a foreign key is specified in the alias (e.g. ADDRESS->COMPANY[SHIPPING_ADDRESS])
            // If so, extract it and call get_relation() again using the separated alias and foreign_key_alias
            if ($start = strpos($alias, '[')) {
                if (! $end = strpos($alias, ']'))
                    throw new MetaRelationNotFoundError($this, 'Missing "]" in relation alias "' . $alias . '"', '6T91HJK');
                $foreign_key_alias = substr($alias, $start + 1, $end - $start - 1);
                $alias = substr($alias, 0, $start);
                return $this->getRelation($alias, $foreign_key_alias);
            } else {
                throw new MetaRelationNotFoundError($this, 'Relation "' . $alias . ($foreign_key_alias ? '[' . $foreign_key_alias . ']' : '') . '" not found for object "' . $this->getAliasWithNamespace() . '"!');
            }
        }
        
        if (! is_array($rel)) {
            return $rel;
        } else {
            $first_rel = false;
            /* @var $r \exface\Core\CommonLogic\Model\relation */
            foreach ($rel as $r) {
                // If there is no specific foreign key alias, try to select the most feasable relation,
                // otherwise just look for a relation with with a matching foreign key alias
                if (! $foreign_key_alias) {
                    // Always return the regular relation if there are regular and reverse ones in the array.
                    // In this case, the reverse relation can only be addressed using the $foreign_key_alias
                    // There can only be at most one regular relation with the same alias for one object, but there
                    // can be multiple reverse relations with the same alias.
                    if ($r->isForwardRelation()) {
                        return $r;
                    } elseif ($first_rel === false) {
                        $first_rel = $r;
                    }
                } elseif ($r->getForeignKeyAlias() == $foreign_key_alias) {
                    return $r;
                }
            }
            return $first_rel;
        }
        throw new MetaRelationNotFoundError($this, 'Relation "' . $alias . ($foreign_key_alias ? '[' . $foreign_key_alias . ']' : '') . '" not found for object "' . $this->getAliasWithNamespace() . '"!');
    }

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
    public function hasRelation($alias, $foreign_key_alias = '')
    {
        try {
            $this->getRelation($alias, $foreign_key_alias);
        } catch (MetaRelationNotFoundError $e) {
            return false;
        }
        return true;
    }

    /**
     * Returns a list of all direct attributes of this object (including inherited ones!)
     *
     * @return AttributeList
     */
    function getAttributes()
    {
        return $this->attributes;
    }

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
    public function getAttribute($alias)
    {
        // First of all check, the cache: 
        // - if it's a HIT (key exists) and the key points to an attribute, return it
        // - if it's a MISS, search for the attribute
        // - if it's a HIT, but the value is FALSE skip the searching an head to the exception trowing
        $attr = $this->getAttributeCache($alias);
        if ($attr instanceof Attribute){
            return $attr;
        } elseif ($attr !== false){
            // Now check, if it is a direct attribute. This is the simplest case and the fastets one too
            if ($attr = $this->getAttributes()->get($alias)) {
                $this->setAttributeCache($alias, $attr);
                return $attr;
            }
            
            // Check if the $alias starts with = and thus is a formula and not an alias!
            if (substr($alias, 0, 1) == '=') {
                $this->setAttributeCache($alias, false);
                throw new MetaAttributeNotFoundError($this, 'Attribute aliases cannot start with "=" ("' . $alias . '")! The "=" is reserved for formulas!');
            }
            
            // check for aggregate functions and remove them
            if ($aggr = DataAggregator::getAggregateFunctionFromAlias($alias)) {
                $alias = substr($alias, 0, strlen(DataAggregator::AGGREGATION_SEPARATOR . $aggr) * (- 1));
                // check if it is a direct attribute again (now, as the aggregator was removed)
                if ($attr = $this->getAttributes()->get($alias)) {
                    $this->setAttributeCache($alias, $attr);
                    return $attr;
                }
            }
            
            // If the attribute has a relation path, delegate to the next related object and so on for every relation in the
            // path. The last object in the relation path must deal with the actual attribute then.
            if ($rel_parts = RelationPath::relationPathParse($alias, 1)) {
                try {
                    $rel_attr = $this->getRelatedObject($rel_parts[0])->getAttribute($rel_parts[1]);
                    $attr = $rel_attr->copy();
                    $rel = $this->getRelation($rel_parts[0]);
                    $attr->getRelationPath()->prependRelation($rel);
                    $this->setAttributeCache($alias, $attr);
                    return $attr;
                } catch (MetaRelationNotFoundError $e) {
                    // Catch relation error and wrap it into an attribute error. 
                    // Otherwise it's not clear to the user, at what point the 
                    // relation was required.
                    $this->setAttributeCache($alias, false);
                    throw new MetaAttributeNotFoundError($this, 'Attribute "' . $alias . '" not found for object "' . $this->getAliasWithNamespace() . '"!', null, $e);
                } catch (MetaAttributeNotFoundError $e) {
                    // Catch attribute-not-found errors from other objects and
                    // wrap them into an error for this object. Otherwise it's
                    // not clear to the user, at what point the attribute was
                    // required.
                    $this->setAttributeCache($alias, false);
                    throw new MetaAttributeNotFoundError($this, 'Attribute "' . $alias . '" not found for object "' . $this->getAliasWithNamespace() . '"!', null, $e);
                }
            }
            
            // At this point only two possibilities are left: it's either a reverse relation or an error
            if ($this->hasRelation($alias)) {
                $rev_rel = $this->getRelation($alias);
                if ($rev_rel->isReverseRelation()) {
                    try {
                        $rel_attr = $rev_rel->getRelatedObject()->getAttribute($rev_rel->getForeignKeyAlias());
                        $attr = $rel_attr->copy();
                        $attr->getRelationPath()->prependRelation($rev_rel);
                        $this->setAttributeCache($alias, $attr);
                        return $attr;
                    } catch (MetaAttributeNotFoundError $e) {
                        $this->setAttributeCache($alias, false);
                        throw new MetaAttributeNotFoundError($this, 'Attribute "' . $alias . '" not found for object "' . $this->getAliasWithNamespace() . '"!', null, $e);
                    }
                }
            }
            $this->setAttributeCache($alias, false);
        }
        throw new MetaAttributeNotFoundError($this, 'Attribute "' . $alias . '" not found for object "' . $this->getAliasWithNamespace() . '"!');
    }
    
    /**
     * Sets the internal cache for the given attribute alias.
     * 
     * The passed value can either be an attribute, boolean FALSE (meaning there
     * is no attribute matching the alias) or NULL to empty the the cache for
     * this alias.
     * 
     * @param string $alias
     * @param Attribute|boolean|null $value
     * @throws InvalidArgumentException
     * @return \exface\Core\CommonLogic\Model\Object
     */
    protected function setAttributeCache($alias, $value = null)
    {
        if ($value === false || is_null($value) || $value instanceof Attribute){
            $this->attributes_alias_cache[$alias] = $value;
        } else {
            throw new InvalidArgumentException('Invalid value passed to attribute cache: expecting Attribute or FALSE, "' . gettype($value) . '" received!');
        }
        return $this;
    }
    
    /**
     * Returns the cached value for the given alias: an attribute, FALSE if there
     * is no attribute matching the alias or NULL if it's a cache miss.
     * 
     * @param string $alias
     * @return Attribute|boolean|null
     */
    protected function getAttributeCache($alias)
    {
        return $this->attributes_alias_cache[$alias];
    }

    /**
     * Returns TRUE if the object has an attribute matching the given alias and FALSE otherwise.
     * The alias may contain a relation path.
     *
     * @param string $alias            
     * @return boolean
     */
    public function hasAttribute($alias)
    {
        $cache = $this->getAttributeCache($alias);
        if ($cache === false){
            return false;
        } elseif ($cache instanceof Attribute){
            return $cache;
        }
        
        try {
            $this->getAttribute($alias);
        } catch (MetaAttributeNotFoundError $e) {
            return false;
        }
        return true;
    }

    /**
     * Returns the object related to the current one via the given relation path string
     *
     * @param string $relation_path_string            
     * @return Object
     */
    function getRelatedObject($relation_path_string)
    {
        $relation_path = RelationPathFactory::createFromString($this, $relation_path_string);
        return $relation_path->getEndObject();
    }

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
     * @param relation $relation            
     * @return Object
     */
    function addRelation(Relation $relation)
    {
        // If there already is a relation with this alias, add another one, making it an array of relations
        // Make sure, this only happens to reverse relation!!! Direct relation MUST have different aliases!
        if ($relation->isReverseRelation() && $this->hasRelation($relation->getAlias())) {
            $duplicate = $this->getRelation($relation->getAlias());
            // Create an array for the alias or just add the relation to the array if there already is one
            if (is_array($duplicate)) {
                $this->relations[$relation->getAlias()][] = $relation;
            } else {
                $this->relations[$relation->getAlias()] = array(
                    $duplicate,
                    $relation
                );
            }
        } else {
            $this->relations[$relation->getAlias()] = $relation;
        }
        return $this;
    }

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
    public function extendFromObjectId($parent_object_id)
    {
        // Do nothing, if trying to extend itself
        if ($parent_object_id == $this->getId())
            return;
        
        // Otherwise clone all attributes and relations of the parent and add them to this object
        $parent = $this->getModel()->getObject($parent_object_id);
        $this->addParentObjectId($parent_object_id);
        
        // Inherit data address
        $this->setDataAddress($parent->getDataAddress());
        $this->setDataAddressProperties($parent->getDataAddressProperties());
        
        // Inherit default editor
        $default_editor_uxon = $parent->getDefaultEditorUxon()->copy();
        // If the default editor is explicitly based on the object, we are inheriting from, replace that object with this one.
        if ($default_editor_uxon->getProperty('object_alias') && $parent->is($default_editor_uxon->getProperty('object_alias'))) {
            $default_editor_uxon->setProperty('object_alias', $this->getAliasWithNamespace());
        }
        $this->setDefaultEditorUxon($default_editor_uxon);
        
        // Inherit some object properties originating from attributes
        $this->setUidAlias($parent->getUidAlias());
        $this->setLabelAlias($parent->getLabelAlias());
        
        // Inherit description
        $this->setShortDescription($parent->getShortDescription());
        
        // Inherit attributes
        foreach ($parent->getAttributes() as $attr) {
            $attr_clone = $attr->copy();
            // Save the parent's id, if there isn't one already (that would mean, that the parent inherited the attribute too)
            if (is_null($attr->getInheritedFromObjectId())) {
                $attr_clone->setInheritedFromObjectId($parent_object_id);
                // TODO Is it a good idea to set the object id of the inheridted attribute to the inheriting object? Would it be
                // better, if we only do this for objects, that do not have their own data address and merely are containers for attributes?
                // Currently the attribute is attached to the inheriting object, but the reference to the original object is saved in the
                // inherited_from_object_id property. This is important because otherwise there is no easy way to find out, which object
                // the attribute belongs to. Say, we want to get the object filtered over if the filter attribute_alias is RELATION__RELATION__ATTRIBUTE
                // and ATTRIBUTE is inherited. In this case ATTRIBUTE->getObject() should return the inheriting object and not the base object.
                //
                // One place, this is used at is \exface\Core\Widgets\Data::doPrefill(). When trying to prefill from the filters of the prefill sheet,
                // we need to find a filter widget over the object the prefill filters attribute belong to. Now, if that attribute is a UID or a
                // create/update-timestamp, it will often be inherited from some base object of the data source - perhaps the same base object, the
                // widget's object inherits from as well. In this case, there is no way to know, whose UID it is, unless the object_id of the inherited
                // attribute points to the object it directly belongs to (working example in Administration > Core > App > Button "Show Objects").
                $attr_clone->setObjectId($this->getId());
            }
            $this->getAttributes()->add($attr_clone);
        }
        
        // Inherit Relations
        foreach ($parent->getRelationsArray() as $rel) {
            $rel_clone = clone $rel;
            // Save the parent's id, if there isn't one already (that would mean, that the parent inherited the attribute too)
            if (is_null($rel->getInheritedFromObjectId())) {
                $rel_clone->setInheritedFromObjectId($parent_object_id);
            }
            $this->addRelation($rel_clone);
        }
        
        // Inherit behaviors
        foreach ($parent->getBehaviors()->getAll() as $key => $behavior) {
            $copy = $behavior->copy()->setObject($this);
            $this->getBehaviors()->add($copy, $key);
        }
        
        // TODO Inherit actions here as soon as actions can be defined in the model
    }

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
     * @param Object $related_object            
     * @return Relation
     */
    public function findRelation(Object $related_object, $prefer_direct_relations = false)
    {
        $first_relation = false;
        foreach ($this->getRelations() as $rel) {
            // It is important to compare the ids only, because otherwise the related object will need to be loaded.
            // Don't call getRelatedObject() here to loading half the model just to look through relations.
            if ($related_object->is($rel->getRelatedObjectId())) {
                if (! $rel->isInherited() || ! $prefer_direct_relations) {
                    return $rel;
                } else {
                    $first_relation = $rel;
                }
            }
        }
        return $first_relation;
    }

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
    public function findRelations($related_object_id, $relation_type = null)
    {
        $rels = array();
        foreach ($this->getRelations() as $rel) {
            try {
                if ($rel->getRelatedObject()->is($related_object_id) && ($relation_type == null || $relation_type == $rel->getType()))
                    $rels[] = $rel;
            } catch (MetaObjectNotFoundError $e) {
                // FIXME for some reason calling find_relations on alexa.RMS.STYLE produces a relation with the object 0x11E678F8FD442F59ACD40205857FEB80,
                // which cannot be found - need to fix quickly! For now, just ignore these cases
            }
        }
        return $rels;
    }

    /**
     * Returns the relation path to a given object or FALSE that object is not related to the current one.
     * In contrast to
     * find_relation() this method returns merely the relation path, not the relation itself.
     * FIXME This does not work very well. It would be better to create a single finder method, that would return a relation and
     * to make the relation know its path like the attributes do.
     *
     * @see find_relation()
     *
     * @param object $related_object            
     * @param number $max_depth            
     * @param RelationPath $start_path            
     * @return RelationPath | boolean
     */
    public function findRelationPath(Object $related_object, $max_depth = 3, RelationPath $start_path = null)
    {
        $path = $start_path ? $start_path : new RelationPath($this);
        
        if ($rel = $path->getEndObject()->findRelation($related_object)) {
            $path->appendRelation($rel);
        } elseif ($max_depth > 1) {
            $result = false;
            foreach ($this->getRelations() as $rel) {
                $possible_path = $path->copy();
                if ($result = $this->findRelationPath($related_object, $max_depth - 1, $possible_path->addRelation($rel))) {
                    return $result;
                }
            }
        } else {
            return false;
        }
        
        return $path;
    }

    /**
     * Returns an array with all attributes of this object having the specified data address (e.g.
     * SQL column name)
     *
     * @param string $data_address            
     * @return attribute[]
     */
    public function findAttributesByDataAddress($data_address)
    {
        $result = array();
        foreach ($this->getAttributes() as $attr) {
            if ($attr->getDataAddress() == $data_address) {
                $result[] = $attr;
            }
        }
        return $result;
    }

    public function getUidAlias()
    {
        return $this->uid_alias;
    }

    public function setUidAlias($value)
    {
        $this->uid_alias = $value;
    }

    /**
     * Returns the meta attribute with the unique ID of the object.
     *
     * @throws MetaObjectHasNoUidAttributeError if no UID attribute defined for this object
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getUidAttribute()
    {
        if (! $this->getUidAlias()) {
            throw new MetaObjectHasNoUidAttributeError($this, 'No UID attribute defined for object "' . $this->getAliasWithNamespace() . '"!');
        }
        return $this->getAttribute($this->getUidAlias());
    }

    /**
     * Returns TRUE if the object has a UID attribute and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasUidAttribute()
    {
        try {
            $this->getUidAttribute();
        } catch (MetaObjectHasNoUidAttributeError $e) {
            return false;
        }
        return true;
    }

    public function getLabelAlias()
    {
        return $this->label;
    }

    public function setLabelAlias($value)
    {
        $this->label = $value;
    }

    /**
     * Returns the meta attribute with the label of the object
     *
     * @return Attribute
     */
    public function getLabelAttribute()
    {
        if (! $this->getLabelAlias()) {
            return null;
        }
        return $this->getAttribute($this->getLabelAlias());
    }

    public function getDataSourceId()
    {
        return $this->data_source_id;
    }

    public function setDataSourceId($value)
    {
        $this->data_source_id = $value;
    }

    /**
     * Returns the data source for this object.
     * The data source is fully initialized and the connection is already established.
     *
     * @return \exface\Core\CommonLogic\DataSource
     */
    public function getDataSource()
    {
        return $this->getModel()->getWorkbench()->data()->getDataSource($this->getDataSourceId(), $this->data_connection_alias);
    }

    /**
     * Returns the data connection for this object.
     * The connection is already initialized and established.
     *
     * @return \exface\Core\CommonLogic\AbstractDataConnector
     */
    function getDataConnection()
    {
        return $this->getModel()->getWorkbench()->data()->getDataConnection($this->data_source_id, $this->data_connection_alias);
    }

    /**
     * Sets a custom data connection to be used for this object.
     * This way, the default connection for the data source can be overridden!
     *
     * @param string $alias            
     * @return \exface\Core\CommonLogic\Model\Object
     */
    function setDataConnectionAlias($alias)
    {
        $this->data_connection_alias = $alias;
        return $this;
    }

    function getQueryBuilder()
    {
        return $this->getModel()->getWorkbench()->data()->getQueryBuilder($this->data_source_id);
    }

    /**
     *
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    function createDataSheet()
    {
        $ds = $this->getModel()->getWorkbench()->data()->createDataSheet($this);
        return $ds;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($value)
    {
        $this->alias = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getDataAddress()
    {
        return $this->data_address;
    }

    public function setDataAddress($value)
    {
        $this->data_address = $value;
    }

    /**
     * Returns a UXON object with data source specific properties of the object
     *
     * @return UxonObject
     */
    public function getDataAddressProperties()
    {
        if (is_null($this->data_address_properties)) {
            $this->data_address_properties = $this->getWorkbench()->createUxonObject();
        }
        return $this->data_address_properties;
    }

    /**
     * Returns the value of a data source specific object property specified by it's id
     *
     * @param string $id            
     */
    public function getDataAddressProperty($id)
    {
        return $this->getDataAddressProperties()->getProperty($id);
    }

    /**
     * Sets the value of a data address property specified by it's id
     *
     * @param string $id            
     * @param string $value            
     * @return Object
     */
    public function setDataAddressProperty($id, $value)
    {
        $this->getDataAddressProperties()->setProperty($id, $value);
        return $this;
    }

    /**
     *
     * @param UxonObject $uxon            
     * @return Object
     */
    public function setDataAddressProperties(UxonObject $uxon)
    {
        $this->data_address_properties = $uxon;
        return $this;
    }

    /**
     * DEPRECATED!
     * Parses a string with data address properties to an assotiative array
     *
     * @param unknown $string            
     * @return array
     */
    public function parseDataAddressProperties($string)
    {
        $props = array();
        if (! empty($string)) {
            $props = @json_decode($string, true);
        }
        if (! $props) {
            $props = array();
        }
        return $props;
    }

    /**
     *
     * @return array
     */
    public function getParentObjectsIds()
    {
        return $this->parent_objects_ids;
    }

    /**
     * Returns all objects, this one inherits from as an array
     *
     * @return object[]
     */
    public function getParentObjects()
    {
        $result = array();
        foreach ($this->parent_objects_ids as $id) {
            $result[] = $this->getModel()->getObject($id);
        }
        return $result;
    }

    public function setParentObjectsIds($value)
    {
        $this->parent_objects_ids = $value;
    }

    public function addParentObjectId($object_id)
    {
        $this->parent_objects_ids[] = $object_id;
    }

    /**
     * TODO
     *
     * @param string $object_alias            
     */
    public function getParentObject($object_alias)
    {}

    /**
     * Returns all objects, that inherit from the current one as an array.
     * This includes distant relatives, that inherit
     * from other objects, inheriting from the current one.
     *
     * @return object[]
     */
    public function getInheritingObjects()
    {
        $result = array();
        $res = $this->getModel()->getWorkbench()->model()->getModelLoader()->getDataConnection()->runSql('SELECT o.oid FROM exf_object o WHERE o.parent_object_oid = ' . $this->getId());
        foreach ($res as $row) {
            if ($obj = $this->getModel()->getObject($row['oid'])) {
                $result[] = $obj;
                $result = array_merge($result, $obj->getInheritingObjects());
            }
        }
        return $result;
    }

    /**
     *
     * @return EntityList
     */
    public function getDefaultSorters()
    {
        return $this->default_sorters;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getAppId()
    {
        return $this->app_id;
    }

    public function setAppId($value)
    {
        $this->app_id = $value;
    }

    public function getShortDescription()
    {
        return $this->short_description;
    }

    public function setShortDescription($value)
    {
        $this->short_description = $value;
    }

    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . NameResolver::NAMESPACE_SEPARATOR . $this->getAlias();
        ;
    }

    public function setAliasWithNamespace($value)
    {
        $this->qualified_alias = $value;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function setNamespace($value)
    {
        $this->namespace = $value;
    }

    /**
     * Returns the UXON description of the default editor widget for instances of this object.
     * This can be specified in the meta model
     *
     * @return UxonObject
     */
    public function getDefaultEditorUxon()
    {
        if (is_null($this->default_editor_uxon)) {
            $this->default_editor_uxon = $this->getWorkbench()->createUxonObject();
        } elseif (! ($this->default_editor_uxon instanceof UxonObject)) {
            $this->default_editor_uxon = UxonObject::fromJson($this->default_editor_uxon);
        }
        return $this->default_editor_uxon;
    }

    /**
     * Sets the UXON description for the default editor widget for this object (e.g.
     * to build the EditObjectDialog)
     *
     * @param UxonObject $value            
     * @return Object
     */
    public function setDefaultEditorUxon(UxonObject $value)
    {
        if (! $value->isEmpty() && ! $value->getProperty('object_alias')) {
            $value->setProperty('object_alias', $this->getAliasWithNamespace());
        }
        $this->default_editor_uxon = $value;
        return $this;
    }

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
    public function getDataAddressRequiredPlaceholders()
    {
        return $this->getModel()->getWorkbench()->utils()->findPlaceholdersInString($this->getDataAddress());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->getModel()->getWorkbench();
    }

    /**
     * Returns the attribute group specified by the given alias or NULL if no such group exists.
     * Apart from explicitly defined attribute groups, built-in groups can be used. Built-in groups have aliases starting with "~".
     * For every built-in alias there is a constant in the AttributeGroup class (e.g. AttributeGroup::ALL, etc.)
     *
     * @param string $alias            
     * @return AttributeGroup
     */
    public function getAttributeGroup($alias)
    {
        if (! $this->attribute_groups[$alias]) {
            $this->attribute_groups[$alias] = AttributeGroupFactory::createForObject($this, $alias);
        }
        return $this->attribute_groups[$alias];
    }

    /**
     * Returns TRUE if this object is exactly the one given or inherits from it and FALSE otherwise - similarly to the behavior of PHP instance_of.
     * E.g. if you have an object SPECIAL_FILE, which extends FILE, SPECIAL_FILE->is(FILE) = true, but FILE->is(SPECIAL_FILE) = false.
     *
     * @param Object|string $object_or_alias_or_id            
     * @return boolean
     *
     * @see is_exactly()
     * @see is_extended_from()
     */
    public function is($object_or_alias_or_id)
    {
        if ($this->isExactly($object_or_alias_or_id)) {
            return true;
        } else {
            return $this->isExtendedFrom($object_or_alias_or_id);
        }
    }

    /**
     * Checks if this object matches the given object identifier: if so, returns TRUE and FALSE otherwise.
     * The identifier may be a qualified alias, a UID or an instantiated object.
     *
     * @param Object|string $alias_with_relation_path            
     * @return boolean
     *
     * @see is()
     * @see is_extended_from()
     */
    public function isExactly($object_or_alias_or_id)
    {
        if ($object_or_alias_or_id instanceof Object) {
            if ($object_or_alias_or_id->getId() == $this->getId()) {
                return true;
            }
        } elseif (mb_stripos($object_or_alias_or_id, '0x') === 0) {
            if ($this->getId() == $object_or_alias_or_id) {
                return true;
            }
        } else {
            if (strcasecmp($this->getAliasWithNamespace(), $object_or_alias_or_id) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns TRUE if this object is extended from the given object identifier.
     * The identifier may be a qualified alias, a UID or an instantiated object.
     *
     * @param Object|string $object_or_alias_or_id            
     * @return boolean
     *
     * @see is_exactly()
     * @see is()
     */
    public function isExtendedFrom($object_or_alias_or_id)
    {
        foreach ($this->getParentObjects() as $parent) {
            if ($parent->isExactly($object_or_alias_or_id)) {
                return true;
            }
        }
        return false;
    }

    public function getBehaviors()
    {
        return $this->behaviors;
    }

    /**
     *
     * @return ObjectActionList|ActionInterface[]
     */
    public function getActions()
    {
        if (! ($this->actions instanceof ObjectActionList)) {
            $this->actions = $this->getModel()->getModelLoader()->loadObjectActions(new ObjectActionList($this->getWorkbench(), $this));
        }
        return $this->actions;
    }

    /**
     * Returns the currently running instance of the app, this object belongs to.
     *
     * @return \exface\Core\Interfaces\AppInterface
     */
    public function getApp()
    {
        return $this->getWorkbench()->getApp($this->getNamespace());
    }
}
?>