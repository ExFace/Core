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
            $this->addToolbar(WidgetFactory::create($this->getPage(), 'DataToolbar', $this));
        }
        return $this->toolbars;
    }
    
    /**
     * Give the widget one or more toolbars with buttons.
     * 
     * This is a more flexible alternative for the buttons property. While all
     * buttons specified there will be automatically added to the default
     * toolbar, specifying each toolbar separately makes it possible to choose
     * where to place which buttons. Many widgets support multiple toolbars:
     * top, bottom, perhaps a menu, etc.
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
    
}