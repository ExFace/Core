<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\DataTypes\ByteSizeDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Events\DataQueryEventInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\StringDataType;

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
    private string $name;
    
    private float $startMs = 0;
    private ?float $stopFinalMs = null;
    
    private WorkbenchInterface $workbench;
    
    private int $msDecimals = 1;
    
    private array $lines = [];

    /**
     * @param WorkbenchInterface $workbench
     * @param float|null $startMs
     * @param int $msDecimals
     * @param string $name
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
        $this->startMs = $startMs > 0 ? $startMs : $this::getCurrentTimeMs();
        $this->lines = [];
        return $this;
    }

    /**
     * Starts the time for the given object and returns a generated lap id
     * 
     * @param object|string $subject
     * @param string|null $name
     * @param string|null $category
     * @return ProfilerLap
     */
    public function start(object|string $subject, string $name = null, string $category = null) : ProfilerLap
    {
        if ($name === null) {
            if (is_string($subject)) {
                $name = $subject;
            } else {
                $name = PhpClassDataType::findClassNameWithoutNamespace($subject);
            }
        }
        $phpClass = is_object($subject) ? get_class($subject) : gettype($subject);
        $lineId = $this->getLineId($subject);
        if (null === $line = ($this->lines[$lineId] ?? null)) {
            $data = [];
            switch (true) {
                case $subject instanceof DataQueryEventInterface;
                    $data['Query'] = StringDataType::truncate($subject->toString(), 500, false, false, true, true);;
                    break;
                case $subject instanceof ActionInterface:
                    $data['Action name'] = $subject->getName();
                    break;
            }
            $line = new ProfilerLine($this, $name, $category, $phpClass, $data);
            $this->lines[$lineId] = $line;
        }
        return $line->startLap();
    }
    
    
    /**
     * Stops the time for the given object and returns it's duration
     * 
     * @param object|string $subject
     * @return ProfilerLine
     */
    public function stop(object|string $subject) : ProfilerLap
    {
        if (null === $line = $this->getLine($subject)) {
            $line = $this->start($subject);
        }
        return $line->stopLap();
    }
    
    public function stopCompletely() : Profiler
    {
        $this->stopFinalMs = $this::getCurrentTimeMs();
        return $this;
    }

    /**
     * Returns the current memory usage of this PHP script in BYTES.
     *
     * NOTE: The memory usage is determined with `memory_get_usage(true)`, which, while accurate, cannot
     * track memory usage per class. So the returned value is the total memory allocated by the application,
     * since the lap was started.
     * 
     * @return int
     */
    public static function getCurrentMemoryBytes() : int
    {
        return memory_get_usage(true);
    }
    
    /**
     * Returns the total duration in milliseconds
     * 
     * @return float
     */
    public function getTimeTotalMs() : float
    {
        return $this->getTimeStopMs() - $this->getTimeStartMs();
    }
    
    /**
     * 
     */
    public function getTimeStartMs() : float
    {
        return $this->startMs;
    }
    
    /**
     * 
     * @param mixed $subject
     * @return float|null
     */
    public function getTimeStopMs() : ?float
    {
        return $this->roundMs($this->stopFinalMs ?? $this::getCurrentTimeMs());
    }
    
    public function getMemoryConsumedBytes() : ?int
    {
        $sum = null;
        foreach ($this->getLines() as $line) {
            $sum += $line->getMemoryConsumedBytes();
        }
        return $sum;
    }
    
    /**
     * 
     * @param mixed $subject
     * @return bool
     */
    protected function hasLine($subject) : bool
    {
        return null !== ($this->lines[$this->getLineId($subject)] ?? null);
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
     * @return ProfilerLine[]
     */
    public function getLines() : array
    {
        return $this->lines;
    }
    
    public function getLine($subject) : ?ProfilerLine
    {
        $id = $this->getLineId($subject);
        return $this->lines[$id] ?? null;
    }
    
    protected function getLineId($subject) : string
    {
        return is_string($subject) || is_numeric($subject) ? $subject : spl_object_id($subject);
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
    <select id="profiler-event-category-filter">
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
    document.getElementById("profiler-event-category-filter").value = "";
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

    const dropdown = document.getElementById("profiler-event-category-filter");
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
document.getElementById("profiler-event-category-filter").addEventListener("change", function() {

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
    const selectedEventType = document.getElementById("profiler-event-category-filter").value;

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
        $startTime = $this->getTimeStartMs();
        $endTime = $this->getTimeStopMs();
        $totalDur = $this->roundMs($endTime - $startTime);
        $minWidth = '1px';
        $milestoneSymbol = '&loz;';
        $emptySymbol = '&nbsp;';
        
        $html .= '<table id="' . $id . '" class="debug-profiler" width="100%"><thead><tr><th>Event</th><th>Duration</th></tr></thead><tbody>';
        $html .= $this->buildHtmlProfilerRow($startTime, $this->getName(), '0px', 'calc(100% - 3px)', $emptySymbol, $totalDur);
        
        $lines = $this->getLines();
        usort(
            $lines, 
            function(ProfilerLine $line1, ProfilerLine $line2){
                return ($line1->getTimeStartMs() < $line2->getTimeStartMs()) ? -1 : 1;
            }
        );
        
        foreach ($lines as $line) {
            $eventStart = $this->roundMs($line->getTimeStartMs());
            $eventOffset = round(($eventStart - $startTime) / $totalDur * 100) . '%';

            if (! $line->isMilestone()) {
                $eventEnd = $this->roundMs($line->getTimeStopMs());
                $eventDur = $this->roundMs($eventEnd - $eventStart);
                $eventDurPercent = round($eventDur / $totalDur * 100);
                $eventWidth = $eventDurPercent > 0 ? 'calc(' . $eventDurPercent . '% - 3px)' : $minWidth;
                $eventSymbol = $emptySymbol;
            } else {
                $eventDur = null;
                $eventWidth = '0px';
                $eventSymbol = $milestoneSymbol;
            }
            
            if (null !== $eventMem = $line->getMemoryConsumedBytes()) {
                $eventMemFormatted = ByteSizeDataType::formatWithScale($eventMem);
            }
           
            $tooltipData = [
                'Category' => $line->getCategory(),
                'Duration' => TimeDataType::formatMs($eventDur, $this->msDecimals),
                'Memory' => $eventMemFormatted, 
                'PHP class' => $line->getPhpClass()
            ];
            $tooltipData = array_merge($tooltipData, $line->getData());

            $html .= $this->buildHtmlProfilerRow(
                $eventStart, 
                $line->getName(), 
                $eventOffset, 
                $eventWidth, 
                $eventSymbol, 
                $eventDur, 
                $eventMem, 
                $line->getCategory(), 
                $tooltipData
            );
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
     * @param float|null $duration
     * @param float|null $memory
     * @param string|null $category
     * @param array $tooltipData
     * @return string
     */
    protected function buildHtmlProfilerRow(float $start, string $name, string $cssOffset, string $cssWidth, string $symbol, float $duration = null, float $memory = null, string $category = null, array $tooltipData = []) : string
    {
        $text = TimeDataType::formatMs($duration, $this->msDecimals);
        if ($memory) {
            $text .= ($text ? ', ' : '') . ByteSizeDataType::formatWithScale($memory);
        }
        $tooltip = implode(",\n", $tooltipData);
        $tooltip = json_encode(StringDataType::replaceLineBreaks(htmlspecialchars($tooltip), "&#10;"));
        $cssClass = $category ?? '';
        return <<<HTML
    <tr class="{$cssClass}" title={$tooltip}>
        <td>{$name}</td>
        <td>
            <span class="waterfall-label">{$text}</span>
            <span class="waterfall-offset" style="width: {$cssOffset}"></span>
            <span class="waterfall-bar" style="width: {$cssWidth}">{$symbol}</span>
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

    public static function getCurrentTimeMs() : float
    {
        return microtime(true) * 1000;
    }
    
    protected function getName() : string
    {
        return $this->name;
    }
}