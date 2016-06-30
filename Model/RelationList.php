<?php namespace exface\Core\Model;

use exface\exface;
use exface\Core\EntityList;
use exface\Core\Factories\AttributeListFactory;

/**
 * 
 * @author aka
 * 
 */
class RelationList extends EntityList {
	
	/**
	 * An attribute list stores attributes with their aliases for keys unless the keys are explicitly specified. 
	 * Using the alias with relation path ensures, that adding related attributes will never lead to dublicates here!
	 * {@inheritDoc}
	 * @see \exface\Core\EntityList::add()
	 * @param Relation $attribute
	 */
	public function add(&$relaion, $key = null){
		if (is_null($key)){
			$key = $relaion->get_alias_with_relation_path();
		} 
		return parent::add($relaion, $key);
	}
	
	/**
	 * @return model
	 */
	public function get_model(){
		return $this->get_meta_object()->get_model();
	}	
	
	/**
	 * @return Object
	 */
	public function get_meta_object() {
		return $this->get_parent();
	}
	
	public function set_meta_object(Object &$meta_object) {
		return $this->set_parent($meta_object);
	}	
	
	/**
	 * @return Relation[]
	 */
	public function get_all(){
		return parent::get_all();
	}
	
	/**
	 * Returns the attribute matching the given alias or FALSE if no such attribute is found
	 * @param string $alias
	 * @return Attribute|boolean
	 */
	public function get_by_attribute_alias($alias){
		// Most attributes stored here will have no relation path, so for now this fallback to iterating over all members is OK.
		if ($attr = $this->get($alias)){
			return $attr;
		} else {
			foreach ($this->get_all() as $attr){
				if (strcasecmp($attr->get_alias_with_relation_path(), $alias) == 0){
					return $attr;
				}
			}
		}
		return false;
	}
	
	/**
	 * Returns the attribute matching the given UID or FALSE if no such attribute is found
	 * @param string $uid
	 * @return Attribute|boolean
	 */
	public function get_by_attribute_id($uid){
		foreach ($this->get_all() as $attr){
			if (strcasecmp($attr->get_id(), $uid) == 0){
				return $attr;
			}
		}
		return false;
	}
	
	/**
	 * Returns a new attribute list with all attributes of the given data type
	 * @param string $data_type_alias
	 * @return AttributeList
	 */
	public function get_by_data_type_alias($data_type_alias){
		$object = $this->get_meta_object();
		$result = AttributeListFactory::create_for_object($object);
		foreach ($this->get_all() as $key => $attr){
			if (strcasecmp($attr->get_data_type()->get_name(), $data_type_alias) == 0){
				$result->add($attr, $key);
			}
		}
		return $result;
	}
	
	/**
	 * Returns a new attribute list containig only attributes marked as required
	 * @return AttributeList
	 */
	function get_required(){
		$object = $this->get_meta_object();
		$output = AttributeListFactory::create_for_object($object);
		foreach ($this->get_all() as $key => $attr){
			if ($attr->is_required()){
				$output->add($attr, $key);
			}
		}
		return $output;
	}
	
	/**
	 * Returns a list with all attributes, marked for the default display of the object sorted by default_display_order
	 * The list can then be easily used to create widgets to display the object without the user having to
	 * specify which particular attributes to display.
	 * @return AttributeList
	 */
	function get_default_display_list(){
		$object = $this->get_meta_object();
		$defs = AttributeListFactory::create_for_object($object);
		foreach ($this->get_all() as $attr){
			if ($attr->get_default_display_order()){
				if ($attr->is_relation()){
					$rel_path = $attr->get_alias();
					$rel_obj = $object->get_related_object($rel_path);
					$rel_attr = $object->get_attribute(RelationPath::relation_path_add($rel_path, $rel_obj->get_label_alias()));
					// Leave the name of the relation as attribute name and ensure, that it is visible
					$rel_attr->set_name($attr->get_name());
					$rel_attr->set_hidden(false);
					$defs->add($rel_attr, $attr->get_default_display_order());
				} else {
					$defs->add($attr, $attr->get_default_display_order());
				}
			}
		}
	
		// Use the label attribute if there are no defaults defined
		if ($defs->count() == 0 && $label_attribute = $object->get_label_attribute()){
			$defs->add($label_attribute);
		}
	
		$defs->sort_by_key();
		return $defs;
	}
	
}