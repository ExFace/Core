<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 * This class represents a tour definition that can be used to guide users through the application.
 * The final tour build happens inside the implementation of the TourDriverInterface,
 * which combines this tour definition with the tour steps (TourStep.php) to create the final tour that can be displayed to the user.
 * 
 * Every tour consists of multiple steps, which are defined in the TourStep class. 
 * Each step can belong to multiple tours via the "waypoints" property.
 * 
 * ## Examples
 * 
 * ```
 * "tours": [
 *      {
 *          "title": "News",
 *          "waypoints_route": "news",
 *          "show_progress": true
 *      },
 *      {
 *          "title": "Tutorials 1 & 2",
 *          "waypoints_route": "tutorial1&tutorial2",
 *          "disable_active_interaction": true
 *      }
 * ]
 * ```
 * 
 * @author Sergej Riel
 */
class Tour implements TourInterface, iHaveIcon
{
    use ICanBeConvertedToUxonTrait;
    use IHaveIconTrait;
    
    private WidgetInterface $widget;
    private ?string $title = null;
    private ?string $waypointsRoute = null;
    private ?string $description = null;
    private bool $showProgress = false;
    private bool $disableActiveInteraction = false;
    private bool $autorun = false;
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
    }

    /**
     * @inheritDoc
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * @inheritDoc
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }

    /**
     * {@inheritDoc}
     * @see TourInterface::getTitle()
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The title of the step
     * 
     * @uxon-property title
     * @uxon-type string
     * @uxon-required true
     * @uxon-translatable true
     * 
     * @param string $title
     * @return TourStepInterface
     */
    protected function setTitle(string $title): TourInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see TourInterface::getSteps()
     */
    public function getSteps(TourDriverInterface $driver): array
    {
        return $driver->getTourSteps($this);
    }

    /**
     * @see TourInterface::getWaypointsRoute()
     */
    public function getWaypointsRoute(): string
    {
        return $this->waypointsRoute ?? '~all';
    }

    /**
     * Route definition consisting of one or multiple waypoints concatenated with `&`
     * 
     * @uxon-property waypoints_route
     * @uxon-type string
     * 
     * @param string $route
     * @return $this
     */
    protected function setWaypointsRoute(string $route) : TourInterface
    {
        $this->waypointsRoute = $route;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see TourInterface::getDescription()
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    //TODO: Improve the documentation here. Currently, the tour description is not used in the UI
    // because the MenuDropdown, which displays the tour list, cannot display descriptions.
    /**
     * The description of the tour.
     * 
     * @uxon-property description
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @param string $description
     * @return TourInterface
     */
    protected function setDescription(string $description) : TourInterface
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return bool
     */
    public function getShowProgress() : bool
    {
        return $this->showProgress;
    }

    /**
     * If true, a progress indicator will appear in the tour popover, which indicates the current step number and the total number of steps in the tour.
     * 
     * @uxon-property show_progress
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $showProgress
     * @return TourInterface
     */
    protected function setShowProgress(bool $showProgress) : TourInterface
    {
        $this->showProgress = $showProgress;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisableActiveInteraction() : bool
    {
        return $this->disableActiveInteraction;
    }

    /**
     * Disable active interaction with the highlighted UI spot while a tour is active.
     * The tour can still be closed by the user by clicking outside, 
     * but they cannot click on the highlighted UI element to trigger its action while the tour is active.
     * 
     * @uxon-property disable_active_interaction
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $disableActiveInteraction
     * @return TourInterface
     */
    protected function setDisableActiveInteraction(bool $disableActiveInteraction) : TourInterface
    {
        $this->disableActiveInteraction = $disableActiveInteraction;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAutorun() : bool
    {
        return $this->autorun;
    }
    
    /**
     * If true, the tour will be automatically started when the view is loaded.
     * 
     * @uxon-property autorun
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $autorun
     * @return TourInterface
     */
    protected function setAutorun(bool $autorun = false) : TourInterface
    {
        $this->autorun = $autorun;
        return $this;
    }
}