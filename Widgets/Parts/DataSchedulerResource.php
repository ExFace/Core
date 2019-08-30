<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;

/**
 * Configuration for resources (people, rooms, etc.) in calendar-related data widgets.
 * 
 * IDEA resources typically are represented by a different meta object, than calendar items.
 * Perhaps, it would be better to make the resource a widget, so that it can be selected,
 * maybe have actions, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSchedulerResource implements WidgetPartInterface
{
    use DataWidgetPartTrait;
    
    private $uidString = null;
    
    private $uidColumn = null;
    
    private $titleString = null;
    
    private $titleColumn = null;
    
    private $subtitleString = null;
    
    private $subtitleColumn = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'title' => $this->getTitleColumn()->getAttributeAlias()
        ]);
        
        if ($this->hasSubtitle()) {
            $uxon->setProperty('subtitle', $this->getSubtitleColumn()->getAttributeAlias());
        }
        
        return $uxon;
    }
    
    /**
     *
     * @return string
     */
    protected function getTitle() : string
    {
        return $this->titleString;
    }
    
    /**
     * Attribute alias or any other expression to be displayed as item title.
     * 
     * If not set explicitly, the object label will be used. If not present - the first
     * visible data column.
     * 
     * @uxon-property title
     * @uxon-type metamodel:expression
     * 
     * @param string $value
     * @return DataSchedulerResource
     */
    public function setTitle(string $expression) : DataSchedulerResource
    {
        $this->titleString = $expression;
        $this->titleColumn = $this->addDataColumn($expression);
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getTitleColumn() : DataColumn
    {
        if ($this->titleColumn === null) {
            if ($this->getMetaObject()->hasLabelAttribute()) {
                $this->titleColumn = $this->addDataColumn($this->getMetaObject()->getLabelAttribute()->getAlias());
            } else {
                foreach ($this->getDataWidget()->getColumns() as $col) {
                    if (false === $col->isHidden()) {
                        $this->titleColumn = $this->getDataWidget()->getColumns()[0];
                        break;
                    }
                }
            }
        }
        return $this->titleColumn;
    }
    
    
    
    /**
     *
     * @return string
     */
    protected function getSubtitle() : string
    {
        return $this->subtitleString;
    }
    
    /**
     * Attribute alias or any other expression to be displayed as item subtitle.
     *
     * If not set explicitly, the object label will be used. If not present - the first
     * visible data column.
     *
     * @uxon-property subtitle
     * @uxon-type metamodel:expression
     *
     * @param string $value
     * @return DataSchedulerResource
     */
    public function setSubtitle(string $expression) : DataSchedulerResource
    {
        $this->subtitleString = $expression;
        $this->subtitleColumn = $this->addDataColumn($expression);
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getSubtitleColumn() : ?DataColumn
    {
        return $this->subtitleColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasSubtitle() : bool
    {
        return $this->subtitleString !== null;
    }
}