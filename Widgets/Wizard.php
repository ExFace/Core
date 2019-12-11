<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * A wizard with multiple panels (steps) to be filled out one-after-another.
 *
 * @author Andrej Kabachnik
 *        
 */
class Wizard extends Tabs
{
    /**
     * Override the method to remove the UXON property tabs - the wizard uses steps instead.
     * 
     * @see \exface\Core\Widgets\Tabs::setTabs()
     */
    public function setTabs($widget_or_uxon_array) : Tabs
    {
        return parent::setTabs($widget_or_uxon_array);
    }
    
    /**
     * Slides of the carousel - each is a separate container widget
     * 
     * @uxon-property slides
     * @uxon-type \exface\Core\Widgets\Tab[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"widgets": [{"": ""}]}]
     * 
     * @param UxonObject|Tab $widget_or_uxon_array
     * @return Tabs
     */
    public function setSteps($widget_or_uxon_array) : Wizard
    {
        return $this->setWidgets($widget_or_uxon_array);
    }
    
    /**
     * Returns the widget type to use when creating new tabs.
     *
     * @return string
     */
    protected function getTabWidgetType() : string
    {
        return 'WizardStep';
    }
}