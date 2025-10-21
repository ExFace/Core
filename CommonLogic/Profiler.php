<?php
namespace exface\Core\CommonLogic;

use exface\Core\DataTypes\ByteSizeDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\StringDataType;
use TextMode;
use function Sabre\Event\Loop\instance;

/**
 * The profiler can be used to stop the time for any objects (e.g. actions, data queries, etc.)
 * 
 * For example, to stop the time for an action call `->start($action)` and `->stop($action)`
 * - the latter will give you the time between these two calls in milliseconds for this
 * specific action instance.
 * 
 * The profiler can generate a debug widget with a timeline for all stopped "laps". A lap
 * is the time between a start and a stop for the same subject (object).
 * 
 * @author Andrej Kabachnik
 *
 */
class Profiler implements WorkbenchDependantInterface, iCanGenerateDebugWidgets
{
    const LAP_START = 'start';
    const LAP_STOP = 'stop';
    const LAP_MEM_START = 'memoryStart';
    const LAP_MEM_STOP = 'memoryStop';
    const LAP_NAME = 'name';
    const LAP_CATEGORY = 'category';
    const LAP_SUBJECT = 'subject';
    
    private string $name;
    
    private $startMs = 0;
    
    private $workbench = null;
    
    private $lapIds = [];
    
    private $lapData = [];
    
    private $msDecimals = 1;

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param float $startMs
     */
    public function __construct(WorkbenchInterface $workbench, float $startMs = null, int $msDecimals = 1, string $name = 'Profiler')
    {
        $this->workbench = $workbench;
        $this->msDecimals = $msDecimals;
        $this->reset($startMs);
        $this->name = $name;
    }

    /**
     * Resets the internal stopwatch
     * 
     * @param int $startMs
     * @return Profiler
     */
    public function reset(float $startMs = null) : Profiler
    {
        $this->startMs = $startMs > 0 ? $startMs : $this->nowMs();
        return $this;
    }

    /**
     * Starts the time for the given object and returns a generated lap id
     *
     * @param string|object $subject
     * @param string|null   $name
     * @param string|null   $category
     * @return int
     */
    public function start($subject, string $name = null, string $category = null) : int
    {
        if ($name === null && is_string($subject)) {
            $name = $subject;
        }
        $lapId = $this->getLapId($subject);
        $this->lapData[$lapId] = [
            self::LAP_NAME => $name,
            self::LAP_CATEGORY => $category,
            self::LAP_START => $this->nowMs(),
            self::LAP_MEM_START => memory_get_usage(true),
            self::LAP_SUBJECT => $subject
        ];
        return $lapId;
    }
    
    /**
     * Stops the time for the given object and returns it's duration
     * 
     * @param mixed $subject
     * @return float|null
     */
    public function stop($subject) : ?float
    {
        $lapId = $this->getLapId($subject);
        return $this->stopLap($lapId);
    }

    /**
     * @param int $lapId
     * @return float|null
     */
    public function stopLap(int $lapId) : ?float
    {
        if (null !== $data =& $this->lapData[$lapId]) {
            $data[self::LAP_STOP] = $now = $this->nowMs();
            $data[self::LAP_MEM_STOP] = memory_get_usage(true);
            return $this->roundMs($now - $data[self::LAP_START]);
        }
        return null;
    }
    
    /**
     * Returns the duration of a given object in milliseconds or NULL if no lap was started for it.
     * 
     * @param mixed $subject
     * @return float|null
     */
    public function getDurationMs($subject = null) : ?float
    {
        if ($subject === null) {
            return $this->getDurationTotal();
        }
        if (! $this->hasLapData($subject)) {
            return null;
        }
        $data = $this->getLapData($subject);
        $start = $data[self::LAP_START] ?? null;
        $stop = $data[self::LAP_STOP] ?? $this->nowMs();
        return $start === null || $stop === null ? null : $this->roundMs($stop - $start);
    }

    /**
     * Returns the total memory usage in BYTES for a given subject or NULL if no lap was started for 
     * that subject yet.
     * 
     * NOTE: The memory usage is determined with `memory_get_usage(true)`, which, while accurate, cannot
     * track memory usage per class. So the returned value is the total memory allocated by the application,
     * since the lap was started.
     * 
     * @param mixed $subject
     * @return float|null
     */
    public function getMemoryUsageBytes(mixed $subject) : ?float
    {
        $data = $this->getLapData($subject);
        if($data === null) {
            return null;
        }
        
        $start = $data[self::LAP_MEM_START];
        $stop = $data[self::LAP_MEM_STOP] ?? memory_get_usage(true);
        return $start !== null ? $stop - $start : null;
    }
    
    /**
     * Returns the total duration in milliseconds
     * 
     * @return float
     */
    public function getDurationTotal() : float
    {
        return $this->roundMs($this->nowMs() - $this->startMs);
    }
    
    /**
     * 
     * @param mixed $subject
     * @return float|null
     */
    public function getStartTime($subject = null) : ?float
    {
        if ($subject === null) {
            return $this->roundMs($this->startMs);
        }
        if (! $this->hasLapData($subject)) {
            return null;
        }
        if (null !== $data = $this->getLapData($subject)) {
            return $this->roundMs($data[self::LAP_START]);
        }
        return null;
    }
    
    /**
     * 
     * @param mixed $subject
     * @return float|null
     */
    public function getEndTime($subject = null) : ?float
    {
        if ($subject === null) {
            return $this->roundMs($this->nowMs());
        }
        if (! $this->hasLapData($subject)) {
            return null;
        }
        if (null !== $data = $this->getLapData($subject)) {
            return $this->roundMs($data['end']);
        }
        return null;
    }
    
    /**
     * 
     * @param mixed $subject
     * @return bool
     */
    protected function hasLapData($subject) : bool
    {
        return in_array($subject, $this->lapIds);
    }
    
    /**
     * 
     * @param mixed $subject
     * @return array|null
     */
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
        $lapId = array_search($subject, $this->lapIds, true);
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
    
    /**
     * 
     * @param string $id
     * @return string
     */
    protected function buildHtmlProfilerTable(string $id) : string
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
        $totalDur = round($endTime - $startTime, $this->msDecimals);
        $minWidth = '1px';
        $milestoneSymbol = '&loz;';
        $emptySymbol = '&nbsp;';
        
        $html = <<<HTML
<style>
    #{$id} td:first-of-type, #{$id} th:first-of-type {width: 50%}
    #{$id} .waterfall-offset {overflow: visible; white-space: nowrap; display: inline-block;}
    #{$id} .waterfall-bar {background-color: lightgray; display: inline-block; overflow: visible;}
    #{$id} .waterfall-label {display: block; position: absolute;}

    #ps-table-control-container{
        margin-bottom: 10px;
    }
    
    .profiler-table-cell-hidden {
        display: none;
    }

    .spacer-right{
        margin-right: 25px;
    }

    .table-background-highlight{
        background-color: #e2d7ed;
    }


</style>


<div id="ps-table-control-container">
    <input id="profiler-search-input" type="text" placeholder="Query..." name="search">
    <select id="profiler-event-type-filter">
        <option value="">all types</option> 
    </select>
    <button id="profiler-search-button">Search</button>
    <button id="profiler-reset-search-button">Reset Search</button>
</div>

<script>

// reset search and UI
function resetSearch(){
    const table = document.getElementById("DebugMessage_Tab_Html_profile");
    const hiddenRows = table.querySelectorAll("tbody tr.profiler-table-cell-hidden"); 

    hiddenRows.forEach(function(row) {
        row.classList.remove("profiler-table-cell-hidden"); 
    });

    document.getElementById("profiler-search-input").value = "";
}

document.getElementById("profiler-reset-search-button").addEventListener("click", function() {
    //clear the search field & dropdown
    resetSearch();
    document.getElementById("profiler-event-type-filter").value = "";
});

// generate dropdown with filter options from css classes
function generateEventTypeFilterOptions() {
    const table = document.getElementById("DebugMessage_Tab_Html_profile");
    const rows = table.querySelectorAll("tbody tr"); 
    const classSet = new Set();

    // add all distinct types
    rows.forEach(function(row) {
        const rowClasses = row.classList;
        rowClasses.forEach(function(cls) {
            classSet.add(cls); 
        });
    });

    const dropdown = document.getElementById("profiler-event-type-filter");
    dropdown.innerHTML = '<option value="">all types</option>';

    //add event types
    classSet.forEach(function(cls) {
        if (cls !== "") { 
            const option = document.createElement("option");
            option.textContent = cls; 
            dropdown.appendChild(option); 
        }
    });
}

// apply filters on dropdown change
document.getElementById("profiler-event-type-filter").addEventListener("change", function() {

    resetSearch();

    const selectedEventType = this.value.toLowerCase(); 
    const table = document.getElementById("DebugMessage_Tab_Html_profile");
    const rows = table.querySelectorAll("tbody tr");


    rows.forEach(function(row) {
        
        const rowClass = row.classList.value.toLowerCase(); 
        if (selectedEventType === "" || rowClass.includes(selectedEventType)) {
            row.classList.remove("profiler-table-cell-hidden"); 
        } else {
            row.classList.add("profiler-table-cell-hidden");
        }
    });
});

// search table for terms while applying selected filters
document.getElementById("profiler-search-button").addEventListener("click", function() {

    const searchQuery = document.getElementById("profiler-search-input").value.toLowerCase(); 
    const table = document.getElementById("DebugMessage_Tab_Html_profile");
    const rows = table.querySelectorAll("tbody tr"); 
    const selectedEventType = document.getElementById("profiler-event-type-filter").value;

    const searchTerms = searchQuery.split(/\s+/).filter(term => term.length > 0);

    // search each cell in each row
    rows.forEach(function(row) {
        const cells = row.getElementsByTagName("td");
        let matchFound = false;

        //apply dropdown filters
        if (selectedEventType === "" || row.classList.contains(selectedEventType)){
            
            searchTerms.forEach(function(term) {
                //search cells
                // query terms are treated as OR, any match breaks search
                for (let i = 0; i < cells.length; i++) {
                    if (cells[i].textContent.toLowerCase().includes(term)) {
                        matchFound = true;
                        break; // break if any term found
                    }
                }
            });

            if (matchFound) {
                row.classList.remove("profiler-table-cell-hidden");
            } else {
                row.classList.add("profiler-table-cell-hidden"); 
            }
        }
        
    });
});

// highlight rows on mouseover
document.getElementById('DebugMessage_Tab_Html_profile').addEventListener('mouseover', function(event) {
  
    if (event.target.tagName.toLowerCase() === 'td') {
        const row = event.target.closest('tr'); // Find the closest row

        row.classList.add('table-background-highlight');
    }
});

// remove highlight on mouseleave
document.getElementById('DebugMessage_Tab_Html_profile').addEventListener('mouseout', function(event) {
    if (event.target.tagName.toLowerCase() === 'td') {
        const row = event.target.closest('tr'); // Find the closest row

        row.classList.remove('table-background-highlight');
    }
});

generateEventTypeFilterOptions();

</script>

HTML;
        
        $html .= '<table id="' . $id . '" class="debug-profiler" width="100%"><thead><tr><th>Event</th><th>Duration</th></tr></thead><tbody>';
        $html .= $this->buildHtmlProfilerRow($startTime, $this->getName(), '0px', '100%', $emptySymbol, $totalDur);
        
        $laps = [];
        foreach (array_keys($this->lapIds) as $lapId) {
            $lapData = $this->lapData[$lapId];
            if ($lapData === null) {
                continue;   
            }
            $laps[] = $lapData;
        }
        usort($laps, function($lap1, $lap2){
            return ($lap1[self::LAP_START] < $lap2[self::LAP_START]) ? -1 : 1;
        });
        
        foreach ($laps as $lap) {
            $eventStart = $lap[self::LAP_START] !== null ? $this->roundMs($lap[self::LAP_START]) : null;
            $eventEnd = $lap[self::LAP_STOP] !== null ? $this->roundMs($lap[self::LAP_STOP]) : null;
            $eventOffset = round(($eventStart - $startTime) / $totalDur * 100) . '%';

            if ($eventEnd !== null) {
                $eventDur = round($eventEnd - $eventStart, $this->msDecimals);
                $eventDurPercent = round($eventDur / $totalDur * 100);
                $eventWidth = $eventDurPercent > 0 ? $eventDurPercent . '%' : $minWidth;
                $eventSymbol = $emptySymbol;
            } else {
                $eventDur = null;
                $eventWidth = '0px';
                $eventSymbol = $milestoneSymbol;
            }
            
            if ($lap[self::LAP_MEM_STOP]) {
                $memory = $lap[self::LAP_MEM_STOP] - $lap[self::LAP_MEM_START];
                $memoryFormatted = ByteSizeDataType::formatWithScale($memory);
            }
            
            if (null === $name = $lap[self::LAP_NAME]) {
                $subj = $lap[self::LAP_SUBJECT];
                if (is_object($subj)) {
                    if (method_exists($subj , '__toString')) {
                        $name = str_replace(["\r", "\n"], ' ', $subj->__toString());
                    } else {
                        $name = get_class($subj);
                    }
                } else {
                    if (is_array($subj)) {
                        $name = json_encode($subj);
                    } else {
                        $name = (string) $subj;
                    }
                }
                $name = StringDataType::truncate($name, 40);
            }

            /* 
               Tooltip with additional details
            */

            $subj = $lap[self::LAP_SUBJECT];
            $type = ucfirst($lap[self::LAP_CATEGORY]);
            $phpClass = is_object($subj) ? PhpClassDataType::findClassNameWithoutNamespace($subj) : '';
            
            switch (true) {
                case $type === 'Query':
                    $query = $lap[self::LAP_SUBJECT];
                    $queryStr = StringDataType::truncate($query->toString(), 500, false, false, true, true);
                    $tooltipData = <<<TEXT
Query: {$queryStr}
TEXT;
                    break;
                case $type === 'Action':
                    $action = $lap[self::LAP_SUBJECT];
                    $tooltipData = <<<TEXT
Action name: {$action->getName()}
TEXT;
                    break;
                default:
                    $tooltipData = '';
                    break;
            }
            
            $tooltipData = <<<TEXT
Category: {$type}
Duration: {$this->formatMs($eventDur)}
Memory: {$memoryFormatted}
PHP class: {$phpClass}
{$tooltipData}
TEXT;

            $html .= $this->buildHtmlProfilerRow($eventStart, $name, $eventOffset, $eventWidth, $eventSymbol, $eventDur, $memory, $lap[self::LAP_CATEGORY], $tooltipData);
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * 
     * @param float $start
     * @param string $name
     * @param string $cssOffset
     * @param string $cssWidth
     * @param string $symbol
     * @param float $duration
     * @param float $memory
     * @param string $tooltipData
     * @return string
     */
    protected function buildHtmlProfilerRow(float $start, string $name, string $cssOffset, string $cssWidth, string $symbol, float $duration = null, float $memory = null, string $category = null, string $tooltipData='') : string
    {
        $text = $this->formatMs($duration);
        if ($memory) {
            $text .= ($text ? ', ' : '') . ByteSizeDataType::formatWithScale($memory);
        }
        $tooltipData = json_encode(StringDataType::replaceLineBreaks(htmlspecialchars($tooltipData), "&#10;"));
        $cssClass = $category ?? '';
        return <<<HTML
    <tr class="{$cssClass}" title={$tooltipData}>
        <td>{$name}</td>
        <td>
            <span class="waterfall-label">{$text}</span>
            <span class="waterfall-offset" style="width: {$cssOffset}"></span>
            <span class="waterfall-bar" style="width: calc({$cssWidth} - 3px)">{$symbol}</span>
        </td>
    </tr>
HTML;
    }
    
    /**
     * 
     * @param float $milliseconds
     * @return float
     */
    protected function roundMs(float $milliseconds) : float
    {
        return round($milliseconds, $this->msDecimals);
    }

    /**
     * Formats milliseconds as "x.xx ms" or "y.yy s" depending on the scale
     * @param float|null $milliseconds
     * @return string
     */
    protected function formatMs(?float $milliseconds) : string
    {
        switch (true) {
            case $milliseconds === null:
                $formatted = '';
                break;
            case $milliseconds > 1000:
                $formatted = round($milliseconds / 1000, $this->msDecimals) . ' s';
                break;
            default:
                $formatted = $this->roundMs($milliseconds) . ' ms';
                break;
        }
        return $formatted;
    }

    protected function nowMs() : float
    {
        return microtime(true) * 1000;
    }
    
    protected function getName() : string
    {
        return $this->name;
    }
}