<?php
namespace exface\Core\CommonLogic\QueryBuilder;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\CommonLogic\DataSheets\DataAggregator;
class QueryPartAttribute extends QueryPart {
	private $aggregate_function;
	private $used_relations = null;

	function __construct($alias, AbstractQueryBuilder &$query){
		parent::__construct($alias, $query);
		
		if (!$attr = $query->get_main_object()->get_attribute($alias)){
			throw new QueryBuilderException('Attribute "' . $alias . '" of object "' . $query->get_main_object()->get_alias() . '" not found!');
		} else {
			$this->set_attribute($attr);
		}
		
		$this->aggregate_function = DataAggregator::get_aggregate_function_from_alias($alias);
	}

	/**
	 * @see \exface\Core\CommonLogic\QueryBuilder\QueryPart::get_used_relations()
	 */
	public function get_used_relations($relation_type = null){
		$rels = array();
		// first check the cache
		if (is_array($this->used_relations)) {
			$rels = $this->used_relations;
		} else {
			// fetch relations
			// first make sure, the attribute has a relation path (otherwise we do not need to to anything
			if ($this->get_attribute()->get_relation_path()->to_string()){
				// split the path in case it contains multiple relations
				$rel_aliases = RelationPath::relation_path_parse($this->get_attribute()->get_relation_path()->to_string());
				// if it is one relation only, use it
				if (!$rel_aliases && $this->get_attribute()->get_relation_path()->to_string()) $rel_aliases[] = $this->get_attribute()->get_relation_path()->to_string();
				// iterate through the found relations
				if ($rel_aliases) {
					$last_alias = '';
					foreach ($rel_aliases as $alias){
						$rels[$last_alias . $alias] = $this->get_query()->get_main_object()->get_relation($last_alias . $alias);
						$last_alias .= $alias . RelationPath::RELATION_SEPARATOR;
					}
				}
			}
			// cache the result
			$this->used_relations = $rels;
		}

		// if looking for a specific relation type, remove all the others
		if ($relation_type){
			foreach ($rels as $alias => $rel){
				if ($rel->get_type() != $relation_type){
					unset ($rels[$alias]);
				}
			}
		}

		return $rels;
	}

	public function get_aggregate_function() {
		return $this->aggregate_function;
	}

	public function set_aggregate_function($value) {
		$this->aggregate_function = $value;
	}

	public function get_data_address_property($property_key) {
		return $this->get_attribute()->get_data_address_property($property_key);
	}
	
	/**
	 * Returns the data source specific address of the attribute represented by this query part. Depending
	 * on the data source, this can be an SQL column name, a file name, etc.
	 * @return string
	 */
	public function get_data_address(){
		return $this->get_attribute()->get_data_address();
	}
	
	public function get_meta_model(){
		return $this->get_attribute()->get_model();
	}
	
	/**
	 * Parses the alias of this query part as an ExFace expression and returns the expression object
	 * @return \exface\Core\CommonLogic\Model\Expression
	 */
	public function get_expression(){
		return $this->get_workbench()->model()->parse_expression($this->get_alias(), $this->get_query()->get_main_object());
	}
	
	public function rebase(AbstractQueryBuilder &$new_query, $relation_path_to_new_base_object){
		// FIXME use deep copy here instead of clone
		$qpart = clone $this;
		$qpart->set_query($new_query);
		$new_expression = $this->get_expression()->rebase($relation_path_to_new_base_object);
		$qpart->used_relations = array();
		$qpart->set_attribute($new_expression->get_attribute());
		$qpart->set_alias($new_expression->to_string());
		return $qpart;
	}
}
?>