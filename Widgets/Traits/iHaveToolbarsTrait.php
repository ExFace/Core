<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Toolbar;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

trait iHaveToolbarsTrait {
    
    private $toolbars = array();
    
    /**
     *
     * @return \exface\Core\Widgets\Toolbar
     */
    public function getToolbarMain()
    {
        if ($this->hasToolbars() === false){
            return $this->initMainToolbar();
        }
        return $this->getToolbars()[0];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::getToolbars()
     */
    public function getToolbars()
    {
        if ($this->hasToolbars() === false){
            $this->initMainToolbar();
        }
        return $this->toolbars;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::hasToolbars()
     */
    public function hasToolbars() : bool
    {
        return empty($this->toolbars) === false;
    }
    
    /**
     * Creates the main toolbar.
     * 
     * Override this method to add default buttons, that should appar if no buttons are
     * specified for the main toolbar expplicitly.
     * 
     * @return Toolbar
     */
    protected function initMainToolbar() : Toolbar
    {
        $tb = WidgetFactory::create($this->getPage(), $this->getToolbarWidgetType(), $this);
        $this->addToolbar($tb);
        return $tb;
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
     * Depending on the facade used, the position-property of a toolbar can be
     * used to place it at a specific point of the widget. For example, a
     * DataTable widget will typically have a top and a bottom toolbar. 
     * 
     * @uxon-property toolbars
     * @uxon-type \exface\Core\Widgets\Toolbar[]
     * @uxon-template [{"buttons": [{"action_alias": ""}] }]
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::setToolbars()
     */
    public function setToolbars($widgets_or_uxon_objects)
    {
        foreach ($widgets_or_uxon_objects as $toolbar){
            if ($toolbar instanceof Toolbar){
                $this->addToolbar($toolbar);
            } elseif ($toolbar instanceof UxonObject){
                if (!$toolbar->hasProperty('widget_type')){
                    $toolbar->setProperty('widget_type', 'DataToolbar');
                }
                $this->addToolbar(WidgetFactory::createFromUxon($this->getPage(), $toolbar, $this));
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'Cannot set toolbars of ' . $this->getWidgetType() . ': expecting instantiated Toolbar widgets or their UXON descriptions - ' . gettype($widgets_or_uxon_objects) . ' given instead!');
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