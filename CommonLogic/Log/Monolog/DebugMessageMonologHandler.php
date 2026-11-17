<?php
namespace exface\Core\CommonLogic\Log\Monolog;

use exface\Core\CommonLogic\Debugger\DebugWidgetRenderer;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler to save debug widgets as JSON files: one file per log entry, named after its log ID.
 * 
 * Files are written atomically using the workbench file manager. Existing files are skipped before
 * rendering; concurrent writers for the same log ID may replace the file with equivalent details.
 * 
 * Failures throw a plain `\RuntimeException` - not an exface exception, because those generate log
 * entries and debug widgets themselves and could recurse into this handler.
 * 
 * @author Andrej Kabachnik
 *
 */
class DebugMessageMonologHandler extends AbstractProcessingHandler
{
    // Log IDs end up in a file path - only allow characters that cannot traverse folders.
    private const LOG_ID_PATTERN = '/^[A-Za-z0-9_-]+$/';
    
    private string $dir;

    private string $fileSuffix;
    
    private string $logEntryContextKey;
    
    private DebugWidgetRenderer $renderer;

    private Filemanager $filemanager;

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $dir folder to save the JSON files to - created if missing
     * @param string $logEntryContextKey context key of the `LogEntryInterface` in the Monolog record
     * @param string $fileSuffix
     * @param int|string|Level $minLogLevel
     */
    public function __construct(
        WorkbenchInterface $workbench,
        string $dir,
        string $logEntryContextKey,
        string $fileSuffix,
        int|string|Level $minLogLevel
    )
    {
        parent::__construct($minLogLevel);
        $this->dir = rtrim($dir, '/\\');
        $this->fileSuffix = $fileSuffix;
        $this->logEntryContextKey = $logEntryContextKey;
        $this->renderer = new DebugWidgetRenderer($workbench);
        $this->filemanager = $workbench->filemanager();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see AbstractProcessingHandler::write
     */
    protected function write(LogRecord $record): void
    {
        $entry = $this->getLogEntry($record);
        $filePath = $this->dir . '/' . $this->getLogId($entry) . $this->fileSuffix;
        
        // The file name is the log ID, so an existing file already holds the details of this entry - e.g.
        // written by another logger sharing the folder. Checking first also saves the rendering effort.
        if (is_file($filePath)) {
            return;
        }
        
        $json = $this->renderer->render($entry, $this->getFallbackData($record));
        $this->publish($filePath, $json);
    }
    
    /**
     * Reads the log entry from the record context.
     * 
     * @param LogRecord $record
     * @return LogEntryInterface
     * @throws \RuntimeException if the record does not contain a log entry
     */
    protected function getLogEntry(LogRecord $record): LogEntryInterface
    {
        $entry = $record->context[$this->logEntryContextKey] ?? null;
        if (! $entry instanceof LogEntryInterface) {
            throw new \RuntimeException(
                'Cannot save debug widget: no log entry in record context key "' . $this->logEntryContextKey 
                . '" (got ' . get_debug_type($entry) . ').'
            );
        }
        
        return $entry;
    }
    
    /**
     * Returns the ID of the log entry, which is used as file name.
     * 
     * Security: the ID becomes part of a file path, so anything but a plain token is rejected to prevent
     * path traversal.
     * 
     * @param LogEntryInterface $entry
     * @return string
     * @throws \RuntimeException if the ID is not a plain token
     */
    protected function getLogId(LogEntryInterface $entry): string
    {
        $logId = $entry->getId();
        
        if (preg_match(self::LOG_ID_PATTERN, $logId) !== 1) {
            throw new \RuntimeException('Cannot save debug widget: invalid log ID "' . $logId . '".');
        }
        
        return $logId;
    }
    
    /**
     * Returns the Monolog record data, that the renderer dumps if no proper debug widget can be created.
     * 
     * Message, level and ID are taken from the log entry by the renderer itself.
     * 
     * @param LogRecord $record
     * @return array
     */
    protected function getFallbackData(LogRecord $record): array
    {
        return [
            'channel' => $record->channel,
            'datetime' => $record->datetime->format(CsvLogConfig::getDateFormat()),
            'extra' => $record->extra
        ];
    }
    
    /**
     * Validates the JSON and writes it atomically using the standard file manager.
     * 
     * @param string $filePath
     * @param string $json
     * @return void
     * @throws \RuntimeException
     */
    protected function publish(string $filePath, string $json): void
    {
        if (! $this->isValidJson($json)) {
            throw new \RuntimeException('Cannot save debug widget "' . $filePath . '": rendered content is not valid JSON.');
        }
        
        $this->filemanager->dumpFile($filePath, $json);
    }
    
    /**
     * Checks if a string is valid JSON - with `json_validate()` (PHP 8.3+), which does not build the
     * decoded structure in memory, or with `json_decode()` on older versions.
     * 
     * @param string $json
     * @return bool
     */
    private function isValidJson(string $json): bool
    {
        if (function_exists('json_validate')) {
            return json_validate($json);
        }
        
        try {
            json_decode($json, flags: JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException) {
            return false;
        }
    }
}