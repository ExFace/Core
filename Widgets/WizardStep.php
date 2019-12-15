<?php
namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Widgets\iHaveIcon;

/**
 * A WizardStep is a special form to be used within Wizard widgets.
 * 
 * Each `WizardStep` can have it's own set of `buttons` (even it's own `toolbars` if needed).
 * These will only be shown, when the step is active - in contrast to the buttons of the
 * `Wizard` widget itself, which are always visible (in all steps).
 * 
 * If no buttons are specified, the `WizardStep` will automatically contain buttons to
 * navigate to the next and previous steps - see `buttons` property for details.
 * 
 * Use `caption`, `hint` and `icon` to customize the appearance of the step in the navigation.
 * Add an `intro` to display a short description of the text (usually abover the content).
 * 
 * As any other form, `WizardStep`may contain any number of widgets. Their positioning can
 * be controlled by changing the `columns_in_grid` and using nested layout widgets like 
 * `WidgetGroup`, `WidgetGrid`, `InlineGroup`, etc.
 * 
 * See documentation for he `Wizard` widget for examples.
 * 
 * @method Wizard getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class WizardStep extends Form implements iHaveIcon
{
    use iHaveIconTrait;
    
    /**
     * 
     * @var string
     */
    private $intro = null;
    
    /**
     * Returns the number of the step (starting with 1!).
     * 
     * NOTE: step numbers start with 1 in contrast to the step index, which starts
     * with 0. 
     * 
     * @return int
     */
    public function getStepNumber() : int
    {
        // Add +1 since widget numbering starts with 0!
        return $this->getParent()->getWidgetIndex($this) + 1;
    }
    
    /**
     * Returns the step index (= widget index within the Wizard - starting with 0!)
     * @return int
     */
    public function getStepIndex() : int
    {
        return $this->getParent()->getWidgetIndex($this);
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
     * to the next step. Use the `go_to_step` property to change this.
     * 
     * If no `buttons` specified, a `WizardStep` will automatically have buttons
     * to navigate to the next and previous steps.
     * 
     * **NOTE**: if you define buttons manually, you will need to add these navigation
     * buttons manually too - see example below!
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
     *              "go_to_step": "previous",
     *              "icon": "chevron-right"
     *          },{
     *              "caption": "Skip this step",
     *              "go_to_step": "next",
     *              "icon": "chevron-right"
     *          },{
     *              "caption": "Next",
     *              "action_alias": "exface.Core.CreateData",
     *              "visibility": "Promoted",
     *              "icon": "chevron-right"
     *          }
     *      ]
     *  }
     *
     * ```
     *
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\WizardButton[]
     * @uxon-template [{"action_alias": ""}]
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons($buttons)
    {
        parent::setButtons($buttons);
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\Widgets\Toolbar
     */
    public function getToolbarMain()
    {
        $tb = parent::getToolbarMain();
        if ($tb->hasButtons() === false) {
            $this->createDefaultButtons($tb);
        }
        return $tb;
    }
    
    /**
     * 
     * @param Toolbar $toolbar
     * @return WizardStep
     */
    protected function createDefaultButtons(Toolbar $toolbar) : WizardStep
    {
        if ($this->isFirst() === false) {
            $toolbar->addButton($toolbar->createButton(new UxonObject([
                'widget_type' => 'WizardButton',
                'icon' => Icons::CHEVRON_LEFT,
                'go_to_step' => WizardButton::GO_TO_STEP_PREVIOUS
            ])));
        }
        if ($this->isLast() === false) {
            $toolbar->addButton($toolbar->createButton(new UxonObject([
                'visibility' => 'promoted',
                'widget_type' => 'WizardButton',
                'icon' => Icons::CHEVRON_RIGHT
            ])));
        }
        return $this;
    }
    
    /**
     * Returns TRUE if this is the last step of the Wizard
     * 
     * @return bool
     */
    public function isLast() : bool
    {
        return $this->getStepNumber() === $this->getWizard()->countSteps();
    }
    
    /**
     * Returns TRUE if this is the first step of the Wizard
     * 
     * @return bool
     */
    public function isFirst() : bool
    {
        return $this->getStepNumber() === 1;    
    }
    
    /**
     * Returns an array with other steps of the widget that can be reached from this step.
     * 
     * @return int[]
     */
    public function getStepsReachable() : array
    {
        $nos = [];
        foreach ($this->getButtons() as $btn) {
            if  ($btn instanceof WizardButton) {
                if ($btn->getGoToStep() !== $this) {
                    $nos[] = $btn->getGoToStep();
                }
            }
        }
        return $nos;
    }
    
    /**
     * Returns TRUE if this step can be skipped and FALSE otherwise.
     * @return bool
     */
    public function isOptional() : bool
    {
        $thisNo = $this->getStepNumber();
        // The step can be skipped if one of the previous steps 
        foreach ($this->getWizard()->getSteps() as $idx => $step) {
            if ($idx >= $thisNo-1) {
                break;
            }
            foreach ($step->getStepsReachable() as $step) {
                if ($thisNo < $step->getStepNumber()) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getIntro() : ?string
    {
        return $this->intro;
    }
    
    /**
     * A brief desription of what to do in this step.
     * 
     * Text lines can be separated by regular line breaks. HTML, Markdown and other markup
     * not supported unless explicitly allowed by a facade.
     * 
     * @uxon-property intro
     * @uxon-type string
     * 
     * @param string $value
     * @return WizardStep
     */
    public function setIntro(string $value) : WizardStep
    {
        $this->intro = $value;
        return $this;
    }
    
}