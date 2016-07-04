<?php
namespace exface\Core\CommonLogic\QueryBuilder;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Factories\ConditionGroupFactory;
/**
 * A filter group query part represents a condition group used for filtering in a query.
 * @author aka
 *
 */
class QueryPartFilterGroup extends QueryPart {
	private $operator = EXF_LOGICAL_AND;
	private $filters = array();
	private $nested_groups = array();
	private $condition_group = null;
	
	public function get_operator() {
		return $this->operator;
	}
	
	public function set_operator($value) {
		$this->operator = $value;
	}
	
	/**
	 * Adds a filter to the group.
	 * @param QueryPartFilter
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
	 */
	public function add_filter(QueryPartFilter $filter){
		// Only add filters based on attributes. A query can only work with meta model attributes, not with other
		// expressions. Filters based on formulas need to be applied by the DataSheet and cannot be handled by queries!
		if ($filter->get_attribute() && !is_null($filter->get_compare_value()) && $filter->get_compare_value() !== ''){
			$this->filters[] = $filter;
			$this->get_condition_group()->add_condition($filter->get_condition());
		}
		return $this;
	}
	
	/**
	 * Creates a filter from a given condition object and adds it to the group
	 * @param Condition $condition
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
	 */
	public function add_condition(Condition $condition){
		$this->add_filter($this->create_query_part_from_condition($condition));
		return $this;
	}
	
	/**
	 * Adds a nested filter group.
	 * @param QueryPartFilterGroup $group
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
	 */
	public function add_nested_group(QueryPartFilterGroup $group){
		$this->nested_groups[] = $group;
		$this->get_condition_group()->add_nested_group($group->get_condition_group());
		return $this;
	}
	
	/**
	 * Creates a filter group from a given condition group and adds it to the group
	 * @param ConditionGroup $group
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
	 */
	public function add_condition_group(ConditionGroup $group){
		$this->add_nested_group($this->create_query_part_from_condition_group($group));
		return $this;
	}
	
	/**
	 * 
	 * @return QueryPartFilter[]
	 */
	public function get_filters(){
		return $this->filters;
	}
	
	/**
	 * 
	 * @return QueryPartFilterGroup[]
	 */
	public function get_nested_groups(){
		return $this->nested_groups;
	}
	
	/**
	 * Returns an array of filters an nested filter groups - that is, all query parts contained in this filter group
	 * @return QueryPart[]
	 */
	public function get_filters_and_nested_groups(){
		return array_merge($this->get_filters(), $this->get_nested_groups());
	}
	
	/**
	 * Creates a filter query part from a condition
	 * @param Condition $condition
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
	 */
	public function create_query_part_from_condition(Condition $condition){
		$qpart = new QueryPartFilter($condition->get_expression()->to_string(), $this->query);
		$qpart->set_condition($condition);
		return $qpart;
	}
	
	/**
	 * Creates a filter group query part from a condition group
	 * @param ConditionGroup $group
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
	 */
	public function create_query_part_from_condition_group(ConditionGroup $group){
		$qpart = new QueryPartFilterGroup('', $this->query);
		$qpart->set_operator($group->get_operator());
		foreach ($group->get_conditions() as $c){
			$qpart->add_condition($c);
		}
		foreach ($group->get_nested_groups() as $g){
			$qpart->add_condition_group($g);
		}
		return $qpart;
	}
	
	/**
	 * A filter group uses all relations used by it's filters and subgroups
	 * @see \exface\Core\CommonLogic\QueryBuilder\QueryPart::get_used_relations()
	 */
	public function get_used_relations($relation_type = null){
		$rels = array();
		foreach ($this->get_filters() as $qpart){
			$rels = array_merge($rels, $qpart->get_used_relations($relation_type));
		}
		foreach ($this->get_nested_groups() as $qpart){
			$rels = array_merge($rels, $qpart->get_used_relations($relation_type));
		}
		return $rels;
	}
	
	/**
	 * Returns an array of meta object ids, that are assumed to be unique in the result of the query because of filtering for
	 * a single instance of that meta object.
	 * @return array
	 */
	public function get_object_ids_safe_for_aggregation(){
		$ids = array();
		foreach ($this->get_filters() as $qpart){
			// TODO The current checks do not really ensure, that the object is unique. Need a better idea!
			if ($qpart->get_comparator() == EXF_COMPARATOR_IS || $qpart->get_comparator() == EXF_COMPARATOR_EQUALS){
				$ids[] = ($qpart->get_attribute()->is_relation() ? $this->get_query()->get_main_object()->get_related_object($qpart->get_alias())->get_id() : $qpart->get_attribute()->get_object()->get_id());
			}
		}
		foreach ($this->get_nested_groups() as $qpart){
			$ids = array_merge($ids, $qpart->get_object_ids_safe_for_aggregation());
		}
		return $ids;
	}
	
	/**
	 * Returns the condition group represented by this filter group.
	 * 
	 * IDEA Currently the condition group is updated every time something happens to the filter group (add_filter(), add_nested_group(), etc.). Perhaps it
	 * is a better idea to build the condition group on demand, because it is only needed on very rare occasions (e.g. for rebasing conditions in subqueries, etc.)
	 * I don't know, if maintainig the condition group all the time has an impact on performance or memory consumption. 
	 * 
	 * @return \exface\Core\CommonLogic\Model\ConditionGroup
	 */
	public function get_condition_group() {
		$exface = $this->exface();
		if (!$this->condition_group) $this->condition_group = ConditionGroupFactory::create_empty($exface, $this->get_operator());
		return $this->condition_group;
	}
	
	/**
	 * Returns a filter query part matching the given alias or FALSE if no match found. Checks nested filter groups recursively.
	 * @param string $alias
	 * @return QueryPartFilter || boolean
	 */
	public function find_filter_by_alias($alias){
		foreach ($this->get_filters() as $f){
			if ($f->get_alias() == $alias) return $f;
		}
		
		foreach ($this->get_nested_groups() as $g){
			if ($f = $g->find_filter_by_alias($alias)) return $f;
		}
		
		return false;
	}
}
?>