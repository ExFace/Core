<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Toolbar;
use exface\Core\CommonLogic\UxonObject;

trait iHaveToolbarsTrait {
    
    private $toolbars = array();
    
    /**
     *
     * @return \exface\Core\Widgets\Toolbar
     */
    public function getToolbarMain()
    {
        return $this->getToolbars()[0];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::getToolbars()
     */
    public function getToolbars()
    {
        if (count($this->toolbars) == 0){
            $this->addToolbar(WidgetFactory::create($this->getPage(), $this->getToolbarWidgetType(), $this));
        }
        return $this->toolbars;
    }
    
    /**
     * Defines one or more toolbars with buttons for this widget.
     * 
     * Specifying toolbars is a more flexible alternative to filling the buttons
     * array of a widget. While all buttons specified there will be automatically 
     * added to the default toolbar, specifying each toolbar separately makes it 
     * possible to choose where to place which buttons and to control button
     * grouping within each toolbar. Refer to the description of the Toolbar
     * widget for more details.
     * 
     * Depending on the template used, the position-property of a toolbar can be
     * used to place it at a specific point of the widget. For example, a
     * DataTable widget will typically have a top and a bottom toolbar. 
     * 
     * @uxon-property toolbars
     * @uxon-type \exface\Core\Widgets\Toolbar
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::setToolbars()
     */
    public function setToolbars(array $widgets_or_uxon_objects)
    {
        foreach ($widgets_or_uxon_objects as $toolbar){
            if ($toolbar instanceof Toolbar){
                $this->addToolbar($toolbar);
            } elseif ($toolbar instanceof UxonObject){
                if (!$toolbar->hasProperty('widget_type')){
                    $toolbar->setProperty('widget_type', 'DataToolbar');
                }
                $this->addToolbar(WidgetFactory::createFromUxon($this->getPage(), $toolbar, $this));
            }
        }
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::addToolbar()
     */
    public function addToolbar(Toolbar $toolbar)
    {
        if ($toolbar->getParent() !== $this){
            $toolbar->setParent($this);
        }
        $this->toolbars[] = $toolbar;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::removeToolbar()
     */
    public function removeToolbar(Toolbar $toolbar){
        unset($this->toolbars[array_search($toolbar, $this->toolbars)]);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::getToolbarWidgetType()
     */
    public function getToolbarWidgetType()
    {
        return 'Toolbar';
    }
}