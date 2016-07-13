<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\Exceptions\MetaModelValidationException;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Exceptions\UxonParserWarning;
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

class Object implements ExfaceClassInterface, AliasInterface {
	private $id;
	private $name;
	private $alias;
	private $data_address;
	private $data_address_properties;
	private $label;
	private $uid_alias;
	private $attributes = array();
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
	
	function __construct(\exface\Core\CommonLogic\Model\Model $model){
		$exface = $model->exface();
		$this->model = $model;
		$this->attributes = AttributeListFactory::create_for_object($this);
		$this->default_sorters = EntityListFactory::create_empty($exface, $this);
		$this->behaviors = new ObjectBehaviorList($exface, $this);
	}
	
	/**
	 * Returns all relations as an array with the relation alias as key. If an ALIAS stands for multiple
	 * relations (of different types), the respective element of the relations array will be an array in
	 * turn.
	 * @return relation[] [relation_alias => relation | relation[]]
	 */
	function get_relations_array(){
		return $this->relations;
	}
	
	/**
	 * Returns all direct relations of this object as a flat array. Optionally filtered by relation type.
	 * @return array
	 */
	function get_relations($relation_type = null){
		$result = array();
		foreach ($this->get_relations_array() as $rel){
			if (is_null($relation_type) || (is_array($rel) && $relation_type == '1n') || $relation_type == $rel->get_type()){
				if (is_array($rel)){
					$result = array_merge($result, $rel);
				} else {
					$result[] = $rel;
				}
			}
		}
		return $result;
	}
	
	/**
	 * Returns a relation specified by it's alias. The alias can also be a relation path consisting of multiple
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
	 * @throws MetaModelValidationException if multiple relations are detected for the alias and not reverse alias is specified
	 * @return relation|boolean
	 */
	function get_relation($alias, $foreign_key_alias = ''){
		
		if ($rel = RelationPath::relation_path_parse($alias, 1)){
			$relation = $this->get_related_object($rel[0])->get_relation($rel[1]);
			return $relation;
		}
		
		$rel = $this->relations[$alias];
		// If the object does not have a relation with a matching alias
		if (!$rel) {
			// Check, if a foreign key is specified in the alias (e.g. ADDRESS->COMPANY[SHIPPING_ADDRESS])
			// If so, extract it and call get_relation() again using the separated alias and foreign_key_alias
			if ($start = strpos($alias, '[')){
				if (!$end = strpos($alias, ']')) throw new UxonParserError('Missing "]" in relation alias "' . $alias . '"');
				$foreign_key_alias = substr($alias, $start+1, $end-$start-1);
				$alias = substr($alias, 0, $start);
				return $this->get_relation($alias, $foreign_key_alias);
			} else {
				return false; 
			}
		}
		
		if (!is_array($rel)){
			return $rel;
		} else {
			$first_rel = false;
			/* @var $r \exface\Core\CommonLogic\Model\relation */
			foreach ($rel as $r){
				// If there is no specific foreign key alias, try to select the most feasable relation,
				// otherwise just look for a relation with with a matching foreign key alias 
				if (!$foreign_key_alias){
					// Always return the regular relation if there are regular and reverse ones in the array.
					// In this case, the reverse relation can only be addressed using the $foreign_key_alias
					// There can only be at most one regular relation with the same alias for one object, but there
					// can be multiple reverse relations with the same alias.
					if ($r->get_type() == 'n1'){
						return $r;
					} elseif ($first_rel === false) {
						$first_rel = $r;
					}
				} elseif ($r->get_foreign_key_alias() == $foreign_key_alias){
					return $r;
				}
			}
			return $first_rel;
		}
		return false;
	}
	
	/**
	 * Returns a list of all direct attributes of this object (including inherited ones!)
	 * @return AttributeList
	 */
	function get_attributes(){
		return $this->attributes;
	}
	
	/**
	 * Returns an attribute matching the given attribute alias. Supports aliases with relations (e.g. CUSTOMER__CUSTOMER_GROUP__LABEL). 
	 * If an attribute of a related object is requested, it will have a non-empty relation path holding all relations needed to reach 
	 * the related object (e.g. CUSTOMER__CUSTOMER_GROUP for CUSTOMER__CUSTOMER_GROUP__NAME): @see Attribute::get_relation_path()
	 * @param string attribute alias
	 * @return Attribute
	 */
	function get_attribute($alias){
		// First of all check, if it is a direct attribute. This is the simplest case and the fastets one too
		if ($this->get_attributes()->get($alias)){
			return $this->get_attributes()->get($alias);
		}
		
		// Return false if the $alias starts with = and thus is a formula and not an alias!
		if (substr($alias, 0, 1) == '=') return false;
		
		// check for aggregate functions and remove them
		if ($aggr = DataAggregator::get_aggregate_function_from_alias($alias)){
			$alias = substr($alias, 0, strlen(DataAggregator::AGGREGATION_SEPARATOR . $aggr)*(-1));
			// check if it is a direct attribute again (now, as the aggregator was removed)
			if ($this->get_attributes()->get($alias)){
				return $this->get_attributes()->get($alias);
			}
		}
		
		// If the attribute has a relation path, delegate to the next related object and so on for every relation in the
		// path. The last object in the relation path must deal with the actual attribute then.
		if ($rel_parts = RelationPath::relation_path_parse($alias, 1)){
			if ($rel_attr = $this->get_related_object($rel_parts[0])->get_attribute($rel_parts[1])){
				$attr = $rel_attr->copy();
				$attr->get_relation_path()->prepend_relation($this->get_relation($rel_parts[0]));
				return $attr;
			} 
		}
		
		// At this point only two possibilities are left: it's either a reverse relation or an error
		if ($rev_rel = $this->get_relation($alias)){
			if ($rev_rel->get_type() == '1n'){
				if ($rel_attr = $rev_rel->get_related_object()->get_attribute($rev_rel->get_foreign_key_alias())){
					$attr = $rel_attr->copy();
					$attr->get_relation_path()->prepend_relation($rev_rel);
					return $attr;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Returns the object related to the current one via the given relation path string
	 * @param string $relation_path_string
	 * @return Object
	 */
	function get_related_object($relation_path_string){
		$relation_path = RelationPathFactory::create_from_string($this, $relation_path_string);
		return $relation_path->get_end_object();
	}
	
	/**
	 * Adds a relation to the object.
	 * TODO When adding reverse relations, it is possible, that there are two relations from the same object,
	 * thus having the same aliases (the alias of the reverse relation is currently the alias of the object,
	 * where it comes from). I like this kind of naming, but it needs to be extended by the possibility to
	 * specify which of the two reverse relation to use (e.g. LOCATION->ADDRESS[SHIPPING_ADDRESS] or something)
	 * @param relation $relation
	 */
	function add_relation(Relation $relation){
		// If there already is a relation with this alias, add another one, making it an array of relations
		if ($duplicate = $this->get_relation($relation->get_alias())){
			if (is_array($duplicate)){
				$this->relations[$relation->get_alias()][] = $relation;
			} else {
				$this->relations[$relation->get_alias()] = array($duplicate, $relation);
			}
		} else {
			$this->relations[$relation->get_alias()] = $relation;
		}
	}
	
	/**
	 * Inherits all attributes, relations and actions from the given parent object. Parts of the parent
	 * can be overridden in the extended object by creating an attribute, relation, etc. with the same alias,
	 * as the parent has. 
	 * 
	 * Inherited elements become property of the extende object and loose any connection to their parents
	 * (i.e. changing an attribute on the parent object at window will not effect the respective inherited
	 * attribute of the extended object). However, using the method get_inherited_from_object_id() of an
	 * inherited element, it can be determined, whether the element is inherited and from which object.
	 * 
	 * @param string $parent_object_id
	 */
	public function extend_from_object_id($parent_object_id){
		// Do nothing, if trying to extend itself
		if ($parent_object_id == $this->get_id()) return;
		// Otherwise clone all attributes and relations of the parent and add them to this object
		$parent = $this->get_model()->get_object($parent_object_id);
		$this->add_parent_object_id($parent_object_id);
		// Inherit some basic object properties, that migtht later be overridden by attributes
		$this->set_uid_alias($parent->get_uid_alias());
		$this->set_label_alias($parent->get_label_alias());
		// Inherit attributes
		foreach ($parent->get_attributes() as $alias => $attr){
			$attr_clone = $attr->copy();
			// Save the parent's id, if there isn't one already (that would mean, that the parent inherited the attribute too)
			if(is_null($attr->get_inherited_from_object_id())){
				$attr_clone->set_inherited_from_object_id($parent_object_id);
				// TODO Is it a good idea to set the object id of the inheridted attribute to the inheriting object? Would it be
				// better, if we only do this for objects, that do not have their own data address and merely are containers for attributes?
				// $attr_clone->set_object_id($this->get_id());
			}
			$this->get_attributes()->add($attr_clone);
		}
		// Inherit Relations
		foreach ($parent->get_relations_array() as $rel){
			$rel_clone = clone $rel;
			// Save the parent's id, if there isn't one already (that would mean, that the parent inherited the attribute too)
			if(is_null($rel->get_inherited_from_object_id())){
				$rel_clone->set_inherited_from_object_id($parent_object_id);
			}
			$this->add_relation($rel_clone);
		}
		// Inherit behaviors
		foreach ($parent->get_behaviors()->get_all() as $key => $behavior){
			$copy = $behavior->copy()->set_object($this);
			$this->get_behaviors()->add($copy, $key);
		}
		// TODO Inherit actions here as soon as actions can be defined in the model
	}
	
	/**
	 * Finds a relation to a specific object. If there are regular and reverse relations to the desired 
	 * object, the regular relation (n-to-1) will be returned. If there are multiple reverse relations,
	 * the first one will be returned. 
	 * Returns FALSE if no relation to the given object is found.
	 * Note: Currently this will only work for direct relations. Chained relations can be found via find_relation_path().
	 * @see find_relation_path()
	 * 
	 * @param string $related_object_id
	 * @return relation
	 */
	public function find_relation($related_object_id){
		foreach ($this->get_relations() as $rel){
			if ($rel->get_related_object_id() == $related_object_id) return $rel;
		}
		return false;
	}
	
	/**
	 * Returns the relation path to a given object or FALSE that object is not related to the current one. In contrast to
	 * find_relation() this method returns merely the relation path, not the relation itself.
	 * FIXME This does not work very well. It would be better to create a single finder method, that would return a relation and
	 * to make the relation know its path like the attributes do.
	 * @see find_relation()
	 * 
	 * @param object $related_object
	 * @param number $max_depth
	 * @param RelationPath $start_path
	 * @return RelationPath | boolean
	 */
	public function find_relation_path(Object $related_object, $max_depth = 3, RelationPath $start_path = null){
		$path = $start_path ? $start_path : new RelationPath($this);
		
		if ($rel = $path->get_end_object()->find_relation($related_object->get_id())){
			$path->append_relation($rel);
		} elseif ($max_depth > 1){
			$result = false;
			foreach ($this->get_relations() as $rel){
				$possible_path = $path->copy();
				if ($result = $this->find_relation_path($related_object, $max_depth-1, $possible_path->add_relation($rel))){
					return $result;
				}
			}
		} else {
			return false;
		}
		
		return $path;
	}
	
	/**
	 * Returns an array with all attributes of this object having the specified data address (e.g. SQL column name)
	 * @param string $data_address
	 * @return attribute[]
	 */
	public function find_attributes_by_data_address($data_address){
		$result = array();
		foreach ($this->get_attributes() as $attr){
			if ($attr->get_data_address() == $data_address){
				$result[] = $attr;
			}
		}
		return $result;
	}
	
	public function get_uid_alias(){
		return $this->uid_alias;
	}
	
	public function set_uid_alias($value){
		$this->uid_alias = $value;
	}
	
	/**
	 * Returns the meta attribute with the unique ID of the object
	 * @return \exface\Core\CommonLogic\Model\attribute
	 */
	public function get_uid_attribute(){
		return $this->get_attribute($this->get_uid_alias());
	}
	
	public function get_label_alias(){
		return $this->label;
	}
	
	public function set_label_alias($value){
		$this->label = $value;
	}
	
	/**
	 * Returns the meta attribute with the label of the object
	 * @return \exface\Core\CommonLogic\Model\attribute
	 */
	public function get_label_attribute(){
		return $this->get_attribute($this->get_label_alias());
	}
	
	public function get_data_source_id(){
		return $this->data_source_id;
	}
	
	public function set_data_source_id($value){
		$this->data_source_id = $value;
	}
	
	/**
	 * Returns the data source for this object. The data source is fully initialized and the connection is already established.
	 * @return \exface\Core\CommonLogic\DataSource
	 */
	public function get_data_source(){
		return $this->get_model()->exface()->data()->get_data_source($this->get_data_source_id(), $this->data_connection_alias);
	}
	
	/**
	 * Returns the data connection for this object. The connection is already initialized and established.
	 * @return \exface\Core\CommonLogic\AbstractDataConnector
	 */
	function get_data_connection(){
		return $this->get_model()->exface()->data()->get_data_connection($this->data_source_id, $this->data_connection_alias);
	}
	
	/**
	 * Sets a custom data connection to be used for this object. This way, the default connection for the data source can be overridden!
	 * @param string $alias
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	function set_data_connection_alias($alias){
		$this->data_connection_alias = $alias;
		return $this;
	}
	
	function get_query_builder(){
		
		return $this->get_model()->exface()->data()->get_query_builder($this->data_source_id);
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
	 */
	function create_data_sheet(){
		
		$ds = $this->get_model()->exface()->data()->create_data_sheet($this);
		return $ds;
	}
	
	public function get_id() {
	  return $this->id;
	}
	
	public function set_id($value) {
	  $this->id = $value;
	}  
	
	public function get_alias() {
	  return $this->alias;
	}
	
	public function set_alias($value) {
	  $this->alias = $value;
	}
	
	public function get_name() {
	  return $this->name;
	}
	
	public function set_name($value) {
	  $this->name = $value;
	}
	
	public function get_data_address() {
	  return $this->data_address;
	}
	
	public function set_data_address($value) {
	  $this->data_address = $value;
	}
	
	/**
	 * Returns an assotiative array of all data source specific properties of the object
	 */
	public function get_data_address_properties() {
		return $this->data_address_properties;
	}
	
	/**
	 * Returns the value of a data source specifi object property specified by it's id
	 * @param string $id
	 */
	public function get_data_address_property($id){
		return $this->get_data_address_properties()->get_property($id);
	}
	
	/**
	 * @param UxonObject $uxon
	 * @return Object;
	 */
	public function set_data_address_properties(UxonObject $uxon){
		$this->data_address_properties = $uxon;
		return $this;
	}
	
	/**
	 * DEPRECATED!
	 * Parses a string with data address properties to an assotiative array
	 * @param unknown $string
	 * @return array
	 */
	public function parse_data_address_properties($string) {
		$props = array();
		if (!empty($string)){
			$props = @json_decode($string, true);
		}
		if (!$props){
			$props = array();
		}
		return $props;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function get_parent_objects_ids() {
		return $this->parent_objects_ids;
	}
	
	/**
	 * Returns all objects, this one inherits from as an array
	 * @return object[]
	 */
	public function get_parent_objects() {
		$result = array();
		foreach ($this->parent_objects_ids as $id){
			$result[] = $this->get_model()->get_object($id);
		}
		return $result;
	}
	
	public function set_parent_objects_ids($value) {
		$this->parent_objects_ids = $value;
	}
	 
	public function add_parent_object_id($object_id){
		$this->parent_objects_ids[] = $object_id;
	}
	
	/**
	 * TODO
	 * @param string $object_alias
	 */
	public function get_parent_object($object_alias){
		
	}
	
	/**
	 * Returns all objects, that inherit from the current one as an array. This includes distant relatives, that inherit
	 * from other objects, inheriting from the current one.
	 * @return object[]
	 */
	public function get_inheriting_objects(){
		$result = array();
		$res = $this->get_model()->exface()->get_model_data_connector()->query('SELECT o.oid FROM exf_object o WHERE o.parent_object_oid = ' . $this->get_id());
		foreach ($res as $row){
			if ($obj = $this->get_model()->get_object($row['oid'])){
				$result[] = $obj;
				$result = array_merge($result, $obj->get_inheriting_objects());
			}
		}
		return $result;
	}
	
	/**
	 * @return EntityList
	 */
	public function get_default_sorters() {
		return $this->default_sorters;
	}
	
	public function get_model() {
		return $this->model;
	}
	
	public function get_app_id() {
		return $this->app_id;
	}
	
	public function set_app_id($value) {
		$this->app_id = $value;
	}
	
	public function get_short_description() {
		return $this->short_description;
	}
	
	public function set_short_description($value) {
		$this->short_description = $value;
	}
	
	public function get_alias_with_namespace() {
		return $this->get_namespace() . NameResolver::NAMESPACE_SEPARATOR . $this->get_alias();;
	}
	
	public function set_alias_with_namespace($value) {
		$this->qualified_alias = $value;
	}
	
	public function get_namespace() {
		return $this->namespace;
	}
	
	public function set_namespace($value) {
		$this->namespace = $value;
	}
	
	/**
	 * Returns the UXON description of the default editor widget for instances of this object. This can be specified in the meta model
	 * @return UxonObject
	 */
	public function get_default_editor_uxon(){	
		if (!is_null($this->default_editor_uxon) && !($this->default_editor_uxon instanceof UxonObject)){
			$this->default_editor_uxon = UxonObject::from_json($this->default_editor_uxon);
		}
		return $this->default_editor_uxon;
	}
	
	public function set_default_editor_uxon(UxonObject $value){
		$this->default_editor_uxon = $value;
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
	public function get_data_address_required_placeholders(){
		return $this->get_model()->exface()->utils()->find_placeholders_in_string($this->get_data_address());
	}
	
	public function exface(){
		return $this->get_model()->exface();
	}
	
	/**
	 * Returns the attribute group specified by the given alias or NULL if no such group exists.
	 * Apart from explicitly defined attribute groups, built-in groups can be used. Built-in groups have aliases starting with "~". 
	 * For every built-in alias there is a constant in the AttributeGroup class (e.g. AttributeGroup::ALL, etc.) 
	 * @param string $alias
	 * @return AttributeGroup
	 */
	public function get_attribute_group($alias){
		if (!$this->attribute_groups[$alias]){
			$this->attribute_groups[$alias] = AttributeGroupFactory::create_for_object($this, $alias);
		}
		return $this->attribute_groups[$alias];
	}
	
	/**
	 * Checks if this object matches the given object identifier: if so, returns TRUE and FALSE otherwise.
	 * The identifier may be a qualified alias, a UID or an instantiated object. 
	 * @param Object|string $alias_with_relation_path
	 * @return boolean
	 */
	public function is($object_or_alias_or_id){
		if ($object_or_alias_or_id instanceof Object){
			if ($object_or_alias_or_id->get_id() == $this->get_id()){
				return true;
			} 
		} elseif (mb_stripos($object_or_alias_or_id, '0x')) {
			if ($this->get_id() == $object_or_alias_or_id){
				return true;
			} 
		} else {
			if (strcasecmp($this->get_alias_with_namespace(), $object_or_alias_or_id) === 0){
				return true;
			} 
		}
		return false;
	}
	
	/**
	 * Returns TRUE if this object is extended from the given object identifier. 
	 * The identifier may be a qualified alias, a UID or an instantiated object. 
	 * @param Object|string $object_or_alias_or_id
	 * @return boolean
	 */
	public function is_extended_from($object_or_alias_or_id){
		foreach ($this->get_parent_objects() as $parent){
			if ($parent->is($object_or_alias_or_id)){
				return true;
			}
		}
		return false;
	}
	
	public function get_behaviors(){
		return $this->behaviors;
	}
}
?>