<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\LogicException;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class TreeLevel extends AbstractWidget
{
    private $UidAttributeAlias = null;

    private $TextAttributeAlias = null;
    
    private $RelationToParentAlias = null;
    
    private $NodeTypeFolderOpenCondition = null;
    
    private $NodeTypeFolderClosedCondition = null;
    
    private $NodeTypeLeafCondition = null;
    
    /**
     * @return string $UidAttributeAlias
     */
    public function getUidAttributeAlias()
    {
        if (is_null($this->UidAttributeAlias)) {
            return $this->getMetaObject()->getUidAttributeAlias();
        }
        return $this->UidAttributeAlias;
    }

    /**
     * @param string $string
     * @return TreeLevel
     */
    public function setUidAttributeAlias($string)
    {
        $this->UidAttributeAlias = $string;
        return $this;
    }
    
    public function getUidAttribute()
    {
        return $this->getMetaObject()->getAttribute($this->getUidAttributeAlias());
    }

    /**
     * @return string $TextAttributeAlias
     */
    public function getTextAttributeAlias()
    {
        if (is_null($this->UidAttributeAlias)) {
            return $this->getMetaObject()->getLabelAttributeAlias();
        }
        return $this->TextAttributeAlias;
    }

    /**
     * @param string $string
     */
    public function setTextAttributeAlias($string)
    {
        $this->TextAttributeAlias = $string;
        return $this;
    }
    
    public function getTextAttribute()
    {
        return $this->getMetaObject()->getAttribute($this->getTextAttributeAlias());
    }

    /**
     * @return string
     */
    public function getRelationToParentAlias()
    {
        if (is_null($this->RelationToParentAlias)) {
            throw new WidgetConfigurationError('Cannot find a relation to parent tree level in level ' . $this->getTree()->getLevelIndex($this) . ' of ' . $this->getTree()->getMetaObject()->getAliasWithNamespace() . ' tree.');
        }
        return $this->RelationToParentAlias;
    }

    /**
     * @param string $relationPath
     */
    public function setRelationToParentAlias($relationPath)
    {
        $this->RelationToParentAlias = $relationPath;
        return $this;
    }

    /**
     * @return Condition
     */
    public function getNodeTypeFolderOpenCondition()
    {
        return $this->NodeTypeFolderOpenCondition;
    }

    /**
     * @param Condition|UxonObject|string $condition_or_string_or_uxon
     * @return TreeLevel
     */
    public function setNodeTypeFolderOpenCondition($condition_or_string_or_uxon)
    {
        $this->NodeTypeFolderOpenCondition = $condition_or_string_or_uxon;
        return $this;
    }
    
    /**
     * @return Condition
     */
    public function getNodeTypeFolderClosedCondition()
    {
        return $this->NodeTypeFolderClosedCondition;
    }

    /**
     * @param Condition|UxonObject|string $condition_or_string_or_uxon
     * @return TreeLevel
     */
    public function setNodeTypeFolderClosedCondition($NodeTypeFolderClosedCondition)
    {
        $this->NodeTypeFolderClosedCondition = $NodeTypeFolderClosedCondition;
        return $this;
    }

    /**
     * @return Condition
     */
    public function getNodeTypeLeafCondition()
    {
        return $this->NodeTypeLeafCondition;
    }

     /**
     * @param Condition|UxonObject|string $condition_or_string_or_uxon
     * @return TreeLevel
     */
    public function setNodeTypeLeafCondition($NodeTypeLeafCondition)
    {
        $this->NodeTypeLeafCondition = $NodeTypeLeafCondition;
        return $this;
    }

    /**
     * 
     * @return Tree
     */
    public function getTree()
    {
        return $this->getParent();
    }
    
    /**
     * 
     * @return string
     */
    public function getType()
    {
        return strtolower(str_replace('TreeLevel', '', $this->getWidgetType()));
    }
    
    /**
     * Defines the type of a tree level: use "recursive" for recursive hierarchies or leave blank for a per-level definition.
     * 
     * @uxon-property type
     * @uxon-type string
     * 
     * @param string $levelType
     * @return TreeLevel
     */
    public function setType($levelType)
    {
        $levelType = strtolower($levelType);
        if ($levelType !== $this->getType()) {
            throw new LogicException('Cannot change the type of a tree level widget after it had been initialized!');
        }
        return $this;
    }
    
}
?>