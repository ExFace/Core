<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\Model\MetaObjectDataConnectionNotFoundError;

abstract class AbstractQueryBuilder
{

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
     * Performs a create query.
     * Returns the number of successfully created rows.
     *
     * @param string $data_connection            
     * @return int
     */
    function create(AbstractDataConnector $data_connection = null)
    {
        throw new QueryBuilderException('Create method not implemented in "' . get_class($this) . '"!');
    }

    /**
     * Performs a read query.
     * Returns the number of read rows.
     *
     * @param string $data_connection            
     * @return int
     */
    function read(AbstractDataConnector $data_connection = null)
    {
        throw new QueryBuilderException('Read method not implemented in "' . get_class($this) . '"!');
    }

    /**
     * Performs an update query.
     * Returns the number of successfully updated rows.
     *
     * @param string $data_connection            
     * @return int
     */
    function update(AbstractDataConnector $data_connection = null)
    {
        throw new QueryBuilderException('Update method not implemented in "' . get_class($this) . '"!');
    }

    /**
     * Performs a delete query.
     * Returns the number of deleted rows.
     *
     * @param string $data_connection            
     * @return int
     */
    function delete(AbstractDataConnector $data_connection = null)
    {
        throw new QueryBuilderException('Delete method not implemented in "' . get_class($this) . '"!');
    }

    /**
     * Set the main object for the query
     *
     * @param \exface\Core\CommonLogic\Model\Object $meta_object            
     * @throws MetaObjectDataConnectionNotFoundError if the data connection for the object cannot be established
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function setMainObject(\exface\Core\CommonLogic\Model\Object $meta_object)
    {
        $this->main_object = $meta_object;
        // Instantiate the data connection for the object here to make sure, all it's settings, contexts, etc. are applied before the query is built!
        if (! $meta_object->getDataConnection()) {
            throw new MetaObjectDataConnectionNotFoundError('Cannot setup data connection for object "' . $meta_object->getAliasWithNamespace() . '"!');
        }
        return $this;
    }

    /**
     * Returns the main meta object of the query
     *
     * @return \exface\Core\CommonLogic\Model\Object
     */
    public function getMainObject()
    {
        return $this->main_object;
    }

    /**
     * Adds an attribute to be fetched by the query
     *
     * @param string $attribute_alias            
     */
    public function addAttribute($alias)
    {
        $qpart = new QueryPartSelect($alias, $this);
        if ($qpart->isValid()) {
            $this->attributes[$alias] = $qpart;
        }
        return $qpart;
    }

    /**
     *
     * @return QueryPartAttribute[]
     */
    protected function getAttributes()
    {
        return $this->attributes;
    }

    /**
     *
     * @param string $alias            
     * @return QueryPartSelect
     */
    protected function getAttribute($alias)
    {
        return $this->attributes[$alias];
    }

    /**
     * Adds a total row to the query (i.e.
     * for the footers)
     *
     * @param string $attribute
     *            attribute_alias
     * @param string $function
     *            like SUM, AVG, etc.
     * @param int $place_in_row
     *            row number within a multi-row footer for this total
     */
    public function addTotal($attribute, $function, $place_in_row = 0)
    {
        $qpart = new QueryPartTotal($attribute, $this);
        $qpart->setAlias($attribute);
        $qpart->setFunction($function);
        $qpart->setRow($place_in_row);
        $this->totals[] = $qpart;
        return $qpart;
    }

    protected function getTotals()
    {
        return $this->totals;
    }

    /**
     * Creates and adds a single filter to the query
     *
     * @param unknown $attribute_alias            
     * @param unknown $value            
     * @param string $comparator            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function addFilterFromString($attribute_alias, $value, $comparator = EXF_COMPARATOR_IS)
    {
        $exface = $this->getWorkbench();
        $condition = ConditionFactory::createFromExpression($exface, $this->getWorkbench()->model()->parseExpression($attribute_alias, $this->getMainObject()), $value, $comparator);
        return $this->addFilterCondition($condition);
    }

    /**
     * Replaces all filters of the query by the given condition group.
     *
     * @param ConditionGroup $filters            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function setFiltersConditionGroup(ConditionGroup $condition_group)
    {
        $this->clearFilters();
        $this->filters = $this->getFilters()->createQueryPartFromConditionGroup($condition_group);
        return $this;
    }

    /**
     * Replaces all filters of the query by the given filter group
     *
     * @param QueryPartFilterGroup $filter_group            
     */
    protected function setFilters(QueryPartFilterGroup $filter_group)
    {
        $this->filters = $filter_group;
        return $this;
    }

    /**
     * Adds a condition group to the first level of filters
     *
     * @param ConditionGroup $condition_group            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function addFilterConditionGroup(ConditionGroup $condition_group)
    {
        $this->getFilters()->addConditionGroup($condition_group);
        return $this;
    }

    /**
     * Adds a filter group query part to the first level of filters
     *
     * @param QueryPartFilterGroup $filter_group            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    protected function addFilterGroup(QueryPartFilterGroup $filter_group)
    {
        $this->getFilters()->addNestedGroup($filter_group);
        return $this;
    }

    /**
     * Adds a first level condition to the root filter group
     *
     * @param Condition $condition            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function addFilterCondition(Condition $condition)
    {
        $this->getFilters()->addCondition($condition);
        return $this;
    }

    /**
     * Adds a filter query part to the first level of filters
     *
     * @param QueryPartFilter $filter            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    protected function addFilter(QueryPartFilter $filter)
    {
        $this->getFilters()->addFilter($filter);
        return $this;
    }

    /**
     * Returns the root filter group.
     *
     * @return QueryPartFilterGroup
     */
    protected function getFilters()
    {
        if (! $this->filters)
            $this->filters = new QueryPartFilterGroup('', $this);
        return $this->filters;
    }

    /**
     * Returns a filter query part with the given alias
     *
     * @return QueryPartFilter
     */
    protected function getFilter($alias)
    {
        return $this->getFilters()->findFilterByAlias($alias);
    }

    /**
     * Removes all filters from the query
     *
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function clearFilters()
    {
        unset($this->filters);
        return $this;
    }

    /**
     * Adds a sorter to the query.
     * Multiple sorters can be added sequentially.
     *
     * @param string $sort_by
     *            attribute_alias
     * @param string $order            
     */
    public function addSorter($sort_by, $order = 'ASC')
    {
        $qpart = new QueryPartSorter($sort_by, $this);
        $qpart->setOrder($order);
        $this->sorters[$sort_by . $order] = $qpart;
        // IDEA move this to the read method of the concrete builder, since it might not be neccessary for
        // all data sources.
        $this->addAttribute($sort_by);
        return $qpart;
    }

    /**
     * Returns an array of sorter query parts
     *
     * @return QueryPartSorter[]
     */
    protected function getSorters()
    {
        return $this->sorters;
    }

    /**
     * Addes a an attribute to aggregate over (= group by for SQL builders)
     *
     * @param string $attribute_alias            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute
     */
    public function addAggregation($attribute_alias)
    {
        $qpart = new QueryPartAttribute($attribute_alias, $this);
        $this->aggregations[$attribute_alias] = $qpart;
        // IDEA move this to the build_sql_query methods since we probably do not always need to add the attribute
        $this->addAttribute($attribute_alias);
        return $qpart;
    }

    /**
     * Returns an array of attribute query parts, that are to be used for aggregation
     *
     * @return QueryPartAttribute[]
     */
    protected function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     *
     * @param string $alias            
     * @return QueryPartAttribute
     */
    protected function getAggregation($alias)
    {
        return $this->aggregations[$alias];
    }

    /**
     * Sets pagination for the query (i.e.
     * get $limit lines starting from line number $offset)
     *
     * @param number $limit            
     * @param number $offset            
     */
    public function setLimit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
    }

    /**
     *
     * @return number
     */
    protected function getLimit()
    {
        return $this->limit;
    }

    /**
     *
     * @return number
     */
    protected function getOffset()
    {
        return $this->offset;
    }

    /**
     * Adds a value column with a single row
     *
     * @param string $attribute_alias            
     * @param string $value            
     * @return QueryPartValue
     */
    public function addValue($attribute_alias, $value)
    {
        $qpart = new QueryPartValue($attribute_alias, $this);
        $qpart->setValue($value);
        $this->values[$attribute_alias] = $qpart;
        return $qpart;
    }

    /**
     * Adds a value column with multiple rows (in other words multiple values for a single database column).
     * The values
     * are passed as an array with row ids as keys. What column is meant by "row id" can optionally be specified via the
     * $row_id_attribute_alias parameter. If not set, the UID column of the main object of the query will be used.
     *
     * @param string $attribute_alias            
     * @param array $values
     *            [ row_id_attribute_alias_value => value_to_be_saved ]
     * @param array $uids_for_values            
     * @return QueryPartValue
     */
    public function addValues($attribute_alias, array $values, array $uids_for_values = array())
    {
        $qpart = new QueryPartValue($attribute_alias, $this);
        $qpart->setValues($values);
        $qpart->setUids($uids_for_values);
        $this->values[$attribute_alias] = $qpart;
        return $qpart;
    }

    /**
     * Resets the values of the query
     *
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function clearValues()
    {
        $this->values = array();
        return $this;
    }

    /**
     * Returns the value query part specified by the given attribute alias
     *
     * @param unknown $attribute_alias            
     * @return QueryPartValue
     */
    protected function getValue($attribute_alias)
    {
        return $this->values[$attribute_alias];
    }

    /**
     * Returns an array of value query parts with all value columns of this query.
     *
     * @return QueryPartValue[]
     */
    protected function getValues()
    {
        return $this->values;
    }

    /**
     * Returns an array of rows fetched.
     * Each row is an associative array in turn
     * with attribute_aliases for keys.
     *
     * @return array
     */
    abstract function getResultRows();

    /**
     * Returns an array with totals: array[column][function]=[value]
     * Multiple agregating functions can be used on each column.
     *
     * @return array
     */
    abstract function getResultTotals();

    /**
     * Returns the total number of rows found, regardless of the pagination
     *
     * @return int
     */
    abstract function getResultTotalRows();

    public function getWorkbench()
    {
        return $this->getMainObject()->getModel()->getWorkbench();
    }

    /**
     * Adds multiple query parts of any type to the query.
     * Even mixed types are supported!
     *
     * @param QueryPart[] $qparts            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function addQueryParts($qparts)
    {
        foreach ($qparts as $qpart) {
            $this->addQueryPart($qpart);
        }
        return $this;
    }

    /**
     * Adds a query part of any type to the query.
     *
     * @param QueryPart $qpart            
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function addQueryPart(QueryPart $qpart)
    {
        if ($qpart instanceof QueryPartValue) {
            $this->values[$qpart->getAlias()] = $qpart;
        } elseif ($qpart instanceof QueryPartAttribute) {
            $this->attributes[$qpart->getAlias()] = $qpart;
        } // FIXME add all other query parts. Perhaps use this metho even in the regular add...() methods to centralize the population of the private arrays.
        return $this;
    }

    /**
     * Sorts the given array of data rows by applying the sorters defined for this query.
     * Returns the sorted array.
     *
     * @param array $row_array            
     * @return array
     */
    protected function applySorting($row_array)
    {
        if (! is_array($row_array)) {
            return $row_array;
        }
        $sorter = new RowDataArraySorter();
        foreach ($this->getSorters() as $qpart) {
            // Do not sort if the attribute sorted by is unsortable
            if ($qpart->getAttribute() && ! $qpart->getAttribute()->isSortable())
                continue;
            // Do not sort if already sorted (remote sort)
            if (! $qpart->getApplyAfterReading())
                continue;
            $sorter->addCriteria($qpart->getAlias(), $qpart->getOrder());
        }
        return $sorter->sort($row_array);
    }

    /**
     * Filters the given array of data rows by applying the filters defined for this query where
     * $query_part_filter->getApplyAfterReading() is TRUE.
     * Returns the resulting array, that
     * now only contains rows matching the filters
     *
     * @param array $row_array            
     * @return array
     */
    protected function applyFilters($row_array)
    {
        if (! is_array($row_array)) {
            return $row_array;
        }
        // Apply filters
        $row_filter = new RowDataArrayFilter();
        foreach ($this->getFilters()->getFilters() as $qpart) {
            // Do not filter if the attribute to filter over is unfilterable
            if ($qpart->getAttribute() && ! $qpart->getAttribute()->isFilterable())
                continue;
            // Do not filter if already filtered (remotely)
            if (! $qpart->getApplyAfterReading() || ! $qpart->getCompareValue())
                continue;
            $row_filter->addAnd($qpart->getAlias(), $qpart->getCompareValue(), $qpart->getComparator(), $qpart->getValueListDelimiter());
        }
        return $row_filter->filter($row_array);
    }

    /**
     * Applies the pagination limit and offset of this query to the given data array.
     * The result only
     * contains rows, that match the requested page.
     *
     * @param array $row_array            
     * @return array
     */
    protected function applyPagination($row_array)
    {
        if (! is_array($row_array) || ! $this->getLimit()) {
            return $row_array;
        }
        return array_slice($row_array, $this->getOffset(), $this->getLimit());
    }
}