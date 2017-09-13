<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Interfaces\Model\AggregatorInterface;

class QueryPartAttribute extends QueryPart
{

    private $aggregator;

    private $used_relations = null;

    function __construct($alias, AbstractQueryBuilder $query)
    {
        parent::__construct($alias, $query);
        
        if (! $attr = $query->getMainObject()->getAttribute($alias)) {
            throw new QueryBuilderException('Attribute "' . $alias . '" of object "' . $query->getMainObject()->getAlias() . '" not found!');
        } else {
            $this->setAttribute($attr);
        }
        
        if ($aggr = DataAggregation::getAggregatorFromAlias($alias)){
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
        // first check the cache
        if (is_array($this->used_relations)) {
            $rels = $this->used_relations;
        } else {
            // fetch relations
            // first make sure, the attribute has a relation path (otherwise we do not need to to anything
            if ($this->getAttribute()->getRelationPath()->toString()) {
                // split the path in case it contains multiple relations
                $rel_aliases = RelationPath::relationPathParse($this->getAttribute()->getRelationPath()->toString());
                // if it is one relation only, use it
                if (! $rel_aliases && $this->getAttribute()->getRelationPath()->toString())
                    $rel_aliases[] = $this->getAttribute()->getRelationPath()->toString();
                // iterate through the found relations
                if ($rel_aliases) {
                    $last_alias = '';
                    foreach ($rel_aliases as $alias) {
                        $rels[$last_alias . $alias] = $this->getQuery()->getMainObject()->getRelation($last_alias . $alias);
                        $last_alias .= $alias . RelationPath::getRelationSeparator();
                    }
                }
            }
            // cache the result
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

    public function getDataAddressProperty($property_key)
    {
        return $this->getAttribute()->getDataAddressProperty($property_key);
    }

    public function setDataAddressProperty($property_key, $value)
    {
        return $this->getAttribute()->setDataAddressProperty($property_key, $value);
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
        return $this->getWorkbench()->model()->parseExpression($this->getAlias(), $this->getQuery()->getMainObject());
    }

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
}
?>