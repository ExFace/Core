<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Relation;

class QueryPart
{

    protected $query = NULL;

    private $attribute = NULL;

    private $alias = NULL;

    function __construct($alias, AbstractQueryBuilder $query)
    {
        $this->setAlias($alias);
        $this->setQuery($query);
    }

    /**
     *
     * @return AbstractQueryBuilder
     */
    public function getQuery()
    {
        return $this->query;
    }

    public function setQuery(AbstractQueryBuilder $value)
    {
        $this->query = $value;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($value)
    {
        $this->alias = $value;
    }

    /**
     * Checks, if the qpart is meaningfull.
     * What exactly is checked depends on the type of the query part
     * (i.e. a select will need a select statement at the attribute, etc.)
     * 
     * @return boolean
     */
    public function isValid()
    {
        return true;
    }

    /**
     * Returns an array of relations used in this query part.
     * If $relation_type is given, only returns relations of this type.
     * 
     * @param string $relations_type            
     * @return Relation[]
     */
    public function getUsedRelations($relation_type = null)
    {
        return array();
    }

    /**
     * Returns the first relation of the given type or false if no relations of this type is found.
     * If $relation_type is ommitted, returns the very first relation regardless of it's type.
     * 
     * @param string $relations_type            
     * @return Relation
     */
    public function getFirstRelation($relations_type = null)
    {
        $rels = $this->getUsedRelations($relations_type);
        $rel = reset($rels);
        return $rel;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     *
     * @param \exface\Core\CommonLogic\Model\Attribute $value            
     */
    public function setAttribute(Attribute $value)
    {
        $this->attribute = $value;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function getWorkbench()
    {
        return $this->getQuery()->getWorkbench();
    }
}
?>