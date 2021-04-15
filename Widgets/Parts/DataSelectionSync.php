<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * 
 * @method iShowData getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSelectionSync implements WidgetPartInterface
{
    use DataWidgetPartTrait;
    
    private $syncWithWidgetId = null;
    
    private $syncWidgetColumnName = null;
    
    private $thisWidgetColumnName = null;
    
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        // TODO
        return $uxon;
    }
    
    public function getSyncWithWidgetId() : string
    {
        return $this->syncWithWidgetId;
    }
    
    
    /**
     * The id of the widget to sync with (must be a widget, that supports selection!)
     * 
     * @uxon-property sync_with_widget_id
     * @uxon-type uxon:$..id
     * @uxon-required true
     * 
     * @param string $value
     * @return DataSelectionSync
     */
    public function setSyncWithWidgetId(string $value) : DataSelectionSync
    {
        $this->syncWithWidgetId = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getSyncWidgetColumnName() : string
    {
        return $this->syncWidgetColumnName;
    }
    
    public function setSyncWidgetColumnName(string $value) : DataSelectionSync
    {
        $this->syncWidgetColumnName = $value;
        return $this;
    }
    
    public function getThisWidgetColumnName() : string
    {
        return $this->thisWidgetColumnName;
    }
    
    public function setThisWidgetColumnName(string $value) : DataSelectionSync
    {
        $this->thisWidgetColumnName = $value;
        return $this;
    }
}