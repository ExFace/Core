<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Exceptions\DataSheets\DataSheetReadError;
use exface\Core\Interfaces\DataSheets\DataCollectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Intercepts data reads for a given object, register new columns are being read compared to a given base sheet and 
 * prefetches data for these column for the entire base sheet at once.
 * 
 * This class can be very helpful for row-by-row operations, that might require additional data to be read. If truly
 * working row-by-row reading additional data will require as many reads as there are rows. The prefetcher can intercept
 * these reads and replace all of them with a single read for the entire base sheet.
 * 
 * For example, if a formula calls some code, that need to load additional data, the resulting data reads will be
 * performed for every row in the data the formula is applied to because formulas are evaluated per row. Using a
 * prefetcher in the formula can help: the prefetcher will intercept reads while it is active (between `start()` and
 * `stop()` calls). When the formula is applied to the first row, new columns will be read for the entire data, not
 * only for the first row. Thus, when the second row is processed by the formula, no additional reads will be required.
 * The prefetcher will still be watching reads and will add new column if there are any others required for subsequent
 * rows.
 *
 * @see DataCollectorInterface
 */
class DataReadPrefetcher implements WorkbenchDependantInterface
{
    private MetaObjectInterface $object;
    private ?DataSheetInterface $baseSheet = null;
    private array $secondarySheetsToEnrich = [];
    private DataCollectorInterface $missingDataCollector;
    
    private bool $prefetchOnEveryRead = true;
    
    public function __construct(MetaObjectInterface $object, DataSheetInterface $baseSheet)
    {
        $this->object = $object;
        $this->baseSheet = $baseSheet;
        $this->missingDataCollector = new DataCollector($object);
    }
    
    public function start() : DataReadPrefetcher
    {
        $this->getWorkbench()->eventManager()->addListener(OnReadDataEvent::getEventName(), [$this, 'onReadFillCollector']);
        return $this;
    }
    
    public function stop() : DataReadPrefetcher
    {
        $this->getWorkbench()->eventManager()->removeListener(OnReadDataEvent::getEventName(), [$this, 'onReadFillCollector']);
        return $this;
    }
    
    public function onReadFillCollector(OnReadDataEvent $event) : void
    {
        $eventData = $event->getDataSheet();
        if ($eventData->getMetaObject() !== $this->object) {
            return;
        }

        foreach ($eventData->getColumns() as $eventCol) {
            if ($this->baseSheet === null || ! $this->baseSheet->getColumns()->getByExpression($eventCol->getExpressionObj())) {
                $this->missingDataCollector->addExpression($eventCol->getExpressionObj());
            }
        }

        if ($this->willPrefetchOnEveryRead() === true) {
            $this->onReadPrefetch($event);
        }
    }
    
    protected function onReadPrefetch(OnReadDataEvent $event) : void
    {
        if ($this->missingDataCollector->isEmpty()) {
            return;
        }
        
        $eventData = $event->getDataSheet();
        if ($eventData->isEmpty()) {
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();
        if (! $eventData->hasUidColumn(true)) {
            throw new DataSheetReadError($eventData, 'Cannot use collector prefetch on data without UIDs');
        }
        $this->enrichData($eventData);
        foreach ($this->getDataSheetsToEnrich() as $secondarySheet) {
            $this->enrichData($secondarySheet);
        }
    }
    
    public function enrichData(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $collector = $this->collectRequiredData();
        $collectorUidCol = $collector->getUidColumn();
        $eventUidCol = $dataSheet->getUidColumn();
        foreach ($eventUidCol->getValues() as $eventRowIdx => $uid) {
            $collectorRowIdx = $collectorUidCol->findRowByValue($uid);
            foreach ($this->missingDataCollector->getRequiredColumns() as $collectorCol) {
                $colVal = $collectorCol->getValue($collectorRowIdx);
                $dataSheet->setCellValue($collectorCol->getName(), $eventRowIdx, $colVal);
            }
        }
        return $dataSheet;
    }
    
    public function hasFoundMissingColumns() : bool
    {
        return ! $this->missingDataCollector->isEmpty();
    }
    
    public function collectRequiredData() : DataCollectorInterface
    {
        if (! $this->missingDataCollector->isEmpty() && ! $this->missingDataCollector->isLoaded()) {
            if ($this->object->hasUidAttribute()) {
                $this->missingDataCollector->addAttribute($this->object->getUidAttribute());
            }
            $this->missingDataCollector->enrich($this->baseSheet);
        }
        return $this->missingDataCollector;
    }

    /**
     * @param bool $trueOrFalse
     * @return $this
     */
    public function setPrefetchOnEveryRead(bool $trueOrFalse) : DataReadPrefetcher
    {
        $this->prefetchOnEveryRead = $trueOrFalse;
        return $this;
    }

    /**
     * @return bool
     */
    public function willPrefetchOnEveryRead() : bool
    {
        return $this->prefetchOnEveryRead;
    }
    
    public function addDataSheetToEnrich(DataSheetInterface $dataSheet) : DataReadPrefetcher
    {
        $this->secondarySheetsToEnrich[] = $dataSheet;
        return $this;
    }

    /**
     * @return DataSheetInterface[]
     */
    protected function getDataSheetsToEnrich() : array
    {
        return $this->secondarySheetsToEnrich;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->object->getWorkbench();
    }
}