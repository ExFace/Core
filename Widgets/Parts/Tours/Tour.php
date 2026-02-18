<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;

class Tour implements TourInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private WidgetInterface $widget;
    private ?string $title = null;
    private ?string $waypointRoute = null;
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
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
     * @see TourInterface::getWaypointRoute()
     */
    public function getWaypointRoute(): string
    {
        // TODO: Implement getWaypoints() method.
        return 'news';
    }

    /**
     * Route definition consisting of one or multiple waypoints concatenated with `&`
     * 
     * @uxon-property waypoints
     * @uxon-type string
     * 
     * @param string $route
     * @return $this
     */
    protected function setWaypoints(string $route) : TourInterface
    {
        $this->waypointRoute = $route;
        return $this;
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
     * @see TourInterface::getDescription()
     */
    public function getDescription(): string
    {
        // TODO: Implement getDescription() method.
        return 'Test tour description';
    }
}