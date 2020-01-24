<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\DataSheets\DataColumn;

class QueryPartAttribute extends QueryPart
{

    private $aggregator;

    private $used_relations = null;
    
    private $dataAddressProperties = [];

    function __construct($alias, AbstractQueryBuilder $query)
    {
        parent::__construct($alias, $query);
        
        if (! $attr = $query->getMainObject()->getAttribute($alias)) {
            throw new QueryBuilderException('Attribute "' . $alias . '" of object "' . $query->getMainObject()->getAlias() . '" not found!');
        } else {
            $this->setAttribute($attr);
        }
        
        if ($aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $alias)){
            $this->aggregator = $aggr;
        }
    }

    /**
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\QueryPart::getUsedRelations()
     */
    public function getUsedRelations($relation_type = null)
    {
        $rels = array();
        
        if (is_array($this->used_relations)) {
            $rels = $this->used_relations;
        } else {            
            $last_alias = '';
            foreach ($this->getAttribute()->getRelationPath()->getRelations() as $rel) {
                $rels[$last_alias . $rel->getAliasWithModifier()] = $rel;
                $last_alias .= $rel->getAliasWithModifier() . RelationPath::getRelationSeparator();
            }
            $this->used_relations = $rels;
        }
        
        // if looking for a specific relation type, remove all the others
        if ($relation_type) {
            foreach ($rels as $alias => $rel) {
                if ($rel->getType() != $relation_type) {
                    unset($rels[$alias]);
                }
            }
        }
        
        return $rels;
    }

    /**
     * Returns the aggregator used to calculate values in this query part.
     * 
     * E.g. for POSITION__VALUE:SUM it would return SUM (in the form of an
     * instantiated aggregator).
     * 
     * @return AggregatorInterface
     */
    public function getAggregator()
    {
        return $this->aggregator;
    }

    public function setAggregator(AggregatorInterface $value)
    {
        $this->aggregator = $value;
    }

    /**
     * Returns the value of the given data address property.
     * 
     * If the property was not explicitly overridden for this
     * query part, the value will be retrieved from the underlying
     * attribute.
     * 
     * @param string $property_key
     * @return mixed
     */
    public function getDataAddressProperty(string $property_key)
    {
        $ucKey = mb_strtoupper($property_key);
        if (array_key_exists($ucKey, $this->dataAddressProperties)) {
            return $this->dataAddressProperties[$ucKey];
        }
        return $this->getAttribute()->getDataAddressProperty($property_key);
    }

    /**
     * Overrides a data address property for this query part (not affecting the underlying attribute).
     * 
     * @param string $property_key
     * @param mixed $value
     * @return QueryPartAttribute
     */
    public function setDataAddressProperty(string $property_key, $value) : QueryPartAttribute
    {
        $this->dataAddressProperties[mb_strtoupper($property_key)] = $value;
        return $this;
    }

    /**
     * Returns the data source specific address of the attribute represented by this query part.
     * Depending
     * on the data source, this can be an SQL column name, a file name, etc.
     *
     * @return string
     */
    public function getDataAddress()
    {
        return $this->getAttribute()->getDataAddress();
    }
    
    /**
     * Returns the data type of this query part
     * 
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public function getDataType()
    {
        return $this->getExpression()->getDataType();
    }

    public function getMetaModel()
    {
        return $this->getAttribute()->getModel();
    }

    /**
     * Parses the alias of this query part as an ExFace expression and returns the expression object
     *
     * @return \exface\Core\Interfaces\Model\ExpressionInterface
     */
    public function getExpression()
    {
        return ExpressionFactory::createFromString($this->getWorkbench(), $this->getAlias(), $this->getQuery()->getMainObject());
    }

    /**
     * 
     * @param AbstractQueryBuilder $new_query
     * @param string $relation_path_to_new_base_object
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute
     */
    public function rebase(AbstractQueryBuilder $new_query, $relation_path_to_new_base_object)
    {
        $qpart = clone $this;
        $qpart->setQuery($new_query);
        $new_expression = $this->getExpression()->rebase($relation_path_to_new_base_object);
        $qpart->used_relations = array();
        $qpart->setAttribute($new_expression->getAttribute());
        $qpart->setAlias($new_expression->toString());
        return $qpart;
    }
    
    /**
     * Returns the key of the column in the query results, that this query part would produce.
     * 
     * @return string
     */
    public function getColumnKey() : string
    {
        return DataColumn::sanitizeColumnName($this->getAlias());
    }
}
?>