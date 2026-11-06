<?php
namespace exface\Core\CommonLogic\Log\Monolog;

use exface\Core\CommonLogic\Debugger\DebugWidgetRenderer;
use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler to save debug widgets as JSON files: one file per log entry, named after its log ID.
 * 
 * Each file is published atomically: the JSON is written to a temp file in the target folder first and
 * then linked to its final name. Readers never see a partial file, and an existing file is never
 * overwritten or appended to.
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
    
    private const TEMP_FILE_PREFIX = 'tmp';
    
    private string $dir;

    private string $fileSuffix;
    
    private string $logEntryContextKey;
    
    private DebugWidgetRenderer $renderer;

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
     * Validates the JSON and publishes it atomically under `$filePath`.
     * 
     * Atomicity: the file is either complete or absent, even if the PHP process crashes mid-write. The content
     * only appears under its final name after it was fully written to the temp file.
     * 
     * Durability (not guaranteed): after a power loss or OS crash, the published file may be empty or
     * truncated, because the OS may not have flushed its write cache to disk yet. Only `fsync()` before
     * publishing would prevent this. It is skipped on purpose: `fsync()` blocks until the disk confirms the
     * write, and flushing one log buffer can publish hundreds of detail files.
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
        
        $this->ensureDirectoryExists();
        $tempPath = $this->createTempFile();
        try {
            if (@file_put_contents($tempPath, $json) !== strlen($json)) {
                throw new \RuntimeException('Cannot write temp file "' . $tempPath . '"' . $this->getLastErrorSuffix());
            }
            $this->moveIntoPlace($tempPath, $filePath);
        } finally {
            // After link() the temp file is just a second name for the published file, so removing it is safe.
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
    
    /**
     * Moves the temp file to its final name without ever replacing an existing file.
     * 
     * `link()` is atomic and fails if the target exists. `rename()` is only a fallback for filesystems
     * without hard links (e.g. FAT or some network shares), because it replaces an existing target.
     * 
     * @param string $tempPath
     * @param string $filePath
     * @return void
     * @throws \RuntimeException
     */
    private function moveIntoPlace(string $tempPath, string $filePath): void
    {
        if (@link($tempPath, $filePath)) {
            return;
        }
        
        // Another process published the same log ID first - its content describes the same entry.
        if (is_file($filePath)) {
            return;
        }
        
        if (@rename($tempPath, $filePath)) {
            return;
        }
        
        throw new \RuntimeException('Cannot publish debug widget "' . $filePath . '"' . $this->getLastErrorSuffix());
    }
    
    /**
     * Creates an empty temp file inside the target folder.
     * 
     * The temp file must be on the same filesystem as the target: otherwise `link()` fails and `rename()`
     * is no longer atomic. `tempnam()` silently falls back to the system temp folder, hence the check.
     * 
     * @return string
     * @throws \RuntimeException
     */
    private function createTempFile(): string
    {
        $tempPath = @tempnam($this->dir, self::TEMP_FILE_PREFIX);
        if ($tempPath === false) {
            throw new \RuntimeException('Cannot create temp file in "' . $this->dir . '"' . $this->getLastErrorSuffix());
        }
        
        if (realpath(dirname($tempPath)) !== realpath($this->dir)) {
            @unlink($tempPath);
            throw new \RuntimeException('Cannot create temp file in "' . $this->dir . '": folder not writable.');
        }
        
        return $tempPath;
    }
    
    /**
     * Creates the target folder if needed.
     * 
     * Concurrent requests may create the same folder, so a failed `mkdir()` is only an error if the folder
     * still does not exist afterward. Permissions match the former Monolog `StreamHandler` (0777 minus umask).
     * 
     * @return void
     * @throws \RuntimeException
     */
    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->dir)) {
            return;
        }
        
        if (! @mkdir($this->dir, 0777, true) && ! is_dir($this->dir)) {
            throw new \RuntimeException('Cannot create folder "' . $this->dir . '"' . $this->getLastErrorSuffix());
        }
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
    
    /**
     * Returns the last suppressed PHP warning as message suffix to explain filesystem failures.
     * 
     * @return string
     */
    private function getLastErrorSuffix(): string
    {
        $error = error_get_last();
        return $error === null ? '.' : ': ' . $error['message'];
    }
}