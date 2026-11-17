<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\CommonLogic\Log\Logger;
use exface\Core\DataTypes\ServerSoftwareDataType;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\LogRecord;
use Monolog\Level;
use Monolog\ResettableInterface;

/**
 * Writes the detail file of a record first, then its CSV row, preserving the row if details fail.
 * 
 * Used instead of a Monolog `GroupHandler`, where both handlers are run independently. Unlike the `GroupHandler`, this handler guarantees that:
 * - Detail publication is attempted before the CSV row is written.
 * - Failed details are noted on the original CSV row and reported in a separate CSV error entry.
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
 * - Detail file fails: the original CSV row is kept with a short note. A separate CSV error entry includes
 *   the original log ID, the failure reason and the operating system user. It bypasses detail generation
 *   to avoid recursion. The failure is also reported via `error_log()`.
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
     * Records are only handled if both handlers accept their level.
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
        
        $detailsError = null;
        try {
            $this->detailHandler->handle(clone $record);
        } catch (\Throwable $e) {
            $detailsError = $e;
            $record = $record->with(message: $record->message . ' [Writing log details failed.]');
        }
        
        $this->csvHandler->handle(clone $record);
        if ($detailsError !== null) {
            $this->reportDetailsFailure($record, $detailsError);
        }
        
        return false === $this->bubble;
    }
    
    /**
     * Reports failed details directly to CSV and the PHP error log without attempting more details.
     * 
     * The diagnostic has its own ID and shows the OS account rather than the application user.
     * Request and action identifiers are retained to link it to the original operation.
     * 
     * @param LogRecord $record
     * @param \Throwable $e
     * @return void
     */
    private function reportDetailsFailure(LogRecord $record, \Throwable $e): void
    {
        $originalId = $record->extra[CsvLogConfig::KEY_ID] ?? '(no id)';
        $osUser = ServerSoftwareDataType::getOsUser() ?? '(unknown OS user)';
        $message = 'Cannot write details for log entry ' . $originalId
            . ' as OS user "' . $osUser . '" (PHP SAPI: ' . PHP_SAPI . '): ' . $e->getMessage();
        $extra = $record->extra;
        $extra[CsvLogConfig::KEY_ID] = Logger::generateLogId();
        $extra[CsvLogConfig::KEY_USER_NAME] = $osUser;
        error_log('CsvWithDetailsHandler: ' . $message . ' Original entry: '
            . $record->level->getName() . ' "' . $record->message . '"');
        $this->csvHandler->handle($record->with(
            level: Level::Error,
            message: $message,
            context: [],
            extra: $extra
        ));
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