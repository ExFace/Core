<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Exceptions\RuntimeException;

class QueryPart
{

    protected $query = NULL;

    private $attribute = NULL;

    private $alias = NULL;
    
    private $parentQueryPart = null;
    
    private $children = [];

    function __construct($alias, AbstractQueryBuilder $query, QueryPart $parentQueryPart = null)
    {
        $this->setAlias($alias);
        $this->setQuery($query);
        $this->parentQueryPart = $parentQueryPart;
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
     * Returns an array of relations [alias_with_relation_path => Relation] used in this query part optionally filtered by type.
     * 
     * If $relation_type is given, only returns relations of this type.
     *
     * @param string $relations_type            
     * @return MetaRelationInterface[]
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
     * @return MetaRelationInterface
     */
    public function getFirstRelation($relations_type = null)
    {
        $rels = $this->getUsedRelations($relations_type);
        $rel = reset($rels);
        return $rel;
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     *
     * @param \exface\Core\Interfaces\Model\MetaAttributeInterface $value            
     */
    public function setAttribute(MetaAttributeInterface $value)
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
    
    public function getParentQueryPart() : ?QueryPart
    {
        return $this->parentQueryPart;
    }
    
    public function isCompound() : bool
    {
        return empty($this->children) === false;
    }
    
    /**
     * 
     * @return QueryPart[]
     */
    public function getCompoundChildren() : array
    {
        return $this->children;
    }
    
    public function hasParent() : bool
    {
        return $this->parentQueryPart !== null;
    }
    
    protected function addChildQueryPart(QueryPart $qpart) : QueryPart
    {
        if ($qpart->getParentQueryPart() !== $this) {
            throw new RuntimeException('Cannot add child query part "' . $qpart->getAlias() . '" to "' . $this->getAlias() . '": the child has no parent registered!');
        }
        $this->children[] = $qpart;
        return $this;
    }
}
?>