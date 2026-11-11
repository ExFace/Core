<?php

namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * This class represents the configuration for functions that should be performed when the user clicks the "Next" button in a tour step popover.
 * 
 * - `widget_function` - The name of the widget function to call when the user clicks on the "next" button in the popover of the step.
 * - `autostart_tour_id` - If set, the tour with the given id will be automatically started if the corresponding view is loaded.
 * - `skip_if_standalone` - Skip this action when the tour is started from its menu.
 * 
 *  ##Examples:
 * 
 *  ```

 *   "on_next_step": {
 *       "widget_function": "press",
 *       "autostart_tour_id" : "news"
 *   }
 * 
 *  ```
 * 
 * @author Sergej Riel
 */

class TourOnNextStep implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;

    private bool $clickFocusedElement = false;
    private ?string $autostartTourId = null;
    private ?string $widgetFunction = null;
    private bool $skipIfStandalone = false;
    
    private $tourStep;
    
    public function __construct(TourStepInterface $tourStep, ?UxonObject $uxon = null)
    {
        $this->tourStep = $tourStep;
        if ($uxon) {
            $this->importUxonObject($uxon);
        }
    }

    /**
     * @return WidgetInterface
     */
    public function getWidget(): WidgetInterface
    {
        return $this->tourStep->getWidget();
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->tourStep->getWorkbench();
    }

    /**
     * @return string|null
     */
    public function getAutostartTourId(): ?string
    {
        return $this->autostartTourId;
    }
    
    /**
     * If set, the tour with the given id will be automatically started if the corresponding view is loaded.
     * Example: If you want the tour to continue inside a dialog:
     * Step 1: The step with this property should be the last one in this tour.
     * Step 2: Define a tour inside the dialog and set the `id` property.
     * Step 3: Set here the autostart_tour_id to the created id of the tour that should be started when the dialog is opened.
     * Step 4: Set the `widget_function` property to `press`.
     * Now if the user clicks on the "next" button in the popover of the step, the dialog will open and the tour inside the dialog will be automatically started.
     *
     * @uxon-property autostart_tour_id
     * @uxon-type string
     *
     * @param string $autostartTourId
     * @return TourOnNextStep
     */
    protected function setAutostartTourId(string $autostartTourId): TourOnNextStep
    {        
        $this->autostartTourId = $autostartTourId;
        return $this;
    }
    
    /**
     * Returns the widget function to call when the user advances from this step.
     *
     * @return string|null
     */
    public function getWidgetFunction(): ?string
    {
        return $this->widgetFunction;
    }

    /**
     * Whether a manual menu start skips this step's widget function and follow-up tour.
     *
     * @return bool
     */
    public function getSkipIfStandalone() : bool
    {
        return $this->skipIfStandalone;
    }

    /**
     * Skip this action when the user starts the tour from its menu.
     * Neither `widget_function` nor `autostart_tour_id` is executed for this step.
     * The step itself is still shown, and "Next" proceeds to the next step as usual.
     * On the last step, "Done" ends the tour without pressing the highlighted widget.
     * Automatic continuations, URL starts and `autorun` still execute this action.
     *
     * @uxon-property skip_if_standalone
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $skip
     * @return TourOnNextStep
     */
    protected function setSkipIfStandalone(bool $skip) : TourOnNextStep
    {
        $this->skipIfStandalone = $skip;
        return $this;
    }

    /**
     * The name of the widget function to call.
     *
     * @uxon-property widget_function
     * @uxon-type string
     *
     * @param string $name
     * @return TourOnNextStep
     */
    public function setWidgetFunction(string $name) : TourOnNextStep
    {
        $name = trim($name);
        if ($name === '') {
            $this->widgetFunction = null;
            return $this;
        }
        $this->widgetFunction = StringDataType::substringBefore($name, '(', $name);
        return $this;
    }
}