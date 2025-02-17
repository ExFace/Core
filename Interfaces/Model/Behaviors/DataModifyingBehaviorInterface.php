<?php
namespace exface\Core\Interfaces\Model\Behaviors;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

interface DataModifyingBehaviorInterface extends BehaviorInterface
{
    /**
     * Returns an array of meta attributes, that might be effected by this behavior in the given sheet
     * 
     * NOTE: attributes in this list are NOT guaranteed to be modified. These attributes
     * will be processed and MAY get modified or added to the sheet.
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $inputSheet
     * @return MetaAttributeInterface[]
     */
    public function getAttributesModified(DataSheetInterface $inputSheet) : array;
    
    /**
     * Returns TRUE if this behavior can add columns to a data sheet or FALSE otherwise
     * 
     * @return bool
     */
    public function canAddColumnsToData() : bool; 
}