<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\LogicException;
use exface\Core\CommonLogic\UxonObject;

class RelatedAttribute extends Attribute
{
    private $original_attribute = null;
    
    private $relation_path = null;
    
    public function __construct(MetaObjectInterface $object, MetaAttributeInterface $original_attribute = null)
    {
        if (is_null($original_attribute)){
            throw new \UnexpectedValueException('No orgininal attribute specified for a related attribute!');
        }
        $this->original_attribute = $original_attribute;
        parent::__construct($object);
    }
    
    public function getRelationPath()
    {
        if (is_null($this->relation_path)) {
            $this->relation_path = RelationPathFactory::createForObject($this->getObject());
        }
        return $this->relation_path;
    }
    
    public function getOriginalAttribute()
    {
        return $this->original_attribute;
    }
    
    public function setOriginalAttribute(MetaAttributeInterface $attribute)
    {
        $this->original_attribute = $attribute;
        return $this;
    }
    
    public function getObject()
    {
        return $this->getOriginalAttribute()->getObject();
    }
    
    /**
     * For every method not exlicitly defined in this class, call the original attribute.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments){
        return call_user_func_array(array($this->getOriginalAttribute(), $method), $arguments);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setRelationFlag()
     */
    public function setRelationFlag($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isRelation()
     */
    public function isRelation()
    {
        return $this->getOriginalAttribute()->isRelation();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getId()
     */
    public function getId()
    {
        return $this->getOriginalAttribute()->getId();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setId()
     */
    public function setId($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->getOriginalAttribute()->getAlias();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setAlias()
     */
    public function setAlias($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataType()
     */
    public function getDataType()
    {
        return $this->getOriginalAttribute()->getDataType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataType()
     */
    public function setDataType($object_or_name)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultDisplayOrder()
     */
    public function getDefaultDisplayOrder()
    {
        return $this->getOriginalAttribute()->getDefaultDisplayOrder();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultDisplayOrder()
     */
    public function setDefaultDisplayOrder($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isEditable()
     */
    public function isEditable()
    {
        return $this->getOriginalAttribute()->isEditable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setEditable()
     */
    public function setEditable($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getFormatter()
     */
    public function getFormatter()
    {
        return $this->getOriginalAttribute()->getFormatter();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFormatter()
     */
    public function setFormatter($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isHidden()
     */
    public function isHidden()
    {
        return $this->getOriginalAttribute()->isHidden();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setHidden()
     */
    public function setHidden($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getName()
     */
    public function getName()
    {
        return $this->getOriginalAttribute()->getName();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setName()
     */
    public function setName($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isRequired()
     */
    public function isRequired()
    {
        return $this->getOriginalAttribute()->isRequired();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setRequired()
     */
    public function setRequired($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataAddress()
     */
    public function getDataAddress()
    {
        return $this->getOriginalAttribute()->getDataAddress();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataAddress()
     */
    public function setDataAddress($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }   
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultWidgetUxon()
     */
    public function getDefaultWidgetUxon()
    {
        return $this->getOriginalAttribute()->getDefaultWidgetUxon();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultWidgetUxon()
     */
    public function setDefaultWidgetUxon(UxonObject $uxon_object)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getFormula()
     */
    public function getFormula()
    {
        return $this->getOriginalAttribute()->getFormula();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFormula()
     */
    public function setFormula($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultValue()
     */
    public function getDefaultValue()
    {
        return $this->getOriginalAttribute()->getDefaultValue();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultValue()
     */
    public function setDefaultValue($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getFixedValue()
     */
    public function getFixedValue()
    {
        return $this->getOriginalAttribute()->getFixedValue();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFixedValue()
     */
    public function setFixedValue($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultSorterDir()
     */
    public function getDefaultSorterDir()
    {
        return $this->getOriginalAttribute()->getDefaultSorterDir();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultSorterDir()
     */
    public function setDefaultSorterDir($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getShortDescription()
     */
    public function getShortDescription()
    {
        return $this->getOriginalAttribute()->getShortDescription();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setShortDescription()
     */
    public function setShortDescription($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getHint()
     */
    public function getHint()
    {
        return $this->getOriginalAttribute()->getHint();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getInheritedFromObjectId()
     */
    public function getInheritedFromObjectId()
    {
        return $this->getOriginalAttribute()->getInheritedFromObjectId();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setInheritedFromObjectId()
     */
    public function setInheritedFromObjectId($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isInherited()
     */
    public function isInherited()
    {
        return $this->getOriginalAttribute()->isInherited();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataAddressProperties()
     */
    public function getDataAddressProperties()
    {
        return $this->getOriginalAttribute()->getDataAddressProperties();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataAddressProperties()
     */
    public function setDataAddressProperties(UxonObject $value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isSystem()
     */
    public function isSystem()
    {
        return $this->getOriginalAttribute()->isSystem();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setSystem()
     */
    public function setSystem($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultAggregateFunction()
     */
    public function getDefaultAggregateFunction()
    {
        return $this->getOriginalAttribute()->getDefaultAggregateFunction();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultAggregateFunction()
     */
    public function setDefaultAggregateFunction($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isSortable()
     */
    public function isSortable()
    {
        return $this->getOriginalAttribute()->isSortable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setSortable()
     */
    public function setSortable($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isFilterable()
     */
    public function isFilterable()
    {
        return $this->getOriginalAttribute()->isFilterable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFilterable()
     */
    public function setFilterable($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isAggregatable()
     */
    public function isAggregatable()
    {
        return $this->getOriginalAttribute()->isAggregatable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setAggregatable()
     */
    public function setAggregatable($value)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getValueListDelimiter()
     */
    public function getValueListDelimiter()
    {
        return $this->getOriginalAttribute()->getValueListDelimiter();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setValueListDelimiter()
     */
    public function setValueListDelimiter($string)
    {
        throw new LogicException('Cannot call ' . __FUNCTION__ . ' on a related attribute!');
    }
}
