<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Factories\AttributeGroupFactory;
use exface\Core\Factories\AttributeListFactory;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Factories\EntityListFactory;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Interfaces\Model\MetaObjectActionListInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Model\MetaObjectHasNoDataSourceError;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Exceptions\Model\MetaRelationAliasAmbiguousError;
use exface\Core\DataTypes\RelationCardinalityDataType;

/**
 * Default implementation of the MetaObjectInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class MetaObject implements MetaObjectInterface
{

    private $id;

    private $name;

    private $alias;

    private $data_address;

    private $data_address_properties;

    private $label;

    private $uid_alias;

    private $attributes = null;
    
    private $attributes_alias_cache = array();

    /**
     * Array holding all direct relations to/from the object with the following structure:
     * 
     * [
     *  relation_alias1 => [
     *      0 => relation_instance for the direct regular relation (if exists),
     *      modifier1 => reverse_relation_instance 1,
     *      modifier2 => reverse_relation_instance 2
     *  ],
     *  relation_alias2 => [...]
     * ]
     * 
     * This structure aims to focus on the following requirements:
     * - fast access to regular relations without name conflicts (majority of cases): $this->relations['alias'][0])
     * - fast search for relation alias
     * 
     * @var array
     */
    private $relations = array();

    private $data_source_id;

    private $data_connection_alias = null;

    private $parent_objects_ids = array();

    private $default_sorters = null;

    private $model;

    private $app_id;

    private $namespace;

    private $short_description = '';

    private $default_editor_uxon = null;

    private $attribute_groups = array();

    private $behaviors = null;

    private $actions = array();
    
    private $readable = true;
    
    private $writable = true;

    function __construct(ModelInterface $model)
    {
        $exface = $model->getWorkbench();
        $this->model = $model;
        $this->attributes = AttributeListFactory::createForObject($this);
        $this->default_sorters = EntityListFactory::createEmpty($exface, $this);
        $this->behaviors = new MetaObjectBehaviorList($exface, $this);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getRelations()
     */
    public function getRelations($relation_type = null) : array
    {
        if ($relation_type !== null) {
            if ($relation_type instanceof RelationTypeDataType) {
                $type = $relation_type;
            } else {
                $type = RelationTypeDataType::fromValue($this->getWorkbench(), $relation_type);
            }
        }
        
        $result = array();
        foreach ($this->relations as $set) {
            foreach ($set as $rel) {
                if ($type === null || $type->isEqual($rel->getType())) {
                    $alias = $rel->getAliasWithModifier();
                    $result[$alias] = $rel;
                }
            }
        }
        
        return $result;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getRelation()
     */
    public function getRelation(string $aliasOrPathString, string $modifier = '') : MetaRelationInterface
    {
        if ($rel = RelationPath::relationPathParse($aliasOrPathString, 1)) {
            $relation = $this->getRelatedObject($rel[0])->getRelation($rel[1], $modifier);
            return $relation;
        } else {
            $alias = $aliasOrPathString;
            $aliasWithModifier = $alias . ($modifier ? '[' . $modifier . ']' : '');
        }
        
        $rels = $this->relations[$alias];
        
        // If the object does not have a relation with a matching alias
        if (empty($rels)) {
            // Check, if a foreign key is specified in the alias (e.g. ADDRESS->COMPANY[SHIPPING_ADDRESS])
            // If so, extract it and call get_relation() again using the separated alias and foreign_key_alias
            if ($start = strpos($alias, '[')) {
                if (! $end = strpos($alias, ']')) {
                    throw new MetaRelationNotFoundError($this, 'Missing "]" in relation alias "' . $alias . '"', '6T91HJK');
                }
                $modifier = substr($alias, $start + 1, $end - $start - 1);
                $alias = substr($alias, 0, $start);
                return $this->getRelation($alias, $modifier);
            } else {
                throw new MetaRelationNotFoundError($this, 'Relation "' . $aliasWithModifier . '" not found for object "' . $this->getAliasWithNamespace() . '": no relations with alias "' . $alias . '" exist!');
            }
        }
        
        $key = $this->sanitizeRelationModifier($modifier);
        
        // If there is an exact match for the given modifier, return it right away
        // This will also be the case if no modifier is set and there is a default 
        // (regular) relation
        if ($match = $rels[$key]) {
            return $match;
        }
        
        if ($modifier !== '') {
            throw new MetaRelationNotFoundError($this, 'Relation "' . $aliasWithModifier . ' not found for object "' . $this->getAliasWithNamespace() . '": no match for modifier "' . $modifier . '"!');
        }
        
        // If there is no relation explicitly matching the modifier, we are looking for 
        // the default reverse relation. A reverse relation can be used by default, if
        // a) it is the only reverse relation with this alias
        // b) it is the only required relation with this alias
        
        /* @var $rel \exface\Core\Interfaces\Model\MetaRelationInterface */
        $revRels = 0;
        $revRelsReq = 0;
        $lastReqRel = null;
        foreach ($rels as $rel) {
            if ($rel->isForwardRelation()) {
                return $rel;
            }
            $revRels++;
            if ($rel->getRightKeyAttribute(false)->isRequired()) {
                $revRelsReq++;
                $lastReqRel = $rel;
            }
        }
        
        if ($revRels === 1) {
            return $rel;
        } elseif ($revRelsReq === 1) {
            return $lastReqRel;
        }
        
        // Now we know, multiple potential matches exist, so the relation is ambiguos
        // 1) there is more than one reverse relation matching the alias
        // 2) there was no modifier specified
        // 3) none of the reverse relations can be used by default (= is the only required relation)
        throw new MetaRelationAliasAmbiguousError($this, 'Relation "' . $aliasWithModifier . '" ambiguously defined for object "' . $this->getAliasWithNamespace() . '"!', '70X3MLA');
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
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    function getAttributes()
    {
        return $this->attributes;
    }

    /**        
     * 
     * {@inheritdoc}
     * @see MetaObjectInterface::getAttribute()
     * 
     * TODO if a related attribute is request, a copy is created with the first
     * call of the method and that copy is cached. This means, any changes on the
     * original attribute will not affect the copy anymore. This is dangerous!
     * To change this, we should replace all $attribute->getRelationPath() with
     * $object->getRelationPath($attribute) and just use references to attributes
     * in the cache. 
     */
    public function getAttribute(string $alias) : MetaAttributeInterface
    {
        // First of all check, the cache: 
        // - if it's a HIT (key exists) and the key points to an attribute, return it
        // - if it's a MISS, search for the attribute
        // - if it's a HIT, but the value is FALSE skip the searching an head to the exception trowing
        $attr = $this->getAttributeCache($alias);
        if ($attr instanceof MetaAttributeInterface){
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
            if (($alias_without_aggregator = DataAggregation::stripAggregator($alias)) !== $alias) {
                $alias = $alias_without_aggregator;
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
                    throw new MetaAttributeNotFoundError($this, 'Attribute "' . $alias . '" not found for object "' . $this->getAliasWithNamespace() . '": invalid relation path "' . $rel_parts[0] . '"!', null, $e);
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
                if ($rev_rel->isReverseRelation() || ($rev_rel->getCardinality() == RelationCardinalityDataType::ONE_TO_ONE)) {
                    try {
                        $rel_attr = $rev_rel->getRightObject()->getAttribute($rev_rel->getRightKeyAttribute()->getAlias());
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
     * @param MetaAttributeInterface|boolean|null $value
     * @throws InvalidArgumentException
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    protected function setAttributeCache($alias, $value = null)
    {
        if ($value === false || is_null($value) || $value instanceof MetaAttributeInterface){
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
     * @return MetaAttributeInterface|boolean|null
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
    public function hasAttribute(string $alias) : bool
    {
        $cache = $this->getAttributeCache($alias);
        if ($cache === false){
            return false;
        } elseif ($cache instanceof MetaAttributeInterface){
            return true;
        }
        
        try {
            $this->getAttribute($alias);
        } catch (MetaAttributeNotFoundError $e) {
            return false;
        }
        return true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getRelatedObject()
     */
    public function getRelatedObject($relation_path_string) : MetaObjectInterface
    {
        $relation_path = RelationPathFactory::createFromString($this, $relation_path_string);
        return $relation_path->getEndObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::addRelation()
     */
    public function addRelation(MetaRelationInterface $relation) : MetaObjectInterface
    {        
        $modifier = $this->sanitizeRelationModifier($relation->getAliasModifier());
        $this->relations[$relation->getAlias()][$modifier] = $relation;
        return $this;
    }
    
    /**
     * 
     * @param string $modifier
     * @return string
     */
    protected function sanitizeRelationModifier(string $modifier) : string
    {
        return $modifier === '' ? 0 : $modifier;
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
        // The inheriting object will only be readable/writable if it is marked as such itself
        // and the parent is readable or writable respectively.
        if ($this->isReadable() === true && $parent->isReadable() === false){
            $this->setReadable(false);
        }
        if ($this->isWritable() === true && $parent->isWritable() === false){
            $this->setWritable(false);
        }
        
        // Inherit default editor
        $default_editor_uxon = $parent->getDefaultEditorUxon()->copy();
        // If the default editor is explicitly based on the object, we are inheriting from, replace that object with this one.
        if ($default_editor_uxon->getProperty('object_alias') && $parent->is($default_editor_uxon->getProperty('object_alias'))) {
            $default_editor_uxon->setProperty('object_alias', $this->getAliasWithNamespace());
        }
        $this->setDefaultEditorUxon($default_editor_uxon);
        
        // Inherit some object properties originating from attributes
        $this->setUidAttributeAlias($parent->getUidAttributeAlias());
        $this->setLabelAttributeAlias($parent->getLabelAttributeAlias());
        
        // Inherit description
        $this->setShortDescription($parent->getShortDescription());
        
        // Inherit attributes
        foreach ($parent->getAttributes() as $attr) {
            $attr_clone = $attr->copy();
            
            // Save the object, we are inheriting from in the attribute
            $attr_clone->setInheritedFromObjectId($parent_object_id);
            
            // IDEA Is it a good idea to set the object of the inheridted attribute to the inheriting object? Would it be
            // better, if we only do this for objects, that do not have their own data address and merely are containers for attributes?
            //
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
            $attr_clone->setObject($this);
            
            $this->getAttributes()->add($attr_clone);
        }
        
        // Inherit Relations
        foreach ($parent->getRelations() as $rel) {
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
     * @param MetaObjectInterface $related_object            
     * @return MetaRelationInterface
     */
    public function findRelation(MetaObjectInterface $related_object, $prefer_direct_relations = false)
    {
        $first_relation = false;
        foreach ($this->getRelations() as $rel) {
            // It is important to compare the ids only, because otherwise the related object will need to be loaded.
            // Don't call getRightObject() here to loading half the model just to look through relations.
            if ($related_object->is($rel->getRightObjectId())) {
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::findRelations()
     */
    public function findRelations(string $related_object_id, $relation_type = null) : array
    {
        $rels = array();
        if ($relation_type !== null) {
            if ($relation_type instanceof RelationTypeDataType) {
                $type = $relation_type;
            } else {
                $type = RelationTypeDataType::fromValue($this->getWorkbench(), $relation_type);
            }
        }
        foreach ($this->getRelations() as $rel) {
            try {
                if ($rel->getRightObject()->is($related_object_id) && ($type === null || $type->isEqual($rel->getType())))
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
     * @param MetaObjectInterface $related_object            
     * @param number $max_depth            
     * @param MetaRelationPathInterface $start_path            
     * @return MetaRelationPathInterface|boolean
     */
    public function findRelationPath(MetaObjectInterface $related_object, $max_depth = 3, MetaRelationPathInterface $start_path = null)
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

    public function getUidAttributeAlias()
    {
        return $this->uid_alias;
    }

    public function setUidAttributeAlias($value)
    {
        $this->uid_alias = $value;
    }

    /**
     * Returns the meta attribute with the unique ID of the object.
     *
     * @throws MetaObjectHasNoUidAttributeError if no UID attribute defined for this object
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getUidAttribute()
    {
        if (! $this->getUidAttributeAlias()) {
            throw new MetaObjectHasNoUidAttributeError($this, 'No UID attribute defined for object "' . $this->getAliasWithNamespace() . '"!');
        }
        return $this->getAttribute($this->getUidAttributeAlias());
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::hasLabelAttribute()
     */
    public function hasLabelAttribute()
    {
        return $this->getLabelAttributeAlias() ? true : false;
        
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getLabelAttributeAlias()
     */
    public function getLabelAttributeAlias()
    {
        return $this->label;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::setLabelAttributeAlias()
     */
    public function setLabelAttributeAlias($value)
    {
        $this->label = $value;
    }

    /**
     * Returns the meta attribute with the label of the object
     *
     * @return MetaAttributeInterface
     */
    public function getLabelAttribute()
    {
        if (! $this->getLabelAttributeAlias()) {
            return null;
        }
        return $this->getAttribute($this->getLabelAttributeAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getDataSourceId()
     */
    public function getDataSourceId()
    {
        return $this->data_source_id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::setDataSourceId()
     */
    public function setDataSourceId($value)
    {
        $this->data_source_id = $value;
    }
    
    /**
     * 
     * @return boolean
     */
    public function hasDataSource()
    {
        return is_null($this->data_source_id) ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getDataSource()
     */
    public function getDataSource()
    {
        return $this->getModel()->getWorkbench()->data()->getDataSource($this->getDataSourceId(), $this->data_connection_alias);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getDataConnection()
     */
    public function getDataConnection()
    {
        if (! $this->hasDataSource()) {
            throw new MetaObjectHasNoDataSourceError($this, 'Cannot get the data connection for "' . $this->getName() . '" (' . $this->getAliasWithNamespace() . '): the object does not have a data source!');
        }
        return $this->getDataSource()->getConnection();
    }

    /**
     * Sets a custom data connection to be used for this object.
     * This way, the default connection for the data source can be overridden!
     *
     * @param string $alias            
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    function setDataConnectionAlias($alias)
    {
        $this->data_connection_alias = $alias;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getQueryBuilder()
     */
    public function getQueryBuilder()
    {
        if (! $this->hasDataSource()) {
            throw new MetaObjectHasNoDataSourceError($this, 'Cannot create a query builder for "' . $this->getName() . '" (' . $this->getAliasWithNamespace() . '): the object does not have a data source!');
        }
        return $this->getModel()->getWorkbench()->data()->getQueryBuilder($this->data_source_id);
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
            $this->data_address_properties = new UxonObject();
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
     * @return MetaObjectInterface
     */
    public function setDataAddressProperty($id, $value)
    {
        $this->getDataAddressProperties()->setProperty($id, $value);
        return $this;
    }

    /**
     *
     * @param UxonObject $uxon            
     * @return MetaObjectInterface
     */
    public function setDataAddressProperties(UxonObject $uxon)
    {
        $this->data_address_properties = $uxon;
        return $this;
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
     * @return MetaObjectInterface[]
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
     * @return MetaObjectInterface[]
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
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
        ;
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getDefaultEditorUxon()
     */
    public function getDefaultEditorUxon()
    {
        if (is_null($this->default_editor_uxon)) {
            $this->default_editor_uxon = new UxonObject();
        } elseif (! ($this->default_editor_uxon instanceof UxonObject)) {
            $this->default_editor_uxon = UxonObject::fromJson($this->default_editor_uxon);
        }
        return $this->default_editor_uxon->copy();
    }

    /**
     * Sets the UXON description for the default editor widget for this object (e.g.
     * to build the ShowObjectEditDialog)
     *
     * @param UxonObject $value            
     * @return MetaObjectInterface
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getDataAddressRequiredPlaceholders()
     */
    public function getDataAddressRequiredPlaceholders($includeStaticPlaceholders = true, $includeDynamicPlaceholders = true)
    {
        $result = [];
        foreach (StringDataType::findPlaceholders($this->getDataAddress()) as $ph) {
            if (substr($ph, 0, 1) === '~') {
                if ($includeStaticPlaceholders) {
                    $result[] = $ph; 
                }
            } elseif ($includeDynamicPlaceholders) {
                // Attribute level
                $result[] = $ph;
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->getModel()->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::getAttributeGroup()
     */
    public function getAttributeGroup($alias)
    {
        if (! $this->attribute_groups[$alias]) {
            $this->attribute_groups[$alias] = AttributeGroupFactory::createForObject($this, $alias);
        }
        return $this->attribute_groups[$alias];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::is()
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
     * @param MetaObjectInterface|string $alias_with_relation_path            
     * @return boolean
     *
     * @see is()
     * @see is_extended_from()
     */
    public function isExactly($object_or_alias_or_id)
    {
        if ($object_or_alias_or_id instanceof MetaObjectInterface) {
            if ($object_or_alias_or_id->getId() === $this->getId()) {
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
     * @param MetaObjectInterface|string $object_or_alias_or_id            
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
     * @return MetaObjectActionListInterface|ActionInterface[]
     */
    public function getActions()
    {
        if (! ($this->actions instanceof MetaObjectActionListInterface)) {
            $this->actions = $this->getModel()->getModelLoader()->loadObjectActions(new MetaObjectActionList($this->getWorkbench(), $this));
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
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::isReadable()
     */
    public function isReadable()
    {
        if ($this->hasDataSource() === false) {
            return false;
        }        
        if ($this->readable === true && $this->getDataSource()->isReadable() === false){
            return false;
        }
        return $this->readable;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::setReadable()
     */
    public function setReadable($true_or_false)
    {
        $this->readable = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::isWritable()
     */
    public function isWritable(bool $ignoreSourceSettings = false)
    {
        if (! $this->hasDataSource()) {
            return false;
        }
        if ($this->writable && $ignoreSourceSettings === false && ! $this->getDataSource()->isWritable()){
            return false;
        }
        return $this->writable;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaObjectInterface::setWritable()
     */
    public function setWritable($true_or_false)
    {
        $this->writable = BooleanDataType::cast($true_or_false);
        return $this;
    }


}
?>