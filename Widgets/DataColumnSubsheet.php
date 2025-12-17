<?php
namespace exface\Core\Widgets;

/**
 * 
 * 
 * @method Data getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumnSubsheet extends DataColumnGroup
{
    /**
     * The relation from the table object to the subsheet contained here
     * 
     * @uxon-property relation
     * @uxon-type metamodel:relation
     * 
     * @param string $alias
     * @return $this
     */
    protected function setRelation(string $alias) : DataColumnSubsheet
    {
        $rel = $this->getParent()->getMetaObject()->getRelation($alias);
        $this->setMetaObject($rel->getRightObject());
        $this->setObjectRelationPathToParent($rel->reverse()->getAliasWithModifier());
        return $this;
    }
}