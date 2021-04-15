<?php
namespace exface\Core\Interfaces\Widgets;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
interface iSelectDataRows
{
    public function setSyncSelection(iSelectDataRows $otherWidget, string $otherWidgetColumnName, string $thisColumnName) : iSelectDataRows;
    
    public function getSyncSelectionWidget() : iSupportMultiSelect;
}