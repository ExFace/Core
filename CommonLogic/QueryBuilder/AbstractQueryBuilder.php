<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\Model\MetaObjectDataConnectionNotFoundError;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Interfaces\QueryBuilderInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\Templates\Modifiers\IfNullModifier;
use exface\Core\Uxon\QueryBuilderSchema;
use exface\Core\Factories\ExpressionFactory;

abstract class AbstractQueryBuilder implements QueryBuilderInterface
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
    
    private $selector = null;
    
    private $workbench = null;
    
    private $timeZone = null;
    
    public function __construct(QueryBuilderSelectorInterface $selector)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::getSelector()
     */
    public function getSelector() : QueryBuilderSelectorInterface
    {
        return $this->selector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::create()
     */
    function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        throw new QueryBuilderException('Create method not implemented in "' . get_class($this) . '"!');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::read()
     */
    function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        throw new QueryBuilderException('READ not implemented in "' . get_class($this) . '"!');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::update()
     */
    function update(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        throw new QueryBuilderException('UPDATE not implemented in "' . get_class($this) . '"!');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::delete()
     */
    function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        throw new QueryBuilderException('DELETE not implemented in "' . get_class($this) . '"!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::count()
     */
    function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        throw new QueryBuilderException('COUNT operation not implemented in "' . get_class($this) . '"!');
    }

    /**
     * Set the main object for the query
     *
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $meta_object            
     * @throws MetaObjectDataConnectionNotFoundError if the data connection for the object cannot be established
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function setMainObject(\exface\Core\Interfaces\Model\MetaObjectInterface $meta_object)
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
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMainObject() : MetaObjectInterface
    {
        return $this->main_object;
    }

    /**
     * Adds an attribute to be fetched by the query
     *
     * @param string $attribute_alias 
     * @return QueryPartAttribute           
     */
    public function addAttribute(string $attribute_alias, string $column_name = null) : QueryPartSelect
    {
        $qpart = new QueryPartSelect($attribute_alias, $this, null, $column_name);
        if ($qpart->isValid()) {
            $this->addQueryPart($qpart);
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
        return $this->attributes[$alias] ?? null;
    }
    
    /**
     * Returns the query part for the UID attribute of the main object if present in the query and NULL otherwies.
     * 
     * @return QueryPartAttribute|NULL
     */
    protected function getUidAttribute() : ?QueryPartAttribute
    {
        if ($this->getMainObject()->hasUidAttribute()) {
            $uidAttr = $this->getMainObject()->getUidAttribute();
            foreach ($this->getAttributes() as $qpart) {
                if ($qpart->getAttribute()->isExactly($uidAttr)) {
                    return $qpart;
                }
            }
        }
        return null;
    }

    /**
     * Adds a total row to the query (i.e.
     * for the footers)
     *
     * @param string $attribute_alias
     * @param AggregatorInterface $aggregator
     * @param int $place_in_row
     *            row number within a multi-row footer for this total
     */
    public function addTotal($attribute_alias, AggregatorInterface $aggregator, $place_in_row = 0)
    {
        $qpart = new QueryPartTotal($attribute_alias, $this);
        $qpart->setAlias($attribute_alias);
        $qpart->setTotalAggregator($aggregator);
        $qpart->setRow($place_in_row);
        $this->totals[] = $qpart;
        return $qpart;
    }
    
    protected function hasTotals() : bool
    {
        return empty($this->totals) === false;
    }

    protected function getTotals()
    {
        return $this->totals;
    }

    /**
     * Creates and adds a single filter to the query
     *
     * @param string $attribute_alias            
     * @param string $value            
     * @param string $comparator            
     * @return QueryPartFilter
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
        $this->filters = QueryPartFilterGroup::createQueryPartFromConditionGroup($condition_group, $this);
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
     * Adds a condition group to the first level of filters and returns the resulting query part.
     *
     * @param ConditionGroup $condition_group            
     * @return QueryPartFilterGroup
     */
    public function addFilterConditionGroup(ConditionGroup $condition_group)
    {
        return $this->getFilters()->addConditionGroup($condition_group);
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
     * Adds a first level condition to the root filter group and returns the resulting query part
     *
     * @param Condition $condition            
     * @return QueryPartFilter
     */
    public function addFilterCondition(Condition $condition)
    {
        return $this->getFilters()->addCondition($condition);
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
     * @param string $alias
     * @return QueryPartFilter|null
     */
    protected function getFilter(string $alias) : ?QueryPartFilter
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
     * 
     * @return QueryPartSorter
     */
    public function addSorter($sort_by, $order = 'ASC', bool $addToAttributes = true)
    {
        $qpart = new QueryPartSorter($sort_by, $this);
        $qpart->setOrder($order);
        $this->sorters[$sort_by . $order] = $qpart;
        // IDEA move this to the read method of the concrete builder, since it might not be neccessary for
        // all data sources.
        if ($addToAttributes === true) {
            $this->addAttribute($sort_by);
        }
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
        $this->addQueryPart($qpart);
        return $qpart;
    }

    /**
     * Adds a value column with multiple rows (in other words multiple values for a single database column).
     * 
     * The values are passed as an array with row indexes as keys. This means, every attribute
     * in a query may have values on different rows. This is important as an update may contain
     * rows, that simply don't have a value for a certain data column, which does not mean, 
     * it should be emptied in the data source!
     * 
     * The third argument is an optional array containing UIDs for rows to be updated.
     * Only knowing the UIDs allows us to be sure to update the right item in the data source!
     * If no UIDs are provided, the query builder will attempt to do an update by filters.
     * Note: The number of items in the values and uids array MUST be equal!  
     *
     * @param string $attribute_alias            
     * @param array $values [ row_index => value_to_be_saved ]
     * @param array $uids_for_values  [ row_index => row_uid ]  
     * @return QueryPartValue
     */
    public function addValues($attribute_alias, array $values, array $uids_for_values = [])
    {
        $qpart = new QueryPartValue($attribute_alias, $this);
        if (empty ($values)) {
            throw new QueryBuilderException("Empty set of values passed for attribute \"{$attribute_alias}\" for an update of " . $this->getMainObject()->__toString());
        }
        if (! empty($uids_for_values) && count($values) !== count($uids_for_values)) {
            throw new QueryBuilderException("Cannot determine UIDs for values of attribute \"{$attribute_alias}\" for an update of {$this->getMainObject()->__toString()}: got " . count($values) . ' values and ' . count($uids_for_values) . ' UIDs');
        }
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
     * @param mixed $attribute_alias            
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

    public function getWorkbench()
    {
        return $this->workbench;
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
        switch (true) {
            case $qpart instanceof QueryPartValue:
                $this->values[$qpart->getAlias()] = $qpart;
                break;
            case $qpart instanceof QueryPartAttribute:
                $columnKey = $qpart instanceof QueryPartSelect ? $qpart->getColumnKey() : $qpart->getAlias();
                $this->attributes[$columnKey] = $qpart;
                break;
            default:
                // FIXME add all other query parts. Perhaps use this metho even in the regular add...() methods to centralize the population of the private arrays.
                throw new NotImplementedError('Adding ready-made query parts to existing queries not supported for ' . get_class($qpart));
        } 
        return $this;
    }
    
    /**
     * 
     * @param QueryPart $qpart
     * @throws NotImplementedError
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    protected function removeQueryPart(QueryPart $qpart) 
    {
        switch (true) {
            case $qpart instanceof QueryPartValue:
                unset($this->values[$qpart->getAlias()]);
                break;
            case $qpart instanceof QueryPartAttribute:
                $columnKey = $qpart instanceof QueryPartSelect ? $qpart->getColumnKey() : $qpart->getAlias();
                unset($this->attributes[$columnKey]);
                break;
            default:
                // FIXME add all other query parts. Perhaps use this metho even in the regular add...() methods to centralize the population of the private arrays.
                throw new NotImplementedError('Removing ready-made query parts to existing queries not supported for ' . get_class($qpart));
        }
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
        
        return $this->getFilters()->applyTo($row_array, true);
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
    
    /**
     *
     * @param mixed[][] $rows
     * @param QueryPartAttribute[] $byAttributeQueryParts
     * @return mixed[][]
     */
    protected function applyAggregations(array $rows, array $byAttributeQueryParts) : array
    {
        if (empty($byAttributeQueryParts)) {
            return $rows;
        }
        $resultRows = [];
        $rowsPerKey = [];
        foreach ($rows as $row) {
            $key = '';
            foreach ($byAttributeQueryParts as $qpart) {
                $key .= $row[$qpart->getColumnKey()];
            }
            $rowsPerKey[$key][] = $row;
        }
        foreach ($rowsPerKey as $rowsWithKey) {
            $resultRow = [];
            foreach ($this->getAttributes() as $qpart) {
                $key = $qpart->getColumnKey();
                switch (true) {
                    case $this->isAggregatedBy($qpart):
                        $resultRow[$key] = $rowsWithKey[0][$key];
                        break;
                    case $qpart->hasAggregator():
                        $vals = [];
                        foreach ($rowsWithKey as $r) {
                            $vals[] = $r[$key];
                        }
                        $resultRow[$key] = ArrayDataType::aggregateValues($vals, $qpart->getAggregator());
                }
            }
            $resultRows[] = $resultRow;
        }
        return $resultRows;
    }
    
    protected function replacePlaceholdersByFilterValues($string)
    {
        foreach (StringDataType::findPlaceholders($string) as $ph) {
            $defaultValue = null;
            $phAlias = IfNullModifier::stripFilter($ph);
            if ($phAlias !== $ph) {
                $defaultValue = IfNullModifier::findDefaultValue($ph);
            }
            switch (true) {
                // Formula placeholder
                case StringDataType::startsWith($phAlias, '='):
                    $expr = ExpressionFactory::createFromString($this->getWorkbench(), $phAlias);
                    if (! $expr->isFormula() && ! $expr->isStatic()) {
                        throw new QueryBuilderException('Only static formulas can be used as placeholder in "' . $string . '"! Placeholder "' . $ph . '" is not a static formula!');
                    }
                    $phVal = $expr->evaluate() ?? $defaultValue;
                    $string = str_replace('[#' . $ph . '#]', $phVal, $string);
                    break;
                // Existing filters
                case $phFilter = $this->getFilter($phAlias):
                    $phVal = $phFilter->getCompareValue();
                    switch (true) {
                        // Filter value - parse according to data type of the filter
                        case $phVal !== null:
                            $phVal = $phFilter->getDataType()->parse($phVal);
                            break;
                        // Default value - no parsing, take the default as-is
                        case $defaultValue !== null:
                            $phVal = $defaultValue;
                            break;
                        // If at least one filter does not have a value, return false
                        default:
                            throw new QueryBuilderException('Missing filter value in "' . $phFilter->getAlias() . '" needed for placeholder "' . $ph . '" in data address "' . $string . '"!');
                    }
                    $string = str_replace('[#' . $ph . '#]', $phVal, $string);
                    break;
                // Missing filters with defaults
                case $defaultValue !== null:
                    $string = str_replace('[#' . $ph . '#]', $defaultValue, $string);
                    break;
                // Exit with error if at least one placeholder has neither of the above
                default:
                    throw new QueryBuilderException('Missing filter for placeholder "' . $ph . '" in SQL "' . $string . '"!');
            }
        }
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::canReadAttribute()
     */
    abstract public function canReadAttribute(MetaAttributeInterface $attribute) : bool;
    
    /**
     * 
     * @param string $modelAliasExpression
     * @return bool
     */
    public function canRead(string $modelAliasExpression) : bool
    {
        return $this->canReadAttribute($this->getMainObject()->getAttribute($modelAliasExpression));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::canWriteAttribute()
     */
    public function canWriteAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $this->canReadAttribute($attribute);
    }
    
    /**
     * Returns TRUE If the query is aggregated by the given query part or another one with the same data address.
     * 
     * @param QueryPartAttribute $qpart
     * @return bool
     */
    protected function isAggregatedBy(QueryPartAttribute $qpart) : bool
    {
        foreach ($this->getAggregations() as $qpartAggr) {
            if ($qpartAggr->getAlias() === $qpart->getAlias()) {
                return true;
            }
            if ($qpartAggr->getDataAddress() === $qpart->getDataAddress()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass() : ?string
    {
        return QueryBuilderSchema::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::getTimeZone()
     */
    public function getTimeZone() : ?string
    {
        return $this->timeZone;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::setTimeZone()
     */
    public function setTimeZone(string $value = null) : QueryBuilderInterface
    {
        $this->timeZone = $value;
        return $this;
    }
}