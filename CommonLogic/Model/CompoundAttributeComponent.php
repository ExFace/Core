<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\CompoundAttributeComponentInterface;
use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class CompoundAttributeComponent implements CompoundAttributeComponentInterface
{
    private $compound = null;
    
    private $attribute = null;
    
    private $suffix = null;
    
    private $prefix = null;
    
    /**
     * 
     * @param CompoundAttributeInterface $compoundAttr
     * @param MetaAttributeInterface $componentAttr
     * @param string $valuePrefix
     * @param string $valueSuffix
     */
    public function __construct(CompoundAttributeInterface $compoundAttr, MetaAttributeInterface $componentAttr, string $valuePrefix = '', string $valueSuffix = '')
    {
        $this->compound = $compoundAttr;
        $this->attribute = $componentAttr;
        $this->suffix = $valueSuffix;
        $this->prefix = $valuePrefix;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeComponentInterface::getCompoundAttribute()
     */
    public function getCompoundAttribute(): CompoundAttributeInterface
    {
        return $this->compound;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeComponentInterface::getIndex()
     */
    public function getIndex(): int
    {
        return array_search($this, $this->getCompoundAttribute()->getComponents());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeComponentInterface::getValuePrefix()
     */
    public function getValuePrefix(): string
    {
        return $this->prefix;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeComponentInterface::getValueSuffix()
     */
    public function getValueSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getCompoundAttribute()->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\CompoundAttributeComponentInterface::getAttribute()
     */
    public function getAttribute(): MetaAttributeInterface
    {
        return $this->attribute;
    }
}