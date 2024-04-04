<?php
namespace exface\Core\Interfaces\Model\Behaviors;

use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

interface DataModifyingBehaviorInterface extends BehaviorInterface
{
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getAttributesModified() : array;
    
    public function canAddColumnsToData() : bool; 
}