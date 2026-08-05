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
 * - `function` - The name of the widget function to call when the user clicks on the "next" button in the popover of the step.
 * - `autostart_tour_id` - If set, the tour with the given id will be automatically started if the corresponding view is loaded.
 * 
 *  ##Examples:
 * 
 *  ```

 *   "on_next_step_function": {
 *       "function": "press",
 *       "autostart_tour_id" : "news"
 *   }
 * 
 *  ```
 * 
 * @author Sergej Riel
 */

class TourOnNextStepFunction implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;

    private bool $clickFocusedElement = false;
    private ?string $autostartTourId = null;
    private ?string $functionName = null;
    
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
     * Step 4: Set the `function` property to `press`.
     * Now if the user clicks on the "next" button in the popover of the step, the dialog will open and the tour inside the dialog will be automatically started.
     *
     * @uxon-property autostart_tour_id
     * @uxon-type string
     *
     * @param string $autostartTourId
     * @return TourOnNextStepFunction
     */
    protected function setAutostartTourId(string $autostartTourId): TourOnNextStepFunction
    {        
        $this->autostartTourId = $autostartTourId;
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallWidgetFunction::getFunctionName()
     */
    public function getFunctionName(): ?string
    {
        return $this->functionName;
    }

    /**
     * The name of the widget function to call.
     *
     * @uxon-property function
     * @uxon-type string
     *
     * @param string $name
     * @return TourOnNextStepFunction
     */
    public function setFunction(string $name) : TourOnNextStepFunction
    {
        $name = trim($name);
        if ($name === '') {
            $this->functionName = null;
            return $this;
        }
        $this->functionName = StringDataType::substringBefore($name, '(', $name);
        return $this;
    }
}