<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Special button type for wizards - allows to navigate between wizard steps.
 * 
 * `WizardButton`s can only be used within `WizardStep`s. In addition to (optional)
 * regular `action` configuration these buttons can navigate between wizard steps 
 * via `go_to_step` property. By default a `WizardButton` will navigate to the next
 * step, but you can also specify a step index explicitly (starting with 0 for the 
 * first step) to navigate to a specific step.
 * 
 * @author Andrej Kabachnik   
 */
class WizardButton extends Button
{
    const GO_TO_STEP_NONE = 'none';
    const GO_TO_STEP_NEXT = 'next';
    const GO_TO_STEP_PREVIOUS = 'previous';
    
    /**
     * 
     * @var int|NULL
     */
    private $goToStep = null;
    
    /**
     *
     * @return int|NULL
     */
    public function getGoToStepIndex() : ?int
    {
        // Remember, that WizardStep::getStepNumber() returns numbers starting at 1!
        if ($this->goToStep === -2) {
            // Previous step
            return $this->getWizardStep()->getStepNumber() - 2;
        } elseif ($this->goToStep === -1) {
            // Current step
            return $this->getWizardStep()->getStepNumber() - 1;
        } elseif ($this->goToStep === null) {
            // Next step
            return $this->getWizardStep()->getStepNumber();
        }
        return $this->goToStep;
    }
    
    /**
     * 
     * @return WizardStep
     */
    public function getGoToStep() : WizardStep
    {
        return $this->getWizard()->getStep($this->getGoToStepIndex());
    }
    
    /**
     * Step number to activate when button is pressed or "none" to stay on this step.
     * 
     * @uxon-property go_to_step
     * @uxon-type [next,previous,none]|integer
     * @uxon-default next
     * 
     * @param int|string $value
     * @return WizardButton
     */
    public function setGoToStep($value) : WizardButton
    {
        if ($value === self::GO_TO_STEP_NONE || $value === -1) {
            $value = -1;
        } elseif ($value === self::GO_TO_STEP_PREVIOUS || $value === -2) {
            $value = -2;
        } elseif ($value === self::GO_TO_STEP_NEXT || $value === null || $value === '') {
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
    
    /**
     * 
     * @return Wizard
     */
    public function getWizard() : Wizard
    {
        return $this->getWizardStep()->getWizard();
    }
    
    public function getCaption() : ?string
    {
        $caption = parent::getCaption();
        if ($caption === null || $caption === '') {
            $thisNo = $this->getWizardStep()->getStepNumber();
            switch ($this->getGoToStep()->getStepNumber()) {
                case $thisNo-1: return $this->translate('WIDGET.WIZARD.PREV_STEP_BUTTON_CAPTION'); break;
                case $thisNo+1: return $this->translate('WIDGET.WIZARD.NEXT_STEP_BUTTON_CAPTION'); break;
            }
        }
        return $caption;
    }
}