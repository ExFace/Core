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
            $line = $this->start($subject)->getProfilerLine();
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

    public function getTimeTotalMs() : float
    {
        $sum = 0;
        foreach ($this->getLines() as $line) {
            $sum += $line->getTimeTotalMs();
        }
        return $sum;
    }
    
    /**
     * Returns the total duration in milliseconds
     * 
     * @return float
     */
    public function getTimeElapsedMs() : float
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
        return $this->stopFinalMs ?? $this::getCurrentTimeMs();
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
    #{$id} td:first-of-type, #{$id} th:first-of-type {width: 40%}
    #{$id} td:last-of-type, #{$id} th:last-of-type {width: 10%}
    #{$id} .waterfall-offset {overflow: visible; white-space: nowrap; display: inline-block;}
    #{$id} .waterfall-bar {background-color: lightgray; display: inline-block; overflow: visible;}
    #{$id} td:last-of-type .waterfall-bar {background-color: sandybrown;}
    #{$id} .waterfall-label {display: block; position: absolute;}

    #{$id} .ps-table-control-container{margin-bottom: 10px;}
    #{$id} .profiler-table-cell-hidden {display: none;}
    #{$id} .spacer-right{margin-right: 25px;}
    #{$id} .table-background-highlight {background-color: #e2d7ed;}
</style>

<div class="ps-table-control-container">
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
    const table = document.getElementById("{$id}");
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
    const table = document.getElementById("{$id}");
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
    const table = document.getElementById("{$id}");
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
    const table = document.getElementById("{$id}");
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
document.getElementById('{$id}').addEventListener('mouseover', function(event) {
  
    if (event.target.tagName.toLowerCase() === 'td') {
        const row = event.target.closest('tr'); // Find the closest row

        row.classList.add('table-background-highlight');
    }
});

// remove highlight on mouseleave
document.getElementById('{$id}').addEventListener('mouseout', function(event) {
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
        $totalDur = $endTime - $startTime;
        $totalMem = $this->getMemoryConsumedBytes();
        $minWidth = '1px';
        $milestoneSymbol = '&loz;';
        $emptySymbol = '&nbsp;';
        
        $html .= <<<HTML
<table id="{$id}" class="debug-profiler" width="100%">
    <thead>
        <tr>
            <th>Event</th>
            <th>Duration</th>
            <th>Memory</th>
        </tr>
    </thead>
<tbody>
HTML;
        $html .= $this->buildHtmlProfilerRow($this->getName(), '0px', 'calc(100% - 3px)', $emptySymbol, $totalDur);
        
        $lines = $this->getLines();
        usort(
            $lines, 
            function(ProfilerLine $line1, ProfilerLine $line2){
                return ($line1->getTimeStartMs() < $line2->getTimeStartMs()) ? -1 : 1;
            }
        );
        
        foreach ($lines as $line) {
            $eventStart = $line->getTimeStartMs();
            $eventOffset = floor(($eventStart - $startTime) / $totalDur * 100) . '%';

            if (! $line->isMilestone()) {
                $eventEnd = $line->getTimeStopMs();
                $eventLen = $eventEnd - $eventStart;
                $eventLenPercent = round($eventLen / $totalDur * 100);
                $eventWidth = $eventLenPercent > 0 ? 'calc(' . $eventLenPercent . '% - 3px)' : $minWidth;
                $eventSymbol = $emptySymbol;
            } else {
                $eventWidth = '0px';
                $eventSymbol = $milestoneSymbol;
            }
            
            if (null !== $eventMem = $line->getMemoryConsumedBytes()) {
                $eventMemFormatted = ByteSizeDataType::formatWithScale($eventMem);
            }
           
            $tooltipData = [
                'Category' => $line->getCategory(),
                'Duration' => TimeDataType::formatMs($line->getTimeTotalMs(), $this->msDecimals),
                'Memory' => $eventMemFormatted, 
                'PHP class' => $line->getPhpClass()
            ];
            if ($line->countLaps() > 1) {
                $tooltipData['Calls'] = $line->countLaps();
                $tooltipData['Avg. time per call'] = TimeDataType::formatMs($line->getTimeAvgMs());
                $tooltipData['Avg. memory per call'] = ByteSizeDataType::formatWithScale($line->getMemoryAvgBytes());
            }
            $tooltipData = array_merge($tooltipData, $line->getData());

            $html .= $this->buildHtmlProfilerRow(
                $line->getName(), 
                $eventOffset, 
                $eventWidth, 
                $eventSymbol, 
                $line->getTimeTotalMs(), 
                $eventMem, 
                $totalMem,
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
     * @param float|null $barText
     * @param float|null $eventMem
     * @param string|null $category
     * @param array $tooltipData
     * @return string
     */
    protected function buildHtmlProfilerRow(
        string $name, 
        string $cssOffset, 
        string $cssWidth, 
        string $symbol, 
        float  $barText = null, 
        int    $eventMem = null, 
        int    $totalMem = null,
        string $category = null, 
        array  $tooltipData = []
    ) : string
    {
        $eventText = TimeDataType::formatMs($barText, $this->msDecimals);
        if ($eventMem) {
            $memWidth = round($eventMem / $totalMem * 100) . '%';
            $memText =  ByteSizeDataType::formatWithScale($eventMem);
        }
        $tooltip = '';
        foreach ($tooltipData as $label => $value) {
            $tooltip .= $label . ': ' . $value . "\n";
        }
        $tooltip = json_encode(trim(StringDataType::replaceLineBreaks(htmlspecialchars($tooltip), "&#10;")));
        $cssClass = $category ?? '';
        return <<<HTML
    <tr class="{$cssClass}" title={$tooltip}>
        <td>{$name}</td>
        <td>
            <span class="waterfall-label">{$eventText}</span>
            <span class="waterfall-offset" style="width: {$cssOffset}"></span>
            <span class="waterfall-bar" style="width: {$cssWidth}">{$symbol}</span>
        </td>
        <td>
            <span class="waterfall-label">{$memText}</span>
            <span class="waterfall-bar" style="width: {$memWidth}">&nbsp;</span>
        </td>
    </tr>
HTML;
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