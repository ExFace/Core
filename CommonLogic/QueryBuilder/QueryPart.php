<?php
namespace exface\Core\CommonLogic\QueryBuilder;
use exface\Core\CommonLogic\Model\Attribute;

class QueryPart {
	protected $query = NULL;
	private $attribute = NULL;
	private $alias = NULL;

	function __construct($alias, AbstractQueryBuilder $query){
		$this->set_alias($alias);
		$this->set_query($query);
	}

	/**
	 * @return AbstractQueryBuilder
	 */
	public function get_query() {
		return $this->query;
	}

	public function set_query(AbstractQueryBuilder $value) {
		$this->query = $value;
	}

	public function get_alias() {
		return $this->alias;
	}

	public function set_alias($value) {
		$this->alias = $value;
	}

	/**
	 * Checks, if the qpart is meaningfull. What exactly is checked depends on the type of the query part
	 * (i.e. a select will need a select statement at the attribute, etc.)
	 * @return boolean
	 */
	public function is_valid(){
		return true;
	}
	
	/**
	 * Returns an array of relations used in this query part. If $relation_type is given, only returns relations of this type.
	 * @param string $relations_type
	 * @return array [ relation_alias_relative_to_main_object => relation_object ]
	 */
	public function get_used_relations($relation_type = null){
		return array();
	}
	
	/**
	 * Returns the first relation of the given type or false if no relations of this type is found.
	 * If $relation_type is ommitted, returns the very first relation regardless of it's type.
	 * @param string $relations_type
	 * @return mixed boolean|array [ relation_alias_relative_to_main_object => relation_object ]
	 */
	public function get_first_relation($relations_type = null){
		$rels = $this->get_used_relations($relations_type);
		$rel = reset($rels);
		return $rel;
	}
	
	/**
	 *
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_attribute() {
		return $this->attribute;
	}
	
	/**
	 *
	 * @param \exface\Core\CommonLogic\Model\Attribute $value
	 */
	public function set_attribute(Attribute $value) {
		$this->attribute = $value;
	}
	
	/**
	 * @return \exface\Core\CommonLogic\Workbench
	 */
	public function get_workbench(){
		return $this->get_query()->get_workbench();
	}
	
}
?>