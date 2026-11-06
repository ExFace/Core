<?php

namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\CommonLogic\Log\Monolog\CsvFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use InvalidArgumentException;

/**
 * Stores to a csv file
 *
 * Can be used to store large data volumes to physical files and import them later into another system that can handle CSV.
 * 
 * Every record is written as one complete line:
 * - Rows must be produced by `CsvFormatter` (enforced in `setFormatter()`), which defines the column layout
 *   via `CsvLogConfig`.
 * - The line is built in memory first and verified by parsing it back with the settings from `CsvLogConfig`.
 *   If the round-trip does not reproduce the formatted row, a fallback row is written instead.
 * - The line is written with a single `fwrite()` while holding an exclusive `flock()` (enabled by default),
 *   so concurrent processes cannot interleave their lines. Readers do not lock: `LogCsvBuilder` ignores an
 *   incomplete last record, `LogCleaner` re-checks and repairs under the same lock.
 * 
 * @author GitHub Copilot, Georg Bieger
 */
class CsvHandler extends StreamHandler
{
    const PLACEHOLDER_ROW_FAILED = '[row encoding failed]';
    
    // Columns kept in the fallback row. They are produced by the logger itself and are safe to write.
    const FALLBACK_COLUMNS = [
        CsvLogConfig::KEY_ID,
        CsvLogConfig::KEY_REQUEST_ID,
        CsvLogConfig::KEY_LEVEL,
        CsvLogConfig::KEY_LEVEL_NAME,
        CsvLogConfig::KEY_CHANNEL,
        CsvLogConfig::KEY_DATETIME,
    ];
    
    /** @var resource|null Reusable in-memory buffer to build CSV lines. */
    private $lineBuffer = null;
    
    private string $version;

    /**
     * Same as `StreamHandler`, but locking is enabled by default to keep lines atomic across processes.
     * 
     * @param resource|string $stream
     * @param int|string|Level $level
     * @param bool $bubble
     * @param int|null $filePermission
     * @param bool $useLocking
     * @param string $fileOpenMode
     * @param string $version format version from `CsvLogConfig` - defines columns, delimiter, enclosure and escape
     */
    public function __construct($stream, int|string|Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = true, string $fileOpenMode = 'a', string $version = CsvLogConfig::CURRENT_VERSION)
    {
        // Validates the version early: unknown versions throw here instead of on the first record.
        CsvLogConfig::getColumns($version);
        $this->version = $version;
        parent::__construct($stream, $level, $bubble, $filePermission, $useLocking, $fileOpenMode);
    }
    
    /**
     * Returns the format version from `CsvLogConfig`, that this handler writes.
     * 
     * @return string
     */
    public function getVersion() : string
    {
        return $this->version;
    }

    /**
     * Only accepts `CsvFormatter` (or subclasses) of the same version, because the written column layout is
     * defined by it.
     * 
     * @throws InvalidArgumentException if the formatter is not a `CsvFormatter` or has a different version
     * @inheritdoc
     */
    public function setFormatter(FormatterInterface $formatter) : HandlerInterface
    {
        if (! $formatter instanceof CsvFormatter) {
            throw new InvalidArgumentException(static::class . ' requires a ' . CsvFormatter::class . ', got ' . get_class($formatter) . '.');
        }
        if ($formatter->getVersion() !== $this->version) {
            throw new InvalidArgumentException(static::class . ' writes version "' . $this->version . '", but the formatter produces version "' . $formatter->getVersion() . '".');
        }
        return parent::setFormatter($formatter);
    }

    /**
     * Releases the lock even if writing failed.
     * 
     * `StreamHandler::write()` only unlocks on success. If it throws, the lock would be held until the
     * stream is closed, blocking all other processes for the rest of this request.
     * 
     * @inheritdoc
     */
    protected function write(LogRecord $record) : void
    {
        try {
            parent::write($record);
        } finally {
            if ($this->useLocking && is_resource($this->stream)) {
                flock($this->stream, LOCK_UN);
            }
        }
    }

    /**
     * Writes the formatted record as a single verified CSV line.
     * 
     * Called by `StreamHandler::write()` while the exclusive lock is held (if locking is enabled).
     * 
     * @inheritdoc
     */
    protected function streamWrite($stream, LogRecord $record) : void
    {
        $row = $record->formatted;
        
        // Safety net against formatter bugs: the row must match the configured column layout exactly,
        // otherwise readers mapping columns by position would show wrong data.
        $hasValidLayout = is_array($row) && array_keys($row) === CsvLogConfig::getColumns($this->version);
        if (! is_array($row)) {
            $row = [];
        }
        
        $line = $hasValidLayout ? $this->buildVerifiedLine($row) : null;
        if ($line === null) {
            $line = $this->buildVerifiedLine($this->buildFallbackRow($row));
        }
        
        if ($line === null) {
            // Even the fallback failed: skip the entry instead of corrupting the file.
            error_log('CsvHandler: skipped log entry ' . ($row[CsvLogConfig::KEY_ID] ?? '(no id)') . ', because it could not be encoded as a valid CSV line.');
            return;
        }
        
        // Reuse the parent's write loop, so partial writes on non-blocking streams are handled.
        parent::streamWrite($stream, $record->with(formatted: $line));
        fflush($stream);
    }

    /**
     * Encodes the row as a CSV line and verifies it by parsing it back.
     * 
     * The row is converted to strings *before* writing to allow for reliable validation.
     * Returns NULL if validation fails, so the caller can handle the error (e.g. by writing a fallback row instead).
     * 
     * @param array $row
     * @return string|null the line including the line ending or NULL if the round-trip did not reproduce the row
     */
    protected function buildVerifiedLine(array $row) : ?string
    {
        // Convert the row to string fields and verify that they can be represented as CSV.
        $fields = $this->toFields($row);
        if ($fields === null) {
            return null;
        }
        
        $delimiter = CsvLogConfig::getDelimiter($this->version);
        $enclosure = CsvLogConfig::getEnclosure($this->version);
        $escape = CsvLogConfig::getEscape($this->version);
        
        $buffer = $this->getLineBuffer();
        ftruncate($buffer, 0);
        rewind($buffer);
        
        // Write the fields to the in-memory buffer as a CSV line. 
        if (fputcsv($buffer, $fields, $delimiter, $enclosure, $escape, "\n") === false) {
            return null;
        }
        
        rewind($buffer);
        $line = stream_get_contents($buffer);
        // Verify that the line can be read from memory. 
        if ($line === false || !str_ends_with($line, "\n")) {
            return null;
        }
        
        // If the line was successfully read from memory, verify that it matches the original fields by parsing it back.
        $parsed = str_getcsv(substr($line, 0, -1), $delimiter, $enclosure, $escape);
        return $parsed === $fields ? $line : null;
    }

    /**
     * Converts the row into the string fields that will be written.
     * 
     * CSV has no types, so NULL becomes an empty string. Whatever this produces is exactly what gets written
     * and compared, so the verification cannot drift from the writer.
     * 
     * @param array $row
     * @return string[]|null NULL if a value cannot be represented as a CSV field
     */
    private function toFields(array $row) : ?array
    {
        $fields = [];
        
        foreach ($row as $value) {
            // If the value is not scalar or NULL, it cannot be represented as a CSV field.
            // We return NULL to let the caller handle the error (e.g. by writing a fallback row instead).
            if ($value !== null && ! is_scalar($value)) {
                return null;
            }
            
            $fields[] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? '1' : '0',
                default => (string) $value
            };
        }
        
        return $fields;
    }

    /**
     * Builds a minimal replacement row in the configured column layout, keeping only logger-generated values.
     * 
     * The layout is taken from `CsvLogConfig`, not from `$row`, so the fallback is valid even if `$row` is not.
     * 
     * @param array $row
     * @return array
     */
    protected function buildFallbackRow(array $row) : array
    {
        $fallback = [];
        
        foreach (CsvLogConfig::getColumns($this->version) as $column) {
            $value = $row[$column] ?? null;
            $fallback[$column] = match (true) {
                // Replace the message with a hint that the row could not be encoded.
                $column === CsvLogConfig::KEY_MESSAGE => self::PLACEHOLDER_ROW_FAILED,
                // Keep the logger-generated values, if they are scalar or NULL.
                in_array($column, self::FALLBACK_COLUMNS, true) && (is_scalar($value) || $value === null) => $value,
                // Otherwise, replace with NULL.
                default => null
            };
        }
        
        return $fallback;
    }

    /**
     * @return resource
     */
    private function getLineBuffer()
    {
        if ($this->lineBuffer === null) {
            $this->lineBuffer = fopen('php://memory', 'w+');
        }
        return $this->lineBuffer;
    }

    /**
     * Closes the in-memory line buffer in addition to the log stream.
     * 
     * @inheritdoc
     */
    public function close() : void
    {
        if (is_resource($this->lineBuffer)) {
            fclose($this->lineBuffer);
        }
        $this->lineBuffer = null;
        parent::close();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultFormatter() : FormatterInterface
    {
        return new CsvFormatter(null, $this->version);
    }
}