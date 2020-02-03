<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *
 */
interface CompoundAttributeComponentInterface extends WorkbenchDependantInterface
{
    public function getCompoundAttribute() : CompoundAttributeInterface;
    
    public function getAttribute() : MetaAttributeInterface;
    
    public function getIndex() : int;
    
    public function getValuePrefix() : string;
    
    public function getValueSuffix() : string;
}