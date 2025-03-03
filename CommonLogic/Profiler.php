<?php
namespace exface\Core\CommonLogic;

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
    const LAP_NAME = 'name';
    const LAP_CATEGORY = 'category';
    const LAP_SUBJECT = 'subject';
    
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
    public function __construct(WorkbenchInterface $workbench, float $startMs = null, int $msDecimals = 1)
    {
        $this->workbench = $workbench;
        $this->msDecimals = $msDecimals;
        $this->reset($startMs);
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
     * @param mixed $subject
     * @return int
     */
    public function start($subject, string $name = null, string $category = null) : int
    {
        $lapId = $this->getLapId($subject);
        $this->lapData[$lapId][] = [
            self::LAP_NAME => $name,
            self::LAP_CATEGORY => $category,
            self::LAP_START => $this->nowMs(),
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
        if (null !== $data = $this->lapData[$lapId]) {
            $lastIdx = count($data)-1;
            $this->lapData[$lapId][$lastIdx][self::LAP_STOP] = $now = $this->nowMs();
            return $this->roundMs($now - $data[$lastIdx][self::LAP_START]);
        }
        return null;
    }
    
    /**
     * Returns the duration of a given object in milliseconds or NULL if no lap was started for it.
     * 
     * @param mixed $subject
     * @return float|null
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
        $stop = $data[self::LAP_STOP] ?? null;
        $start = $data[self::LAP_START] ?? null;
        return $start === null || $stop === null ? null : $this->roundMs($stop - $start);
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

    #ps-table-control-container{
        margin-bottom: 10px;
    }
    
    .profiler-table-cell-hidden {
        display: none;
    }

    .spacer-right{
        margin-right: 25px;
    }

    .svg-tooltip{
		position: absolute;
		pointer-events: none; 
		z-index: 9999;
		padding: 5px;
		background-color: rgba(0, 0, 0, 0.75);
		color: white;
		border-radius: 5px;
		visibility: hidden
		tooltip.style.color = 'white';
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

/*
    questions:
    - change tooltip data
    - on long hover -> other tooltip appears? (From powerui itself?) -> could be used instead
*/

const tooltip = document.createElement('div');
tooltip.classList.add('svg-tooltip');
document.body.appendChild(tooltip);

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

//add tooltip overlay
document.getElementById('DebugMessage_Tab_Html_profile').addEventListener('mouseover', function(event) {
  
  if (event.target.tagName.toLowerCase() === 'td') {
    const row = event.target.closest('tr'); // Find the closest row
    const cells = row.getElementsByTagName('td');
    
    tooltip.textContent = "" + row.getAttribute('data-info');
    tooltip.style.visibility = 'visible';

    // position tooltip
    const mouseX = event.pageX + 10; 
    const mouseY = event.pageY + 10; 
    tooltip.style.left = mouseX + "px";;
    tooltip.style.top = mouseY + "px";;
  }
});


document.getElementById('DebugMessage_Tab_Html_profile').addEventListener('mouseout', function(event) {
    tooltip.style.visibility = 'hidden';
});

generateEventTypeFilterOptions();

</script>

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

            /* Todo: 
                Tooltip content (subject to string) and structure (multi-line tooltip) still need to be adapted.
            */

            $subj = $lap[self::LAP_SUBJECT];
            $type = ucfirst($lap[self::LAP_CATEGORY]);
            $phpClass = PhpClassDataType::findClassNameWithoutNamespace($subj);
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
            }
            $tooltipData = <<<TEXT
Type: {$type}
PHP class: {$phpClass}
{$tooltipData}
TEXT;

            $html .= $this->buildHtmlProfilerRow($eventStart, $name, $eventOffset, $eventWidth, $eventSymbol, $eventDur, $lap[self::LAP_CATEGORY], $tooltipData);
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
     * @param string $tooltipData
     * @return string
     */
    protected function buildHtmlProfilerRow(float $start, string $name, string $cssOffset, string $cssWidth, string $symbol, float $duration = null, string $category = null, string $tooltipData='') : string
    {
        $durationText = $duration === null ? '' : $duration . ' ms';
        $tooltipData = str_replace("\\n", "&#10;", json_encode(htmlspecialchars($tooltipData)));
        $cssClass = $category ?? '';
        return "<tr class=\"{$cssClass}\" title={$tooltipData}><td>{$name}</td><td><span class=\"waterfall-offset\" style=\"width: {$cssOffset}\">{$durationText}</span><span class = \"waterfall-bar\" style=\"width: {$cssWidth}\">{$symbol}</span></td></tr>";
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

    protected function nowMs() : float
    {
        return microtime(true) * 1000;
    }
}