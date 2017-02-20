<?php
namespace exface\Core\CommonLogic\QueryBuilder;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\Model\MetaObjectDataConnectionNotFoundError;
abstract class AbstractQueryBuilder {
	protected $main_object;
	protected $attributes = array();
	protected $aggregations = array();
	protected $filters = NULL;
	protected $sorters = array();
	protected $totals = array();
	protected $limit = 0;
	protected $offset = 0;
	protected $values = array();
	
	/**
	 * Performs a create query. Returns the number of successfully created rows.
	 * @param string $data_connection
	 * @return int
	 */
	function create(AbstractDataConnector $data_connection = null){
		throw new QueryBuilderException('Create method not implemented in "' . get_class($this) . '"!');
	}
	
	
	/**
	 * Performs a read query. Returns the number of read rows.
	 * @param string $data_connection
	 * @return int
	 */
	function read(AbstractDataConnector $data_connection = null){
		throw new QueryBuilderException('Read method not implemented in "' . get_class($this) . '"!');
	}
	
	/**
	 * Performs an update query. Returns the number of successfully updated rows.
	 * @param string $data_connection
	 * @return int
	 */
	function update(AbstractDataConnector $data_connection = null){
		throw new QueryBuilderException('Update method not implemented in "' . get_class($this) . '"!');
	}
	
	/**
	 * Performs a delete query. Returns the number of deleted rows.
	 * @param string $data_connection
	 * @return int
	 */
	function delete(AbstractDataConnector $data_connection = null){
		throw new QueryBuilderException('Delete method not implemented in "' . get_class($this) . '"!');
	}
	
	/**
	 * Set the main object for the query
	 * @param \exface\Core\CommonLogic\Model\Object $meta_object
	 * @throws MetaObjectDataConnectionNotFoundError if the data connection for the object cannot be established
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function set_main_object(\exface\Core\CommonLogic\Model\Object $meta_object){
		$this->main_object = $meta_object;
		// Instantiate the data connection for the object here to make sure, all it's settings, contexts, etc. are applied before the query is built!
		if (!$meta_object->get_data_connection()){
			throw new MetaObjectDataConnectionNotFoundError('Cannot setup data connection for object "' . $meta_object->get_alias_with_namespace() . '"!');
		}
		return $this;
	}
	
	/**
	 * Returns the main meta object of the query
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_main_object(){
		return $this->main_object;
	}
	
	/**
	 * Adds an attribute to be fetched by the query
	 * @param string attribute_alias
	 */
	public function add_attribute($alias){
		$qpart = new QueryPartSelect($alias, $this);
		if ($qpart->is_valid()){
			$this->attributes[$alias] = $qpart;
		}
		return $qpart;
	}
	
	/**
	 * @return QueryPartAttribute[]
	 */
	protected function get_attributes(){
		return $this->attributes;
	}
	
	/**
	 * 
	 * @param string $alias
	 * @return QueryPartSelect
	 */
	protected function get_attribute($alias){
		return $this->attributes[$alias];
	}
	
	/**
	 * Adds a total row to the query (i.e. for the footers)
	 * @param string $attribute attribute_alias
	 * @param string $function like SUM, AVG, etc.
	 * @param int $place_in_row row number within a multi-row footer for this total
	 */
	public function add_total($attribute, $function, $place_in_row=0){
		$qpart = new QueryPartTotal($attribute, $this);
		$qpart->set_alias($attribute);
		$qpart->set_function($function);
		$qpart->set_row($place_in_row);
		$this->totals[] = $qpart;
		return $qpart;
	}
	
	protected function get_totals(){
		return $this->totals;
	}
	
	/**
	 * Creates and adds a single filter to the query
	 * @param unknown $attribute_alias
	 * @param unknown $value
	 * @param string $comparator
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function add_filter_from_string($attribute_alias, $value, $comparator = EXF_COMPARATOR_IS){
		$exface = $this->get_workbench();
		$condition = ConditionFactory::create_from_expression($exface, $this->get_workbench()->model()->parse_expression($attribute_alias, $this->get_main_object()), $value, $comparator);
		return $this->add_filter_condition($condition);
	}
	
	/**
	 * Replaces all filters of the query by the given condition group.
	 * @param ConditionGroup $filters
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function set_filters_condition_group(ConditionGroup $condition_group){
		$this->clear_filters();
		$this->filters = $this->get_filters()->create_query_part_from_condition_group($condition_group);
		return $this;
	}
	
	/**
	 * Replaces all filters of the query by the given filter group
	 * @param QueryPartFilterGroup $filter_group
	 */
	protected function set_filters(QueryPartFilterGroup $filter_group){
		$this->filters = $filter_group;
		return $this;
	}
	
	/**
	 * Adds a condition group to the first level of filters
	 * @param ConditionGroup $condition_group
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function add_filter_condition_group(ConditionGroup $condition_group){
		$this->get_filters()->add_condition_group($condition_group);
		return $this;
	}
	
	/**
	 * Adds a filter group query part to the first level of filters
	 * @param QueryPartFilterGroup $filter_group
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	protected function add_filter_group(QueryPartFilterGroup $filter_group){
		$this->get_filters()->add_nested_group($filter_group);
		return $this;
	}
	
	/**
	 * Adds a first level condition to the root filter group
	 * @param Condition $condition
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function add_filter_condition(Condition $condition){
		$this->get_filters()->add_condition($condition);
		return $this;
	}
	
	/**
	 * Adds a filter query part to the first level of filters
	 * @param QueryPartFilter $filter
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	protected function add_filter(QueryPartFilter $filter){
		$this->get_filters()->add_filter($filter);
		return $this;
	}
	
	/**
	 * Returns the root filter group.
	 * @return QueryPartFilterGroup
	 */
	protected function get_filters(){
		if (!$this->filters) $this->filters = new QueryPartFilterGroup('', $this);
		return $this->filters;
	}
	
	/**
	 * Returns a filter query part with the given alias
	 * @return QueryPartFilter
	 */
	protected function get_filter($alias){
		return $this->get_filters()->find_filter_by_alias($alias);
	}
	
	/**
	 * Removes all filters from the query
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function clear_filters(){
		unset($this->filters);
		return $this;
	}
	
	/**
	 * Adds a sorter to the query. Multiple sorters can be added sequentially.
	 * @param string $sort_by attribute_alias
	 * @param string $order
	 */
	public function add_sorter($sort_by, $order = 'ASC'){
		$qpart = new QueryPartSorter($sort_by, $this);
		$qpart->set_order($order);
		$this->sorters[$sort_by . $order] = $qpart;
		// IDEA move this to the read method of the concrete builder, since it might not be neccessary for
		// all data sources.
		$this->add_attribute($sort_by);
		return $qpart;
	}
	
	/**
	 * Returns an array of sorter query parts
	 * @return QueryPartSorter[]
	 */
	protected function get_sorters(){
		return $this->sorters;
	}
	
	/**
	 * Addes a an attribute to aggregate over (= group by for SQL builders)
	 * @param string $attribute_alias
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute
	 */
	public function add_aggregation($attribute_alias){
		$qpart = new QueryPartAttribute($attribute_alias, $this);
		$this->aggregations[$attribute_alias] = $qpart;
		// IDEA move this to the build_sql_query methods since we probably do not always need to add the attribute
		$this->add_attribute($attribute_alias);
		return $qpart;
	}
	
	/**
	 * Returns an array of attribute query parts, that are to be used for aggregation
	 * @return QueryPartAttribute[]
	 */
	protected function get_aggregations(){
		return $this->aggregations;
	}
	
	/**
	 * 
	 * @param string $alias
	 * @return QueryPartAttribute
	 */
	protected function get_aggregation($alias){
		return $this->aggregations[$alias];
	}
	
	/**
	 * Sets pagination for the query (i.e. get $limit lines starting from line number $offset)
	 * @param number $limit
	 * @param number $offset
	 */
	public function set_limit($limit, $offset=0){
		$this->limit = $limit;
		$this->offset = $offset;
	}
	
	/**
	 * 
	 * @return number
	 */
	protected function get_limit(){
		return $this->limit;
	}
	
	/**
	 * 
	 * @return number
	 */
	protected function get_offset(){
		return $this->offset;
	}
	
	/**
	 * Adds a value column with a single row
	 * @param string $attribute_alias
	 * @param string $value
	 * @return QueryPartValue
	 */
	public function add_value($attribute_alias, $value){
		$qpart = new QueryPartValue($attribute_alias, $this);
		$qpart->set_value($value);
		$this->values[$attribute_alias] = $qpart;
		return $qpart;
	}
	
	/**
	 * Adds a value column with multiple rows (in other words multiple values for a single database column). The values
	 * are passed as an array with row ids as keys. What column is meant by "row id" can optionally be specified via the
	 * $row_id_attribute_alias parameter. If not set, the UID column of the main object of the query will be used.
	 * @param string $attribute_alias
	 * @param array $values [ row_id_attribute_alias_value => value_to_be_saved ]
	 * @param array $uids_for_values
	 * @return QueryPartValue
	 */
	public function add_values($attribute_alias, array $values, array $uids_for_values = array()){
		$qpart = new QueryPartValue($attribute_alias, $this);
		$qpart->set_values($values);
		$qpart->set_uids($uids_for_values);
		$this->values[$attribute_alias] = $qpart;
		return $qpart;
	}
	
	/**
	 * Resets the values of the query
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function clear_values(){
		$this->values = array();
		return $this;
	}
	
	/**
	 * Returns the value query part specified by the given attribute alias
	 * @param unknown $attribute_alias
	 * @return QueryPartValue
	 */
	protected function get_value($attribute_alias){
		return $this->values[$attribute_alias];
	}
	
	/**
	 * Returns an array of value query parts with all value columns of this query.
	 * @return QueryPartValue[]
	 */
	protected function get_values(){
		return $this->values;
	}
	
	/**
	 * Returns an array of rows fetched. Each row is an associative array in turn
	 * with attribute_aliases for keys.
	 * @return array
	 */
	abstract function get_result_rows();
	
	/**
	 * Returns an array with totals: array[column][function]=[value]
	 * Multiple agregating functions can be used on each column.
	 * @return array
	 */
	abstract function get_result_totals();
	
	/**
	 * Returns the total number of rows found, regardless of the pagination
	 * @return int
	 */
	abstract function get_result_total_rows();
	
	public function get_workbench(){
		return $this->get_main_object()->get_model()->get_workbench();
	}
	
	/**
	 * Adds multiple query parts of any type to the query. Even mixed types are supported!
	 * @param QueryPart[] $qparts
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function add_query_parts($qparts){
		foreach ($qparts as $qpart){
			$this->add_query_part($qpart);	
		}
		return $this;
	}
	
	/**
	 * Adds a query part of any type to the query.
	 * @param QueryPart $qpart
	 * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
	 */
	public function add_query_part(QueryPart $qpart){
		if ($qpart instanceof QueryPartValue) {
			$this->values[$qpart->get_alias()] = $qpart;
		} elseif ($qpart instanceof QueryPartAttribute){
			$this->attributes[$qpart->get_alias()] = $qpart; 
		} // FIXME add all other query parts. Perhaps use this metho even in the regular add...() methods to centralize the population of the private arrays.
		return $this;
	}
	
	/**
	 * Sorts the given array of data rows by applying the sorters defined for this query. Returns the sorted array.
	 * 
	 * @param array $row_array
	 * @return array
	 */
	protected function apply_sorting($row_array){
		if (!is_array($row_array)){
			return $row_array;
		}
		$sorter = new RowDataArraySorter();
		foreach ($this->get_sorters() as $qpart){
			if (!$qpart->get_apply_after_reading()) continue;
			$sorter->addCriteria($qpart->get_alias(), $qpart->get_order());
		}
		return $sorter->sort($row_array);
	}
	
	/**
	 * Filters the given array of data rows by applying the filters defined for this query where 
	 * $query_part_filter->get_apply_after_reading() is TRUE. Returns the resulting array, that
	 * now only contains rows matching the filters
	 * 
	 * @param array $row_array
	 * @return array
	 */
	protected function apply_filters($row_array){
		if (!is_array($row_array)){
			return $row_array;
		}
		// Apply filters
		$row_filter = new RowDataArrayFilter();
		foreach ($this->get_filters()->get_filters() as $qpart){
			if (!$qpart->get_apply_after_reading()) continue;
			$row_filter->add_and($qpart->get_alias(), $qpart->get_compare_value(), $qpart->get_comparator());
		}
		return $row_filter->filter($row_array);
	}
	
	/**
	 * Applies the pagination limit and offset of this query to the given data array. The result only
	 * contains rows, that match the requested page.
	 * 
	 * @param array $row_array
	 * @return array
	 */
	protected function apply_pagination($row_array){
		if (!is_array($row_array) || !$this->get_limit()){
			return $row_array;
		}
		return array_slice($row_array, $this->get_offset(), $this->get_limit());
	}
}