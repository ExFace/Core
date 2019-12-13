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
}