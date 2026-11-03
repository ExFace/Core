<?php
namespace exface\Core\CommonLogic\Log\Monolog;

use exface\Core\CommonLogic\Debugger\DebugWidgetRenderer;
use exface\Core\CommonLogic\Log\CsvLogConfig;
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
    
    private string $filenameRecordKey;
    
    private DebugWidgetRenderer $renderer;

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $debugGeneratorContextKey context key of the object generating the debug widget (sender)
     * @param string $dir folder to save the JSON files to - created if missing
     * @param string $filenameRecordKey key of the log ID in the record `extra` or `context`
     * @param string $fileSuffix
     * @param int|string|Level $minLogLevel
     */
    public function __construct(
        WorkbenchInterface $workbench,
        string $debugGeneratorContextKey,
        string $dir,
        string $filenameRecordKey,
        string $fileSuffix,
        int|string|Level $minLogLevel
    )
    {
        parent::__construct($minLogLevel);
        $this->dir = rtrim($dir, '/\\');
        $this->fileSuffix = $fileSuffix;
        $this->filenameRecordKey = $filenameRecordKey;
        $this->renderer = new DebugWidgetRenderer($workbench, $debugGeneratorContextKey);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see AbstractProcessingHandler::write
     */
    protected function write(LogRecord $record): void
    {
        $filePath = $this->dir . '/' . $this->getLogId($record) . $this->fileSuffix;
        
        // The file name is the log ID, so an existing file already holds the details of this entry - e.g.
        // written by another logger sharing the folder. Checking first also saves the rendering effort.
        if (is_file($filePath)) {
            return;
        }
        
        $json = $this->renderer->render($record->context, $this->getFallbackData($record));
        $this->publish($filePath, $json);
    }
    
    /**
     * Reads the log ID from `extra` (moved there by the `IdProcessor`) or from `context` (if the processor
     * did not run yet).
     * 
     * Security: the ID becomes part of a file path, so anything but a plain token is rejected to prevent
     * path traversal.
     * 
     * @param LogRecord $record
     * @return string
     * @throws \RuntimeException if the ID is missing or not a plain token
     */
    protected function getLogId(LogRecord $record): string
    {
        $key = $this->filenameRecordKey;
        $logId = $record->extra[$key] ?? $record->context[$key] ?? null;
        
        if (! is_string($logId) || preg_match(self::LOG_ID_PATTERN, $logId) !== 1) {
            throw new \RuntimeException(
                'Cannot save debug widget: missing or invalid log ID in record key "' . $key . '" (got ' 
                . get_debug_type($logId) . ').'
            );
        }
        
        return $logId;
    }
    
    /**
     * Returns the record data, that the renderer dumps if no proper debug widget can be created.
     * 
     * @param LogRecord $record
     * @return array
     */
    protected function getFallbackData(LogRecord $record): array
    {
        return [
            'message' => $record->message,
            'level' => $record->level->getName(),
            'channel' => $record->channel,
            'datetime' => $record->datetime->format(CsvLogConfig::getDateFormat()),
            'extra' => $record->extra
        ];
    }
    
    /**
     * Validates the JSON and publishes it atomically under `$filePath`.
     * 
     * The content is not `fsync`ed: atomicity protects against crashed processes, not against power loss.
     * Syncing every detail file would make flushing a large log buffer very slow.
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