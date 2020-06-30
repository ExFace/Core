<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\QueryBuilderInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\QueryBuilder\QueryPart;
use exface\Core\CommonLogic\QueryBuilder\QueryPartValue;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSelect;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\Model\MetaObjectDataConnectionNotFoundError;
use exface\Core\Interfaces\Model\AggregatorInterface;

/**
 * This query builder represents whatever query builder is configured for the model data source.
 * 
 * Which query builder it actually represents is determined by the configuration in
 * the system config (`System.config.json`) in `METAMODEL.QUERY_BUILDER`. 
 * 
 * @author Andrej Kabachnik
 *        
 */
class ModelLoaderQueryBuilder implements QueryBuilderInterface
{
    /**
     * 
     * @var AbstractQueryBuilder
     */
    private $qb = null;
    
    private $selector = null;
    
    private $workbench = null;
    
    public function __construct(QueryBuilderSelectorInterface $selector)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
        $this->qb = QueryBuilderFactory::createModelLoaderQueryBuilder($selector->getWorkbench());
    }
    
    /**
     * 
     * @return QueryBuilderInterface
     */
    public function getModelQueryBuilder() : QueryBuilderInterface
    {
        return $this->qb;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\DummyQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $this->qb->canReadAttribute($attribute);
    }
    
    /**
     *
     * @param string $modelAliasExpression
     * @return bool
     */
    public function canRead(string $modelAliasExpression) : bool
    {
        return $this->qb->canRead($modelAliasExpression);
    }
    
    /**
    *
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\QueryBuilderInterface::create()
    */
    public function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->qb->create($data_connection);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->qb->read($data_connection);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::update()
     */
    public function update(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->qb->update($data_connection);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::delete()
     */
    public function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->qb->delete($data_connection);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::count()
     */
    function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->qb->count($data_connection);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\QueryBuilderInterface::getSelector()
     */
    public function getSelector(): QueryBuilderSelectorInterface
    {
        return $this->selector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * Set the main object for the query
     *
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $meta_object
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function setMainObject(\exface\Core\Interfaces\Model\MetaObjectInterface $meta_object)
    {
        $this->qb->setMainObject($meta_object);
        return $this;
    }
    
    /**
     * Returns the main meta object of the query
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMainObject() : MetaObjectInterface
    {
        return $this->qb->getMainObject();
    }
    
    /**
     * Adds an attribute to be fetched by the query
     *
     * @param string $attribute_alias
     * @return QueryPartSelect
     */
    public function addAttribute(string $attribute_alias, string $column_name = null) : QueryPartSelect
    {
        return $this->qb->addAttribute($attribute_alias, $column_name);
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
        return $this->qb->addTotal($attribute_alias, $aggregator, $place_in_row);
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
        return $this->qb->addFilterFromString($attribute_alias, $value, $comparator);
    }
    
    /**
     * Replaces all filters of the query by the given condition group.
     *
     * @param ConditionGroup $filters
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function setFiltersConditionGroup(ConditionGroup $condition_group)
    {
        return $this->qb->setFiltersConditionGroup($condition_group);
    }
    
    /**
     * Adds a condition group to the first level of filters and returns the resulting query part.
     *
     * @param ConditionGroup $condition_group
     * @return QueryPartFilterGroup
     */
    public function addFilterConditionGroup(ConditionGroup $condition_group)
    {
        return $this->qb->addFilterConditionGroup($condition_group);
    }
    
    /**
     * Adds a first level condition to the root filter group and returns the resulting query part
     *
     * @param Condition $condition
     * @return QueryPartFilter
     */
    public function addFilterCondition(Condition $condition)
    {
        return $this->qb->addFilterCondition($condition);
    }
    
    /**
     * Removes all filters from the query
     *
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function clearFilters()
    {
        $this->qb->clearFilters();
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
    public function addSorter($sort_by, $order = 'ASC')
    {
        return $this->qb->addSorter($sort_by, $order);
    }
    
    /**
     * Addes a an attribute to aggregate over (= group by for SQL builders)
     *
     * @param string $attribute_alias
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute
     */
    public function addAggregation($attribute_alias)
    {
        return $this->qb->addAggregation($attribute_alias);
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
        $this->qb->setLimit($limit, $offset);
        return $this;
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
        return $this->qb->addValue($attribute_alias, $value);
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
        return $this->qb->addValues($attribute_alias, $values, $uids_for_values);
    }
    
    /**
     * Resets the values of the query
     *
     * @return \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder
     */
    public function clearValues()
    {
        $this->qb->clearValues();
        return $this;
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
        $this->qb->addQueryParts($qparts);
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
        $this->qb->addQueryPart($qpart);
        return $this;
    }
}