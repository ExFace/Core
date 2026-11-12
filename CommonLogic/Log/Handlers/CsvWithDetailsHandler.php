<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\LogRecord;
use Monolog\ResettableInterface;

/**
 * Writes the detail file of a record first and appends its CSV row only after the detail file was published.
 * 
 * Used instead of a Monolog `GroupHandler`, where both handlers are run independently. Unlike the `GroupHandler`, this handler guarantees that:
 * - The detail file is published before the CSV row is written.
 * - A CSV row without its detail file is never written. (A detail file without a CSV row is acceptable: retention cleans it up)
 * 
 * ### Batches
 * 
 * Batches are handled record by record. `GroupHandler::handleBatch()` would pass the whole batch to one
 * handler before the next, so rows of later records could be written before their detail files.
 * 
 * ### Levels
 * 
 * A record is only handled if both handlers accept it. Both handlers must use the same level - the
 * constructor throws on a mismatch, if the handlers expose their levels (`AbstractHandler`).
 * 
 * ### Failures
 * 
 * - Detail file fails: only this record is affected. Its CSV row is skipped and the record is reported via
 *   `error_log()`. The remaining records (e.g. of a `FingersCrossedHandler` flush) are still written.
 * - CSV row fails: the exception propagates as before, because a broken CSV file affects all records. The
 *   already published detail file stays as an orphan.
 * 
 * @author Georg Bieger
 */
class CsvWithDetailsHandler extends Handler implements ProcessableHandlerInterface, ResettableInterface
{
    use ProcessableHandlerTrait;
    
    private CsvHandler $csvHandler;
    
    private HandlerInterface $detailHandler;

    private bool $bubble;

    /**
     *
     * @param HandlerInterface $detailHandler publishes the detail files - must throw if a file cannot be published
     * @param CsvHandler $csvHandler appends the CSV rows
     * @param bool $bubble
     * @throws \InvalidArgumentException if both handlers expose their levels and the levels differ
     */
    public function __construct(
        HandlerInterface $detailHandler,
        CsvHandler $csvHandler,
        bool $bubble = true
    )
    {
        // A level mismatch is a configuration error: fail once here instead of silently dropping records later.
        // Handlers without `getLevel()` cannot be checked and rely on `isHandling()` alone.
        if ($detailHandler instanceof AbstractHandler && $detailHandler->getLevel() !== $csvHandler->getLevel()) {
            throw new \InvalidArgumentException(
                'CsvWithDetailsHandler requires the same level for both handlers, got "'
                . $detailHandler->getLevel()->getName() . '" for the detail handler and "'
                . $csvHandler->getLevel()->getName() . '" for the CSV handler.'
            );
        }
        
        $this->detailHandler = $detailHandler;
        $this->csvHandler = $csvHandler;
        $this->bubble = $bubble;
    }

    /**
     * Records are only handled if both handlers accept them: a CSV row must never be written without its
     * detail file, and a detail file without a row would be an orphan.
     * 
     * {@inheritDoc}
     * @see HandlerInterface::isHandling()
     */
    public function isHandling(LogRecord $record): bool
    {
        return $this->csvHandler->isHandling($record) && $this->detailHandler->isHandling($record);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see HandlerInterface::handle()
     */
    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) {
            return false;
        }
        
        if (\count($this->processors) > 0) {
            $record = $this->processRecord($record);
        }
        
        try {
            $this->detailHandler->handle(clone $record);
        } catch (\Throwable $e) {
            $this->reportSkippedRow($record, $e);
            return false === $this->bubble;
        }
        
        $this->csvHandler->handle(clone $record);
        
        return false === $this->bubble;
    }
    
    /**
     * Reports a record, whose CSV row was skipped, to the PHP error log, so the entry is not lost entirely.
     * 
     * Uses `error_log()` directly instead of the workbench logger: logging from within a log handler could
     * recurse into this handler. The message is included because it is not persisted anywhere else.
     * 
     * @param LogRecord $record
     * @param \Throwable $e
     * @return void
     */
    private function reportSkippedRow(LogRecord $record, \Throwable $e): void
    {
        error_log(
            'CsvWithDetailsHandler: skipped CSV row of log entry ' . ($record->extra[CsvLogConfig::KEY_ID] ?? '(no id)')
            . ', because its detail file could not be written: ' . $e->getMessage()
            . ' Original entry: ' . $record->level->getName() . ' "' . $record->message . '"'
        );
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see ResettableInterface::reset()
     */
    public function reset(): void
    {
        $this->resetProcessors();
        
        foreach ([$this->detailHandler, $this->csvHandler] as $handler) {
            if ($handler instanceof ResettableInterface) {
                $handler->reset();
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see HandlerInterface::close()
     */
    public function close(): void
    {
        parent::close();
        $this->detailHandler->close();
        $this->csvHandler->close();
    }
}