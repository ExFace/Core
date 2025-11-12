<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * TODO: Build it and use for the Gantt chard header adjustments
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTimelineHeader implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private $timeline;
    private ?string $interval = DataTimeline::INTERVAL_DAY;
    private ?string $format = null;
    
    public function __construct(DataTimeline $timeline, ?UxonObject $uxon = null)
    {
        $this->timeline = $timeline;
        if ($uxon) {
            $this->importUxonObject($uxon);
        }
    }

    public function getWidget(): WidgetInterface
    {
        return $this->timeline->getWidget();
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->timeline->getWorkbench();
    }
}