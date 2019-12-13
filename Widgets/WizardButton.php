<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * 
 * @author Andrej Kabachnik   
 */
class WizardButton extends Button
{
    /**
     * 
     * @var int|NULL
     */
    private $goToStep = null;
    
    /**
     *
     * @return int|NULL
     */
    public function getGoToStep() : ?int
    {
        if ($this->goToStep === -1) {
            return $this->getWizardStep()->getStepNumber() - 1;
        } elseif ($this->goToStep === null) {
            return $this->getWizardStep()->getStepNumber();
        }
        return $this->goToStep;
    }
    
    /**
     * Step number to activate when button is pressed or "none" to stay on this step.
     * 
     * @uxon-property go_to_step
     * @uxon-type [next,none]|integer
     * @uxon-default next
     * 
     * @param int|string $value
     * @return WizardButton
     */
    public function setGoToStep($value) : WizardButton
    {
        if ($value === 'none') {
            $value = -1;
        } elseif ($value === 'next' || $value === null || $value === '') {
            $value = null;
        } elseif (is_int($value) === false) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $value . '" for property go_to_step of widget "' . $this->getWidgetType() . '": only step numbers or keyword "none" allowed!');
        }
        $this->goToStep = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Button::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if ($next = $this->getGoToStep() !== null) {
            $uxon->setProperty('got_to_step', $next);
        }
        return $uxon;
    }
    
    /**
     * 
     * @throws WidgetConfigurationError
     * @return WizardStep
     */
    public function getWizardStep() : WizardStep
    {
        $parent = $this->getParent();
        while (! ($parent instanceof WizardStep) && $parent->hasParent() === true) {
            $parent = $parent->getParent();
        }
        
        if (! ($parent instanceof WizardStep) && $parent->hasParent() === false) {
            throw new WidgetConfigurationError($this, 'Cannot find WizardStep for ' . $this->getWidgetType() . '!');
        }
        return $parent;
    }
}