<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * The profiler can be used to stop the time for things like actions, data queries, etc.
 * 
 * For example, to stop the time for an action call `->start($action)` and `->stop($action)`
 * - the latter will give you the time between these two calls in milliseconds for this
 * specific action instance.
 * 
 * @author Andrej Kabachnik
 *
 */
class Profiler implements WorkbenchDependantInterface, iCanGenerateDebugWidgets
{

    private $startMicrotime = 0;
    
    private $workbench = null;
    
    private $lapIds = [];
    
    private $lapData = [];
    
    private $msDecimals = 1;

    /**
     * 
     * @param Workbench $workbench
     * @param int $startMicrotime
     */
    public function __construct(Workbench $workbench, int $startMicrotime = null, int $msDecimals = 1)
    {
        $this->workbench = $workbench;
        $this->msDecimals = $msDecimals;
        $this->reset($startMicrotime);
    }

    /**
     * Resets the internal stopwatch
     * 
     * @param int $startMicrotime
     * @return Profiler
     */
    public function reset(int $startMicrotime = null) : Profiler
    {
        $this->startMicrotime = $startMicrotime ?? microtime(true);
        return $this;
    }
    
    /**
     * Starts the time for the given object and returns a generated lap id
     * 
     * @param mixed $subject
     * @return int
     */
    public function start($subject, string $name, string $category = null) : int
    {
        $lapId = $this->getLapId($subject);
        $this->lapData[$lapId][] = [
            'name' => $name,
            'category' => $category,
            'start' => microtime(true)
        ];
        return $lapId;
    }
    
    /**
     * Stops the time for the given object and returns it's duration
     * 
     * @param mixed $subject
     * @return float|NULL
     */
    public function stop($subject) : ?float
    {
        $lapId = $this->getLapId($subject);
        if (null !== $data = $this->lapData[$lapId]) {
            $lastIdx = count($data)-1;
            $this->lapData[$lapId][$lastIdx]['stop'] = $now = microtime(true);
            return $this->toMs($now - $data[$lastIdx]['start']);
        }
        return null;
    }
    
    /**
     * Returns the duration of a given object in milliseconds or NULL if no lap was started for it.
     * 
     * @param mixed $subject
     * @return float|NULL
     */
    public function getDuration($subject = null) : ?float
    {
        if ($subject === null) {
            return $this->getDurationTotal();
        }
        if (! $this->hasLapData($subject)) {
            return null;
        }
        $data = $this->getLapData($subject);
        $stop = $data['stop'] ?? null;
        $start = $data['start'] ?? null;
        return $start === null || $stop === null ? null : $this->toMs($stop - $start);
    }
    
    /**
     * Returns the total duration in milliseconds
     * 
     * @return float
     */
    public function getDurationTotal() : float
    {
        return $this->toMs(microtime(true) - $this->startMicrotime);
    }
    
    public function getStartTime($subject = null) : ?float
    {
        if ($subject === null) {
            return $this->toMs($this->startMicrotime);
        }
        if (! $this->hasLapData($subject)) {
            return null;
        }
        if (null !== $data = $this->getLapData($subject)) {
            return $this->toMs($data['start']);
        }
        return null;
    }
    
    public function getEndTime($subject = null) : ?float
    {
        if ($subject === null) {
            return $this->toMs(microtime(true));
        }
        if (! $this->hasLapData($subject)) {
            return null;
        }
        if (null !== $data = $this->getLapData($subject)) {
            return $this->toMs($data['end']);
        }
        return null;
    }
    
    protected function hasLapData($subject) : bool
    {
        in_array($subject, $this->lapIds);
    }
    
    protected function getLapData($subject) : ?array
    {
        return $this->lapData[$this->getLapId($subject)] ?? null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @param mixed $subject
     * @return int
     */
    protected function getLapId($subject) : int
    {
        $lapId = array_search($subject, $this->lapIds);
        if ($lapId === false) {
            $this->lapIds[] = $subject;
            $lapId = count($this->lapIds) - 1;
        }
        return $lapId;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        // Add a tab with the data sheet UXON
        $tab = $debug_widget->createTab();
        $tab->setCaption('Profiler');
        $tab->setNumberOfColumns(1);
        $htmlWidget = WidgetFactory::create($debug_widget->getPage(), 'Html', $tab);
        $htmlWidget->setWidth('100%');
        $htmlWidget->setHtml($this->buildHtmlProfilerTable($htmlWidget->getId() . '_profile'));
        $tab->addWidget($htmlWidget);
        $debug_widget->addTab($tab);
        return $debug_widget;
    }
    
    protected function buildHtmlProfilerTable(string $id) : string
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
        $totalDur = round($endTime - $startTime, $this->msDecimals);
        $minWidth = '5px';
        $milestoneSymbol = '&loz;';
        $emptySymbol = '&nbsp;';
        
        $html = <<<HTML
<style>
    #{$id} td:first-of-type, #{$id} th:first-of-type {width: 50%}
    #{$id} .waterfall-offset {overflow: visible; white-space: nowrap; display: inline-block;}
    #{$id} .waterfall-bar {background-color: lightgray; display: inline-block; overflow: visible;}
</style>

HTML;
        
        $html .= '<table id="' . $id . '" class="debug-profiler" width="100%"><thead><tr><th>Event</th><th>Duration</th></tr></thead><tbody>';
        $html .= $this->buildHtmlProfilerRow($startTime, 'Request', '0px', '100%', $totalDur . ' ms');
        
        $laps = [];
        foreach (array_keys($this->lapIds) as $lapId) {
            $lapData = $this->lapData[$lapId];
            if ($lapData === null) {
                continue;   
            }
            foreach ($lapData as $lap) {
                $laps[] = $lap;
            }
        }
        usort($laps, function($lap1, $lap2){
            return ($lap1['start'] < $lap2['start']) ? -1 : 1;
        });
        
        foreach ($laps as $lap) {
            $eventStart = $lap['start'] !== null ? $this->toMs($lap['start']) : null;
            $eventEnd = $lap['stop'] !== null ? $this->toMs($lap['stop']) : null;
            $eventOffset = round(($eventStart - $startTime) / $totalDur * 100) . '%';
            if ($eventEnd !== null) {
                $eventDur = round($eventEnd - $eventStart, $this->msDecimals);
                $eventDurPercent = round($eventDur / $totalDur * 100);
                $eventWidth = $eventDurPercent > 0 ? $eventDurPercent . '%' : $minWidth;
                $eventSymbol = $emptySymbol;
            } else {
                $eventWidth = '0px';
                $eventSymbol = $milestoneSymbol;
            }
            $html .= $this->buildHtmlProfilerRow($eventStart, $lap['name'], $eventOffset, $eventWidth, $eventSymbol, $eventDur);
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    protected function buildHtmlProfilerRow(float $start, string $name, string $cssOffset, string $cssWidth, string $symbol, float $duration = null) : string
    {
        $durationText = $duration === null ? '' : $duration . ' ms';
        return "<tr><td title=\"$start\">{$name}</td><td><span class=\"waterfall-offset\" style=\"width: {$cssOffset}\">{$durationText}</span><span class = \"waterfall-bar\" style=\"width: {$cssWidth}\">{$symbol}</span></td></tr>";
    }
    
    protected function toMs(float $microtime) : float
    {
        return round(($microtime * 1000), $this->msDecimals);
    }
}