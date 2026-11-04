<?php

namespace exface\Core\CommonLogic\Log;

use exface\Core\CommonLogic\Log\Processors\ActionAliasProcessor;
use exface\Core\CommonLogic\Log\Processors\RequestIdProcessor;
use exface\Core\CommonLogic\Log\Processors\UserNameProcessor;
use InvalidArgumentException;

/**
 *  Configuration for CSV log files.
 * 
 *  **IMPORTANT:** Always use `getVersionFromFileName()` to detect the version of a log file!
 * 
 *  Defines delimiter, enclosure, date format and the column layout of every format version.
 *  and is the single source of truth for writer and reader:
 *  - Writing: the CSV formatter and `CsvHandler` always use `CURRENT_VERSION`.
 *  - Reading: `LogCsvBuilder` detects the version from the file name (the version key) and
 *    translates the column names used as DATA_ADDRESS in `exface.Core.LOG_ENTRY` into
 *    positions via `getColumns($version)`. Old files are never migrated; they stay readable
 *    through their version entry.
 * 
 *  ### Rules for changes:
 *  - Never modify an existing entry in `VERSIONS`. Files in that format already exist.
 *  - To change the layout, add a new version with a unique key (like 'v1') and bump
 *    `CURRENT_VERSION`. Reordering columns needs no model change.
 *  - Make sure the key still matches the file masks of `exface.Core.LOG`
 *    (`logs/*.log`) and `exface.Core.TRACE_LOG` (`logs/traces/*.csv`), otherwise new
 *    files will not show up in the UI.
 *  - Adding a new column additionally requires a new attribute in `exface.Core.LOG_ENTRY`
 *    (`Model/exface.Core.LOG_ENTRY/04_ATTRIBUTE.json`) to make it visible.
 * 
 *  ### File naming and version detection:
 *
 *    <base>[.<key>].<extension>
 *
 *  - `<base>` is the date (`Y-m-d`) for daily logs or the time (`Y-m-d H-i-s-u`) for traces.
 *    It never contains a dot, which is what makes detection unambiguous.
 *  - `<key>` is empty for legacy logs and a string for all subsequent versions. It must not contain dots.
 *  - `<extension>` never changes (`.log` / `.csv`), so the existing file masks keep matching.
 *
 *  Example for a version with the key `v1`:
 *
 *    | Kind  | Legacy (no key)                     | Version 1 (`v1`)                       | Mask                  |
 *    |-------|-------------------------------------|----------------------------------------|-----------------------|
 *    | Daily | logs/2026-10-20.log                 | logs/2026-10-20.v1.log                 | logs/*.log            |
 *    | Trace | logs/traces/2026-10-20 16-48-12-437000.csv | logs/traces/2026-10-20 16-48-12-437000.v1.csv | logs/traces/*.csv |
 *
 *  Both match, because `*` in the masks also matches dots. Details stay in
 *  `logs/details/<base>/` for every version.
 *
 *  Version switch: there is at most one file per base name. If a file with the base name already exists in an
 *  older version, writers continue it in that version (`getVersionForWriting()`), so after an upgrade the new
 *  format starts with the next day's daily log. Readers that only know the date can therefore always resolve
 *  it to exactly one file (`findFiles()`).
 *
 *  Detection: take the file name without extension and look at the part after the
 *  last dot. If it equals a known key, that's the version. If there is no
 *  dot, it's a legacy log.
 *
 *    2026-10-20.v1.log  → "2026-10-20.v1" → key "v1"   → version 1
 *    2026-10-20.log     → "2026-10-20"    → no dot     → legacy log
 *
 *  ### Checklist for a new key:
 *  - unique among all versions, no dots
 *  - must contain a letter (to allow strict comparisons)
 *  - file name still ends in `.log` / `.csv`, so it matches `logs/*.log` / `logs/traces/*.csv`
 * 
 * @see \exface\Core\QueryBuilders\LogCsvBuilder for the version-aware reader
 * @see \exface\Core\CommonLogic\Log\LogCleaner for per-version validation and repair
 * 
 * @author Georg Bieger
 */
final class CsvLogConfig
{
    const LEGACY_VERSION = '';
    const CURRENT_VERSION = 'temp';
    
    const KEY_ID = 'id';
    const KEY_REQUEST_ID = 'requestId';
    const KEY_USER_NAME = 'userName';
    const KEY_ACTION_ALIAS = 'actionAlias';
    const KEY_MESSAGE = 'message'; //*
    const KEY_CONTEXT = 'context';
    const KEY_LEVEL = 'level'; //*
    const KEY_LEVEL_NAME = 'level_name';
    const KEY_CHANNEL = 'channel';
    const KEY_DATETIME = 'datetime'; //*
    const KEY_EXTRA = 'extra'; //*
    
    const PRP_COLUMNS = 'columns';
    const PRP_DELIMITER = 'delimiter';
    const PRP_ENCLOSURE = 'enclosure';
    const PRP_ESCAPE = 'escape';
    const PRP_DATE_FORMAT = 'date_format';

    // DO NOT MODIFY existing entries, because they are already in use.
    // Append newer versions at the end of this array to allow proper version comparison.
    const VERSIONS = [
        self::LEGACY_VERSION => [ // legacy log files without version key
            self::PRP_COLUMNS => [ // will be written to the CSV file in this order
                self::KEY_ID,
                RequestIdProcessor::KEY_REQUEST_ID,
                UserNameProcessor::KEY_USER_NAME,
                ActionAliasProcessor::KEY_ACTION_ALIAS,
                self::KEY_MESSAGE,
                self::KEY_CONTEXT,
                self::KEY_LEVEL,
                self::KEY_LEVEL_NAME,
                self::KEY_CHANNEL,
                self::KEY_DATETIME,
                self::KEY_EXTRA
            ],
            self::PRP_DELIMITER => ',',
            self::PRP_ENCLOSURE => '\'',
            self::PRP_ESCAPE => '\\',
            self::PRP_DATE_FORMAT => 'Y-m-d H:i:s.v'
        ],
        'temp' => [
            self::PRP_COLUMNS => [ // will be written to the CSV file in this order
                // Fixed-width or short columns first, so they line up when the file is read by a human.
                self::KEY_DATETIME,
                self::KEY_ID,
                RequestIdProcessor::KEY_REQUEST_ID,
                self::KEY_LEVEL,
                self::KEY_LEVEL_NAME,
                self::KEY_CHANNEL,
                // Variable-length columns last.
                UserNameProcessor::KEY_USER_NAME,
                ActionAliasProcessor::KEY_ACTION_ALIAS,
                self::KEY_MESSAGE,
                self::KEY_CONTEXT,
                self::KEY_EXTRA
            ],
            // RFC 4180 / Excel convention: double quote enclosure, quotes inside values are doubled, no escape char.
            self::PRP_DELIMITER => ',',
            self::PRP_ENCLOSURE => '"',
            self::PRP_ESCAPE => '',
            self::PRP_DATE_FORMAT => 'Y-m-d H:i:s.v'
        ],
    ];

    /**
     * Column keys for the given version (current by default).
     * 
     * @throws InvalidArgumentException if the version is unknown.
     * @param string $version
     * @return array
     */
    public static function getColumns(string $version = self::CURRENT_VERSION) : array
    {
        self::assertVersionExists($version, 'version');
        return self::VERSIONS[$version][self::PRP_COLUMNS];
    }

    /**
     * Returns the delimiter character for the given version (current by default).
     *
     * @throws InvalidArgumentException if the version is unknown.
     * @param string $version
     * @return string
     */
    public static function getDelimiter(string $version = self::CURRENT_VERSION) : string
    {
        self::assertVersionExists($version, 'version');
        return self::VERSIONS[$version][self::PRP_DELIMITER];
    }
    
    /**
     * Returns the enclosure character for the given version (current by default). 
     *
     * @throws InvalidArgumentException if the version is unknown.
     * @param string $version
     * @return string
     */
    public static function getEnclosure(string $version = self::CURRENT_VERSION) : string
    {
        self::assertVersionExists($version, 'version');
        return self::VERSIONS[$version][self::PRP_ENCLOSURE];
    }
    
    /**
     * Returns the escape character for the given version (current by default). 
     *
     * @throws InvalidArgumentException if the version is unknown.
     * @param string $version
     * @return string
     */
    public static function getEscape(string $version = self::CURRENT_VERSION) : string
    {
        self::assertVersionExists($version, 'version');
        return self::VERSIONS[$version][self::PRP_ESCAPE];
    }

    /**
     * Returns the date format for the given version (current by default). 
     *
     * @throws InvalidArgumentException if the version is unknown.
     * @param string $version
     * @return string
     */
    public static function getDateFormat(string $version = self::CURRENT_VERSION) : string
    {
        self::assertVersionExists($version, 'version');
        return self::VERSIONS[$version][self::PRP_DATE_FORMAT];
    }

    /**
     * Returns the file name without extension and without a known version key,
     * e.g. "logs/2026-10-20.v1.log" => "2026-10-20". Unknown suffixes are kept,
     * consistent with getVersionFromFileName() treating such files as legacy.
     */
    public static function getBaseName(string $fileName) : string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $version = self::getVersionFromFileName($fileName);

        return $version === self::LEGACY_VERSION
            ? $name
            : substr($name, 0, -strlen('.' . $version));
    }

    /**
     * Returns the version key for a given file name.
     * 
     * If the file name does not contain a version key, it is treated as legacy.
     * If it does contain a version key, that is unknown, it is also treated as legacy.
     * 
     * @param string $fileName
     * @return string
     */
    public static function getVersionFromFileName(string $fileName) : string
    {
        // Filename without extension, e.g."2026-10-20.v1.csv" => "2026-10-20.v1".
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        
        // Find the last dot in the base name.
        $lastDotPos = strrpos($baseName, '.');
        
        // If there is no dot, it's a legacy log.
        if ($lastDotPos === false) {
            return self::LEGACY_VERSION;
        }
        
        // Get string after the last dot, e.g. "2026-10-20.v1" => "v1".
        $key = substr($baseName, $lastDotPos + 1);
        
        // If the key is known, return it; otherwise, treat it as legacy.
        return array_key_exists($key, self::VERSIONS) ? $key : self::LEGACY_VERSION;
    }

    /**
     * Builds a log file name for the given version, e.g. ("2026-10-20", "log") => "2026-10-20.v1.log".
     * 
     * @throws InvalidArgumentException if the version is unknown.
     * @param string $base
     * @param string $extension
     * @param string $version
     * @return string
     */
    public static function buildFileName(string $base, string $extension, string $version = self::CURRENT_VERSION) : string
    {
        self::assertVersionExists($version, 'version');
        return $base . ($version === self::LEGACY_VERSION ? '' : '.' . $version) . '.' . $extension;
    }
    
    /**
     * Returns the existing files with the given base name in a folder, keyed by version (oldest version first).
     * 
     * Only known versions are checked, so files with unknown keys are ignored.
     * 
     * @param string $dir
     * @param string $base
     * @param string $extension
     * @return string[] [version => path]
     */
    public static function findFiles(string $dir, string $base, string $extension) : array
    {
        $dir = rtrim($dir, '/\\');
        $found = [];
        foreach (array_keys(self::VERSIONS) as $version) {
            $path = $dir . DIRECTORY_SEPARATOR . self::buildFileName($base, $extension, (string) $version);
            if (is_file($path)) {
                $found[$version] = $path;
            }
        }
        return $found;
    }
    
    /**
     * Returns the version to write a file with the given base name: the newest version that already has a file
     * with this base name, otherwise the current version.
     * 
     * Ensures one file per base name: after deploying a new version, the new format starts with the next base name
     * (e.g. the next day for daily logs) instead of creating a second file for the same date. Otherwise, readers
     * that only know the date (e.g. `MONITOR_ERROR.LOG_FILE`) could not tell which file to read.
     * 
     * @param string $dir
     * @param string $base
     * @param string $extension
     * @return string
     */
    public static function getVersionForWriting(string $dir, string $base, string $extension) : string
    {
        $existing = self::findFiles($dir, $base, $extension);
        return empty($existing) ? self::CURRENT_VERSION : (string) array_key_last($existing);
    }
    
    /**
     * Compares two versions, returning 
     *  - `1` if version1 is GREATER than version2
     *  - `-1` if version1 is LESS than version2
     *  - `0` if they are EQUAL.
     * 
     * Depends on `self::VERSIONS` order, so append new versions at the end of the array to make them greater than existing ones.
     * 
     * @param string $version1
     * @param string $version2
     * @return int
     */
    public static function compareVersion(string $version1, string $version2) : int
    {
        // Validate that both versions exist in the VERSIONS array.
        self::assertVersionExists($version1, 'version1');
        self::assertVersionExists($version2, 'version2');
        
        if ($version1 === $version2) {
            return 0;
        }
        
        foreach (self::VERSIONS as $key => $config) {
            if ($key === $version1) {
                return -1; // version1 is less than version2
            }
            
            if ($key === $version2) {
                return 1; // version1 is greater than version2
            }
        }
        
        return 0;
    }
    
    /**
     * Asserts that the given version exists in the VERSIONS array. Throws an InvalidArgumentException if it does not.
     * 
     * @param string $version
     * @param string $parameterName
     * @return void
     */
    private static function assertVersionExists(string $version, string $parameterName) : void
    {
        if (!array_key_exists($version, self::VERSIONS)) {
            throw new InvalidArgumentException("Unknown $parameterName: $version");
        }
    }
}