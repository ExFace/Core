<?php
namespace exface\Core\Widgets;

/**
 * A WizardStep is a special form to be used within Wizard widgets.
 * 
 * @method Wizard getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class WizardStep extends Form
{
    /**
     * 
     * @return int
     */
    public function getStepNumber() : int
    {
        // Add +1 since widget numbering starts with 0!
        return $this->getParent()->getWidgetIndex($this) + 1;
    }
    
    /**
     * 
     * @return Wizard
     */
    public function getWizard() : Wizard
    {
        return $this->getParent();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see Form::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'WizardButton';
    }
    
    /**
     * Array of buttons visible in this wizard step.
     * 
     * By default, every button validates this step and (if successfull) advances 
     * to the next step. 
     *
     * All buttons specified here will be added to the main toolbar of the step.
     * Refer to the description of the `toolbars` property for details.
     *
     * Depending on the `align` property of each button it will be automatically
     * added to the first button group left or right in the main toolbar.
     *
     * ## Example:
     *
     * ```
     *  {
     *      "buttons": [
     *          {
     *              "action_alias": "exface.Core.ShowObjectCreateDialog"
     *          },
     *          {
     *              "widget_type": "MenuButton",
     *              "caption": "My menu",
     *              "buttons": [...]
     *          },
     *          {
     *              "action_alias": "exface.Core.RefreshWidget",
     *              "align": "right"
     *          }
     *      ]
     *  }
     *
     * ```
     *
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template [{"action_alias": ""}]
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons($buttons)
    {
        parent::setButtons($buttons);
        return $this;
    }
}