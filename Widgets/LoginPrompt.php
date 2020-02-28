<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * A login promt with potentially multiple forms for different authentication options (i.e. local login, LDAP, OAuth, etc.).
 * 
 * @author Andrej Kabachnik
 *        
 */
class LoginPrompt extends Container implements iFillEntireContainer
{
    /**
     * Returns the panels of the Split.
     * Technically it is an alias for Split::getWidgets() for better readability.
     *
     * @see getWidgets()
     */
    public function getForms()
    {
        return $this->getWidgets();
    }

    /**
     * Array of login forms (im multiple login options required).
     * 
     * @uxon-property forms
     * @uxon-type \exface\Core\Widgets\LoginPrompt[]
     * @uxon-template [{"caption": "", "widgets": [{"": ""}]}]
     *
     * @param UxonObject|LoginPrompt|AbstractWidget $widget_or_uxon_array
     * @return \exface\Core\Widgets\LoginPrompt
     */
    public function setForms($widget_or_uxon_array) : LoginPrompt
    {
        return $this->setWidgets($widget_or_uxon_array);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                $widget = WidgetFactory::createFromUxonInParent($this, $w, 'Form');
            } elseif ($w instanceof WidgetInterface) {
                // If it is already a widget, take it for further checks
                $widget = $w;
            } else {
                throw new WidgetConfigurationError($this, 'Invalid element "' . $w  . '" in property "forms" of widget "' . $this->getWidgetType() . '": expecting UXON widget description or instantiated widget object!');
            }
            
            if (! ($widget instanceof Form)) {
                throw new WidgetConfigurationError($this, 'Cannot use widget "' . $widget->getWidgetType()  . '" within property "forms" of widget "' . $this->getWidgetType() . '": only Form widgets or derivatives allowed!');
            } else {
                $widgets[] = $widget;
            }
        }
        
        return parent::setWidgets($widgets);
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return $this->getWidgetFirst();
    }
}