<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;

/**
 * The relation path object holds all relations needed to reach the end object from the start object. If attributes
 * or relations are fetched from an object, that they don't directly belong to, the relation path part of their
 * qualified alias will be stored here (not the alias itself!): the attribute ORDER->ORDER_TYPE->CODE fetched
 * from the object ORDER_POSITION will have an attribute path of two relations: ORDER and ORDER_TYPE. It will
 * not contain any information about the attribute CODE itself, but will be stored in this attribute.
 * 
 * TODO Moving from string relation paths to this object introduces lot's auf new possibilities. Old code should
 * be rewritten to use them: specificly query builders and widgets, relying on relation paths! After this, the
 * static methods relation_path_xxx can be removed or made protected!
 * 
 * @author Andrej Kabachnik
 *
 */
class RelationPath implements \IteratorAggregate {
	const RELATION_SEPARATOR = '__';
	
	/* Properties to be dublicated on copy() */
	private $relations = array();
	
	/* Properties NOT to be dublicated on copy() */
	private $start_object = null;
	
	public function __construct(Object $start_object){
		$this->start_object = $start_object;
	}
	
	/**
	 * Returns all relations from this path as an array
	 * @return relation[]
	 */
	public function get_relations(){
		return $this->relations;
	}
	
	/**
	 * Adds the given relation to the right end of the path
	 * @param Relation $relation
	 * @return \exface\Core\CommonLogic\Model\RelationPath
	 */
	public function append_relation(Relation $relation){
		$this->relations[] = $relation;
		return $this;
	}
	
	/**
	 * Adds the given relation to the left end of the path
	 * @param Relation $relation
	 * @return \exface\Core\CommonLogic\Model\RelationPath
	 */
	public function prepend_relation(Relation $relation){
		array_unshift($this->relations, $relation);
		return $this;
	}
	
	/**
	 * Adds all relations from the given path string to the left end of the path. Use with caution as ambiguos
	 * reverse relation may cause trouble. Prefer append_relation() insted, because it always explicitly specifies
	 * each relation!
	 * 
	 * @param string $relation_path_string
	 * @return RelationPath
	 */
	public function append_relations_from_string_path($relation_path_string){
		$first_rel = self::get_first_relation_from_string_path($relation_path_string);
		try {
			$rel = $this->get_end_object()->get_relation($first_rel);
			if (is_array($rel)){
				// TODO what if it is a ambiguous reverse relation?
			} else {
				$this->append_relation($rel);
			}
		} catch (MetaRelationNotFoundError $e){
			// Do nothing, if it is not a relation (it's probably an attribute than)
			// IDEA Maybe check, to see if it really is an attribute and throw an error otherwise???
			$rel = false;
		}
		
		if ($first_rel != $relation_path_string){
			return $this->append_relations_from_string_path(substr($relation_path_string, (strlen($first_rel) + strlen(self::RELATION_SEPARATOR))));
		}
		
		return $this;
	}
	
	protected static function get_first_relation_from_string_path($string_relation_path){
		return explode(self::RELATION_SEPARATOR, $string_relation_path, 2)[0];
	}
	
	public function to_string(){
		$path = '';
		foreach ($this->get_relations() as $rel){
			$path = $path . self::RELATION_SEPARATOR . $rel->get_alias();
		}
		$path = trim($path, self::RELATION_SEPARATOR);
		return $path;
	}
	
	public function get_workbench(){
		return $this->get_start_object()->get_model()->get_workbench();
	}
	
	public function get_start_object(){
		return $this->start_object;
	}
	
	/**
	 * Returns the last object in the relation path (the related object of the last relation)
	 * @return Object
	 */
	public function get_end_object(){
		if ($this->count_relations()){
			return $this->get_relation($this->count_relations())->get_related_object();
		} else {
			return $this->get_start_object();
		}
	}
	
	/**
	 * Returns the nth relation in the path (starting with 1 for the first relation).
	 * @param integer $sequence_number
	 * @return Relation
	 */
	public function get_relation($sequence_number){
		return $this->get_relations()[$sequence_number-1];
	}
	
	/**
	 * Returns the first relation of the path or NULL if the path is empty
	 * @return Relation
	 */
	public function get_relation_first(){
		return $this->get_relation(1);
	}
	
	/**
	 * Returns the last relation of the path or NULL if the path is empty
	 * @return Relation
	 */
	public function get_relation_last(){
		return $this->get_relation($this->count_relations());	
	}
	
	public function count_relations(){
		return count($this->get_relations());
	}
	
	/**
	 * DEPRECATED! Use RelationPathFactory::crate_from_string_path() instead!
	 * TODO make protected or remove
	 * Checks if the given alias includes a relation path and returns an array with relations
	 * @param string $col
	 * @param int $depth
	 *
	 * @return array|false
	 */
	public static function relation_path_parse($alias, $depth=0){
		$depth = intval($depth);
	
		$sep = self::RELATION_SEPARATOR;
		if (strpos($alias, $sep) === false) {
			return false;
		} else {
			if ($depth)	{
				return explode($sep, $alias, $depth+1);
			} else {
				return explode($sep, $alias);
			}
		}
	}
	
	/**
	 * DEPRECATED! Use combine() instead!
	 * @param unknown $relation_alias_1
	 * @param unknown $relation_alias_2
	 */
	public static function relation_path_add($relation_alias_1, $relation_alias_2){
		$output = '';
		if ($relation_alias_1){
			$output = $relation_alias_1;
		}
		if ($relation_alias_2){
			$output = $output ? $output . self::RELATION_SEPARATOR . $relation_alias_2 : $relation_alias_2;
		}
	
		return $output;
	}
	
	/**
	 * Returns a new relation path appending the given path to the current one
	 * @param RelationPath $path_to_append
	 * @return RelationPath
	 */
	public function combine(RelationPath $path_to_append){
		$result = $this->copy();
		foreach ($path_to_append as $relation){
			$result->append_relation($relation);
		}
		return $result;
	}
	
	/**
	 * DEPRECATED! Use reverse() instead!
	 * Reverses a given relation path relative to a given object: POSITION->PRODUCT relative to an ORDER will become POSITION->ORDER and thus can now be
	 * used in the context of a PRODUCT.
	 * @param string $relation_path
	 * @param object $meta_object
	 * @return string
	 */
	public static function relation_path_reverse($relation_path, Object $meta_object){
		$output = '';
		// Parse the relation path to get an array of relation aliases
		$relations = self::relation_path_parse($relation_path);
		// If the path is jus a single regular relation, parsing it will return FALSE, because it is also an attribute of the object
		// In this case, jus take the relation path as the alias
		if (!$relations && $meta_object->get_attribute($relation_path)->is_relation()){
			$relations = array($relation_path);
		}
	
		// Loop through the relation alias and find out the alias of each of the from the point of view of the next related object
		$current_object = $meta_object;
		$reverse_aliases = array();
		foreach ($relations as $rel_alias){
			/* @var $rel \exface\Core\CommonLogic\Model\relation */
			try {
				$rel = $current_object->get_relation($rel_alias);
			} catch (MetaRelationNotFoundError $e){
				// Skip non-relations (will ignore regular attributes at the end of the chain)
				continue;
			}
				
			$reverse_aliases[] = $rel->get_reversed_relation()->get_alias();
			$current_object = $rel->get_related_object();
		}
		$reverse_aliases = array_reverse($reverse_aliases);
		$output = implode(self::RELATION_SEPARATOR, $reverse_aliases);
		return $output;
	}
	
	/**
	 * DEPRECATED! Use trim() instead!
	 * Cuts off the relation path leaving only the part between $cut_after_alias and $cut_before_alias
	 * Examples:
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', 'CUSTOMER') = 'ADDRESS__TYPE__LABEL'
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', 'ORDER__CUSTOMER') = 'ADDRESS__TYPE__LABEL'
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', null, 'ADDRESS') = 'ORDER__CUSTOMER'
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', 'ORDER', 'TYPE') = 'CUSTOMER__ADDRESS'
	 * @param string $relation_path
	 * @param string $cut_before_alias
	 * @return string
	 */
	public static function relaton_path_cut($relation_path, $cut_after_alias, $cut_before_alias = null){
		$output = '';
		if ($cut_after_rels = self::relation_path_parse($cut_after_alias)){
			$cut_after_alias = array_shift($cut_after_rels);
		} else {
			$cut_after_rels = array();
		}
		if ($rels = self::relation_path_parse($relation_path)){
			foreach($rels as $rel){
				if ($cut_after_alias) {
					if ($rel === $cut_after_alias){
						$cut_after_alias = array_shift($cut_after_rels);
					}
				} else {
					if ($rel === $cut_before_alias){
						break;
					} else {
						$output = self::relation_path_add($output, $rel);
					}
				}
			}
		}
		return $output;
	}
	
	/**
	 * Returns a new relation path cutting off subpathes left and right of the current path.
	 * Examples:
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', 'CUSTOMER') = 'ADDRESS__TYPE__LABEL'
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', 'ORDER__CUSTOMER') = 'ADDRESS__TYPE__LABEL'
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', null, 'ADDRESS') = 'ORDER__CUSTOMER'
	 * relation_path_cut('ORDER__CUSTOMER__ADDRESS__TYPE__LABEL', 'ORDER', 'TYPE') = 'CUSTOMER__ADDRESS'
	 * 
	 * TODO Rewrite to get rid of the static string methods 
	 * 
	 * @param string $relation_path
	 * @param string $cut_before_alias
	 * @return string
	 */
	public function trim(RelationPath $trim_left, RelationPath $trim_right){
		return self::relaton_path_cut($this->to_string(), $trim_left->to_string(), ($trim_right->is_empty() ? $trim_right->to_string() : null));
	}
	
	/**
	 * DEPRECATED! Use Attribute->get_relation_path() instead
	 * Returns the relation path contained in an attribute alias. If the alias does not contain any relations, returns empty strgin.
	 * E.g. for the attribute CUSTOMER__CUSTOMER_GROUP__LABEL it woud return CUSTOMER__CUSTOMER_GRUP.
	 * For the attribute LABEL it would return '' since there is no relation prefix.
	 * @param string $attribute_alias
	 * @return string relation path separated by the relation separater or empty string if no relation used.
	 */
	public static function get_relation_path_from_alias($string){
		$output = substr($string, 0, strrpos($string, self::RELATION_SEPARATOR));
		return $output;
	}
	
	/**
	 * Copies the relation path keeping the start object, but copying all relations
	 * 
	 * @return RelationPath
	 */
	public function copy(){
		$copy = RelationPathFactory::create_for_object($this->get_start_object());
		foreach ($this->get_relations() as $rel){
			$copy->append_relation($rel->copy());
		}
		return $copy;
	}
	
	/**
	 * Returns TRUE if the path does not contain any relation and FALSE otherwise
	 */
	public function is_empty(){
		if (is_null($this->get_relation_first())){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * TODO Make the staic method relation_path_reverse protected or remove it once all calls are replaced 
	 * by this method!
	 * @return \exface\Core\CommonLogic\Model\RelationPath
	 */
	public function reverse(){
		return RelationPathFactory::create_from_string($this->get_end_object(), self::relation_path_reverse($this->to_string(), $this->get_start_object()));
	}
	
	public function getIterator () {
		return $this->relations;
	}
	
	public function get_relation_separator(){
		return self::RELATION_SEPARATOR;
	}
	
	/**
	 * Returns an attribute of the end object specified by it's attribute alias, but with a relation path relative to the start object.
	 * E.g. calling get_attribute_of_end_object('POSITION_NO') on the relation path ORDER<-POSITION will return ORDER__POSITION__POSITION_NO
	 * @param string $attribute_alias
	 * @return Attribute
	 */
	public function get_attribute_of_end_object($attribute_alias){
		return $this->get_start_object()->get_attribute(self::relation_path_add($this->to_string(), $attribute_alias));
	}
}