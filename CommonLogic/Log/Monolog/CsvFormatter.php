<?php

namespace exface\Core\CommonLogic\Log\Monolog;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

/**
 * Formats log records into a valid CSV row, with columns and order defined by `CsvLogConfig`.
 * 
 * In case of errors, fields are sanitized instead of rejected (see `normalizeFlat()`), 
 * so a single malformed value never costs the whole row. Monolog's default JSON flags 
 * already substitute invalid UTF-8 and allow partial output, so they are kept as is.
 */
class CsvFormatter extends NormalizerFormatter
{
    // Placeholder for JSON columns that could not be encoded at all. Real JSON for an array never equals 'null',
    // so Monolog's `'null'` failure result can be detected and replaced with something explicit.
    const PLACEHOLDER_JSON_FAILED = '[JSON encoding failed]';
    
    // Matches the replacement Monolog uses for invalid UTF-8 in JSON (`JSON_INVALID_UTF8_SUBSTITUTE`).
    const UTF8_SUBSTITUTE_CODEPOINT = 0xFFFD;
    
    public function __construct(?string $dateFormat = null)
    {
        $dateFormat = $dateFormat ?? CsvLogConfig::getDateFormat();
        
        parent::__construct($dateFormat);
        
        // Large arrays can cause performance issues, so we limit the depth and item count for normalization.
        // The cut-off will be indicated with a placeholder string in the output.
        $this->setMaxNormalizeDepth(9);
        $this->setMaxNormalizeItemCount(1000);
    }
    
    public function format(LogRecord $record) : array
    {
        $columns = CsvLogConfig::getColumns();
        $context = $record->context;
        $extra = $record->extra;
        $result = [];

        foreach ($columns as $column) {
            $unset = false;
            
            // Fetch the value from the extra or context arrays if it exists.
            if(array_key_exists($column, $extra)) {
                $value = $extra[$column];
                $unset = true;
            } elseif (array_key_exists($column, $context)) {
                $value = $context[$column];
                $unset = true;
            } else {
                $value = null;
            }
            
            // Remove the value from the extra and context arrays to avoid duplication in the final output.
            if($unset) {
                unset($extra[$column], $context[$column]);
            }

            $result[$column] = match (true) {
                // Core values have precedence over context and extra values.
                $column === CsvLogConfig::KEY_CHANNEL => $this->normalizeFlat($record->channel),
                $column === CsvLogConfig::KEY_DATETIME => $this->normalizeFlat($record->datetime),
                $column === CsvLogConfig::KEY_MESSAGE => $this->normalizeFlat($record->message),
                $column === CsvLogConfig::KEY_LEVEL => $this->normalizeFlat($record->level->value),
                $column === CsvLogConfig::KEY_LEVEL_NAME => $this->normalizeFlat($record->level->getName()),
                // Ensure columns are written in the correct order, regardless of their contents.
                default => $this->normalizeFlat($value)
            };
        }

        // Fill the extra column last, to ensure all duplicate data has been stripped.
        if(array_key_exists(CsvLogConfig::KEY_CONTEXT, $result)) {
            $result[CsvLogConfig::KEY_CONTEXT] = $this->normalizeFlat($context);
        }
        
        if(array_key_exists(CsvLogConfig::KEY_EXTRA, $result)) {
            $result[CsvLogConfig::KEY_EXTRA] = $this->normalizeFlat($extra);
        }
        
        return $result;
    }

    /**
     * Normalizes a value into a single, valid CSV field (scalar or null).
     * 
     * Arrays are JSON encoded. Strings are guaranteed to be valid UTF-8: invalid byte sequences are
     * replaced with U+FFFD instead of rejecting the row, so a malformed value never costs the entire entry.
     * 
     * @param mixed $value
     * @return string|int|float|bool|null
     */
    private function normalizeFlat(mixed $value) : string|int|float|bool|null
    {
        $value = $this->normalize($value);
        
        if (is_array($value)) {
            $json = $this->toJson($value, true);
            return $json === 'null' ? self::PLACEHOLDER_JSON_FAILED : $json;
        }
        
        if (is_string($value)) {
            return $this->sanitizeUtf8($value);
        }
        
        return $value;
    }

    /**
     * Replaces invalid UTF-8 byte sequences with U+FFFD, consistent with how Monolog treats JSON.
     * 
     * The global `mbstring` substitute character is restored afterward to avoid side effects on other code.
     * 
     * @param string $value
     * @return string
     */
    private function sanitizeUtf8(string $value) : string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        
        $previous = mb_substitute_character();
        mb_substitute_character(self::UTF8_SUBSTITUTE_CODEPOINT);
        try {
            return mb_scrub($value, 'UTF-8');
        } finally {
            mb_substitute_character($previous);
        }
    }
}