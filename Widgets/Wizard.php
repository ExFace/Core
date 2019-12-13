<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;

/**
 * A wizard with multiple panels (steps) to be filled out one-after-another.
 *
 * @author Andrej Kabachnik
 *        
 */
class Wizard extends Container implements iFillEntireContainer, iHaveToolbars, iHaveButtons
{
    use iHaveButtonsAndToolbarsTrait;
    
    /**
     * Returns the step under the given index (starting with 0 from the left/top)
     * 
     * @param integer $index
     * @return \exface\Core\Widgets\WizardStep|null
     */
    public function getStep($index) : ?WizardStep
    {
        return $this->getSteps()[$index];
    }

    /**
     *
     * @return WizardStep[]
     */
    public function getSteps() : array
    {
        return $this->getWidgets();
    }
    
    /**
     * Defines an array of widgets as steps.
     * 
     * Adding widgets to a `Wizard` will automatically produce `WizardStep` widgets for each added widget, 
     * unless it already is a `WizardStep` or another widget based on it. This way, a short and better
     * understandable notation of wizards is possible: simply add any type of widget to the `steps` array 
     * and see them be displayed in the `Wizard`.
     * 
     * @uxon-property steps
     * @uxon-type \exface\Core\Widgets\WizardStep[]|\exface\Core\Widgets\Container[]
     * @uxon-template [{"caption": "", "widgets": [{"": ""}]}]
     * 
     * @param UxonObject|WizardStep[] $widget_or_uxon_array
     * @return Wizard
     */
    public function setSteps($widget_or_uxon_array) : Wizard
    {
        return $this->setWidgets($widget_or_uxon_array);
    }
    
    /**
     * Returns TRUE if there is at least one step and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasSteps() : bool
    {
        return $this->hasWidgets();
    }
    
    /**
     * Returns TRUE if at least one step has at least one widget and FALSE otherwise.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::isEmpty()
     */
    public function isEmpty()
    {
        foreach ($this->getSteps() as $step){
            if (false === $step->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Defines widets (steps) to be displayed - same as `steps` property.
     * 
     * Adding widgets to a `Wizard` will automatically produce `WizardStep` widgets for each added widget, 
     * unless it already is a `WizardStep` or another widget based on it. This way, a short and better
     * understandable notation of wizards is possible: simply add any type of widget to the `steps` array 
     * and see them be displayed in the `Wizard`.
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\WizardStep[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"caption": "", "widgets": [{"widget_type": ""}]}]
     *
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                // If we have a UXON or instantiated widget object, use the widget directly
                $page = $this->getPage();
                $widget = WidgetFactory::createFromUxon($page, $w, $this, $this->getStepWidgetType());
            } elseif ($w instanceof AbstractWidget){
                $widget = $w;
            } else {
                // If it is something else, just add it to the result and let the parent object deal with it
                $widgets[] = $w;
            }
            
            // If the widget is not a WizardStep itslef, wrap it in a WizardStep. Otherwise add it directly to the result.
            if (! ($widget instanceof WizardStep)) {
                $widgets[] = $this->createStep($widget);
            } else {
                $widgets[] = $widget;
            }
        }
        
        // Now the resulting array consists of widgets and unknown items. Send it to the parent class. Widgets will get
        // added directly and the unknown types may get some special treatment or just lead to errors. We don't handle
        // them here in order to ensure centralised processing in the container widget.
        return parent::setWidgets($widgets);
    }
    
    /**
     * Returns the widget type to use when creating new steps.
     * 
     * @return string
     */
    protected function getStepWidgetType() : string
    {
        return 'WizardStep';
    }

    /**
     * Creates a step (but does not add it automatically!!!)
     *
     * @param WidgetInterface $contents            
     * @return WizardStep
     */
    public function createStep(WidgetInterface $contents = null) : WizardStep
    {
        // Create an empty step
        $widget = $this->getPage()->createWidget($this->getStepWidgetType(), $this);
        
        // If any contained widget is specified, add it to the step an inherit some of it's attributes
        if ($contents) {
            $widget->addWidget($contents);
            $widget->setMetaObject($contents->getMetaObject());
            $widget->setCaption($contents->getCaption());
        }
        
        return $widget;
    }

    /**
     * Adds the given widget as a new step.
     * The position (sequential number) of the step can
     * be specified optionally. If the given widget is not a step itself, it will be wrapped
     * in a WizardStep widget.
     *
     * @see add_widget()
     *
     * @param WidgetInterface $widget            
     * @param int $position            
     * @return Wizard
     */
    public function addStep(WidgetInterface $widget, int $position = null) : Wizard
    {
        if ($widget instanceof WizardStep) {
            $step = $widget;
        } else {
            $step = $this->createStep($widget);
        }
        return $this->addWidget($step, $position);
    }

    /**
     * Returns the number of currently contained steps
     * @return int
     */
    public function countSteps() : int
    {
        return parent::countWidgets();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Container::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = null)
    {
        if ($widget instanceof WizardStep) {
            return parent::addWidget($widget, $position);
        } else {
            return $this->getStepOne()->addWidget($widget);
        }
        return $this;
    }

    /**
     * 
     * @return WizardStep
     */
    protected function getStepOne() : WizardStep
    {
        if ($this->hasSteps() === false) {
            $step = $this->createStep();
            $this->addWidget($step);
        }
        return $this->getSteps()[0];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return $this->getStepOne();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        // See if steps and widgets arrays both exist. If so, remove steps (they came from the original UXON)
        // and keep widgets (they were generated by the parent (Container).
        if ($uxon->hasProperty('steps') && $uxon->hasProperty('widgets')) {
            $uxon->removeProperty('steps');
        }
        return $uxon;
    }
    
    /**
     * 
     * @return string
     */
    public function getToolbarWidgetType()
    {
        return 'FormToolbar';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach (parent::getChildren() as $child) {
            yield $child;
        }
        
        foreach ($this->getToolbars() as $tb) {
            yield $tb;
        }
    }
}