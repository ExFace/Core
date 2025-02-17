<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iHaveVisibility;

/**
 * This trait adds the visibility property to a widget or a widget part.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveVisibilityTrait {
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveVisibility::getVisibility()
     */
    public function getVisibility() : int
    {
        if ($this->visibility === null)
            $this->setVisibility(EXF_WIDGET_VISIBILITY_NORMAL);
            return $this->visibility;
    }
    
    /**
     * Sets the visibility of the widget: normal, hidden, optional, promoted.
     *
     * @uxon-property visibility
     * @uxon-type [normal,promoted,optional,hidden]
     * @uxon-default normal
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveVisibility::setVisibility()
     */
    public function setVisibility($value) : iHaveVisibility
    {
        if (is_int($value)){
            $this->visibility = $value;
        } else {
            if (! defined('EXF_WIDGET_VISIBILITY_' . mb_strtoupper($value))) {
                throw new WidgetPropertyInvalidValueError($this, 'Invalid visibility value "' . $value . '" for widget "' . $this->getWidgetType() . '"!', '6T90UH3');
            }
            $this->visibility = constant('EXF_WIDGET_VISIBILITY_'. mb_strtoupper($value));
        }
        return $this;
    }
}