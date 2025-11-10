<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTimelineView implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;
    
    const GRANULARITY_DAYS = 'days';
    const GRANULARITY_HOURS = 'hours';
    const GRANULARITY_WEEKS = 'weeks';
    const GRANULARITY_MONTHS = 'months';
    const GRANULARITY_YEARS = 'years';
    
    private $timeline;
    
    private ?string $name = null;
    private ?string $description = null;
    
    private $granularity = null;
    private ?string $columnWidth = null;
    
    private ?array $headerLines = null;
    private ?UxonObject $headerLinesUxon = null;
    
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
    
    protected function getColumnWidth() : WidgetDimension
    {
        return $this->columnWidth;
    }

    /**
     * Width of the smallest visible columns (= granularity)
     * 
     * @uxon-property column_width
     * @uxon-type string
     * 
     * @param string $width
     * @return $this
     */
    protected function setColumnWidth(string $width) : DataTimelineView
    {
        $this->columnWidth = new WidgetDimension($this->getWorkbench(), $width);
        return $this;
    }
}