<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;

/**
 * The configurator is a special widget, that controls the behavior of another widget.
 * 
 * For example, a DataTable will have a configurator, that contains filters,
 * sorters, perhaps some controls to change the aggregations, a sorting widget
 * to move columns and turn them on and off, etc. A chart configurator will 
 * contain filters and sorters too, but instead of colums it will have a tab
 * with chart types, color themes, etc.
 * 
 * Configurators are organized in tabs. Each type of configurator will have 
 * a different set of tabs. However, since configurators are separate widgets,
 * templates may render them as something different than the regular tabs widget:
 * perhaps, an accordion. Templates can also rearrange tab contents: e.g. a
 * desktop template with lot's of space could render a DataConfigurator with 
 * only two tabs - one for filters and one for everything else. Another option
 * could be a panel with promoted filters and sorters and a dialog with the
 * rest of the configurator rendered as regular tabs. There are lots of
 * possibilities!
 * 
 * This is a basic - empty - configurator. It is the base for real configurators
 * like:
 * 
 * @see DataConfigurator
 * @see DataTableConfigurator
 * @see ChartConfigurator
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetConfigurator extends Tabs implements iConfigureWidgets
{
    private $widget = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::getWidget()
     */
    public function getWidgetConfigured()
    {
        if (is_null($this->widget)){
            // TODO search recursively for a parent with iHaveConfigurator
            return $this->getParent();
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::setWidget()
     */
    public function setWidgetConfigured(iHaveConfigurator $widget)
    {
        $this->widget = $widget;
        return $this;
    }
}
?>