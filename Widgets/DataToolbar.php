<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * A general toolbar to be used in data widgets for actions.
 * 
 * The generic DataToolbar is nothing more than a simple container with a link
 * to the data widget. This makes sure all widgets using data internally (like 
 * ComboTables, Charts, etc.) do not have to create complex toolbars. Widgets
 * that need visible toolbars with global actions, etc. should use the
 * DataTableToolbar, that has a lot of extras, but produces overhead.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataToolbar extends Toolbar
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setParent()
     */
    public function setParent(WidgetInterface $widget)
    {
        if (! $this->is($widget->getToolbarWidgetType())
        && ! $widget instanceof DataToolbar){
            throw new WidgetConfigurationError($this, 'The widget DataToolbar can only be used within Data widgets or other DataToolbars');
        }
        return parent::setParent($widget);
    }
    
    /**
     * 
     * @return Data
     */
    public function getDataWidget()
    {
        return $this->getInputWidget();
    }
}
?>