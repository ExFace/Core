<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\DataTypes\DateDataType;
use exface\Core\CommonLogic\Tracer;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\LogLevelDataType;
use exface\Core\Exceptions\RuntimeException;

/**
 * Removes expired log entries and takes care of log migrations, repairs, etc.
 *
 * @author Ralf Mulansky, Andrej Kabachnik
 *
 */
class LogCleaner
{
    const CLEANUP_AREA_LOGS = 'logs';
    
    const FOLDER_NAME_QUARANTINE = 'quarantine';

    /**
     * Deleting log files and details folder that are older then the config option `MAX_DAYS_TO_KEEP`.
     * Moves old .json details files to new details subfolders ifthey are not due for deletion,
     * else they are simply deleted.
     * This is done so the old files are compatible with new deletion process.
     *
     * We simply now delete the *.log file if it is due and also the corresponing subfolder
     * in the details folder. This is done so we dont have to iterate over all
     * detail files to calculate if they are due for deletion or not.
     *
     * @param OnCleanUpEvent $event
     */
    public static function onCleanUp(OnCleanUpEvent $event) : void
    {
        if (! $event->isAreaToBeCleaned(self::CLEANUP_AREA_LOGS)) {
            return;
        }

        $workbench = $event->getWorkbench();
        $config = $workbench->getConfig();
        $maxDaysLogs = $config->getOption('LOG.MAX_DAYS_TO_KEEP');

        $filemanager = $workbench->filemanager();
        $coreLogDir = $filemanager->getPathToLogFolder();
        $detailsLogDir = $workbench->filemanager()->getPathToLogDetailsFolder();

        // Delete log detail files older than max days to keep.
        // If they are not due fordeletion or move them to subfolder.
        // Necessary for migration from old to new log deletion process.
        static::cleanupLogDetailsOldStructure($filemanager, $detailsLogDir, $maxDaysLogs);

        // New process of deleting log files.
        // Delete the logfile and the corresponding details sub folder if they are due for deletion.
        $msg = static::cleanupLogsAndDetails($filemanager, $coreLogDir, $detailsLogDir, $maxDaysLogs);
        if ($msg !== null) {
            $event->addResultMessage($msg);
        }

        // Repair broken log files
        $msg = static::repairBrokenLogs($filemanager, $coreLogDir);
        if ($msg !== null) {
            $event->addResultMessage($msg);
        }

        // Delete old trace files too
        $maxDaysTraces = $config->getOption('DEBUG.MAX_DAYS_TO_KEEP');
        $traceDir = $coreLogDir . DIRECTORY_SEPARATOR . Tracer::FOLDER_NAME_TRACES;
        $msg = static::cleanupTraces($filemanager, $traceDir, $maxDaysTraces);
        if ($msg !== null) {
            $event->addResultMessage($msg);
        }

        return;
    }

    /**
     *
     * @param Filemanager $filemanager
     * @param string $detailsLogDir
     * @param int $maxDaysToKeep
     * @return string|NULL
     */
    protected static function cleanupLogDetailsOldStructure(Filemanager $filemanager, string $detailsLogDir, int $maxDaysToKeep) : ?string
    {
        if (0 === $maxDaysToKeep) {
            return null;
        }
        $limitTime = max(0, time() - ($maxDaysToKeep * 24 * 60 * 60));
        $detailsFiles = glob($detailsLogDir . DIRECTORY_SEPARATOR . '*.json');
        foreach ($detailsFiles as $detailFile) {
            if (is_writable($detailFile)) {
                $mtime = filemtime($detailFile);
                // delete files that are due for deletion
                if ($mtime < $limitTime) {
                    @unlink($detailFile);
                    // else copy them to subfolder and then delete them
                } else {
                    $subFolderName = date(DateDataType::DATE_FORMAT_INTERNAL, $mtime);
                    $newPath = $detailsLogDir . DIRECTORY_SEPARATOR . $subFolderName . DIRECTORY_SEPARATOR . basename($detailFile);
                    $filemanager->copyFile($detailFile, $newPath);
                    @unlink($detailFile);
                }
            }
        }
        return null;
    }

    /**
     *
     * @param Filemanager $filemanager
     * @param string $coreLogDir
     * @param string $pathToDetails
     * @param int $limitTime
     * @return string|NULL
     */
    protected static function cleanupLogsAndDetails(Filemanager $filemanager, string $pathToLogs, string $pathToDetails, int $maxDaysToKeep) : ?string
    {
        if (0 === $maxDaysToKeep) {
            return null;
        }
        $limitTime = max(0, time() - ($maxDaysToKeep * 24 * 60 * 60));
        $logFiles = glob($pathToLogs . '/*.log');
        $countDir = 0;
        $countFiles = 0;
        foreach ($logFiles as $logFile) {
            if (is_writable($logFile)) {
                $mtime = filemtime($logFile);
                if ($mtime < $limitTime) {
                    // Details are stored per date for all format versions, so strip the version key
                    // ("2026-10-20.v1.log" => "2026-10-20").
                    $detailsFolderName = CsvLogConfig::getBaseName($logFile);
                    $detailsSubFolder = $pathToDetails . DIRECTORY_SEPARATOR . $detailsFolderName;
                    if (is_dir($detailsSubFolder)) {
                        $filemanager->deleteDir($detailsSubFolder);
                        $countDir++;
                    }
                    // Records removed by repairBrokenLogs() expire with their log file
                    $quarantineFile = static::getQuarantinePath($pathToLogs, FilePathDataType::findFileName($logFile, true));
                    if (is_file($quarantineFile)) {
                        @unlink($quarantineFile);
                    }
                    @unlink($logFile);
                    $countFiles++;
                }
            }
        }
        return "Cleaned up log files removing {$countFiles} expired log files and {$countDir} details subfolders.";
    }

    protected static function cleanupTraces(Filemanager $filemanager, string $pathToTraces, int $maxDaysToKeep) : ?string
    {
        if (0 === $maxDaysToKeep) {
            return null;
        }
        $limitTime = max(0, time() - ($maxDaysToKeep * 24 * 60 * 60));
        $logFiles = glob($pathToTraces . DIRECTORY_SEPARATOR . '*.csv');
        $countFiles = 0;
        foreach ($logFiles as $logFile) {
            if (is_writable($logFile)) {
                $mtime = filemtime($logFile);
                if ($mtime < $limitTime) {
                    @unlink($logFile);
                    $countFiles++;
                }
            }
        }
        return "Cleaned up trace files removing {$countFiles} expired traces.";
    }

    /**
     * Moves broken records from log files to the quarantine folder
     *
     * Sometimes a CSV log file contains broken records: truncated or interleaved writes, values the legacy
     * writer could not escape, etc. These would show up as garbage in the log viewer.
     *
     * Files are parsed as CSV (logical records, so valid multi-line records are kept) with the settings of
     * their format version in `CsvLogConfig`. Records failing `isValidLogRecord()` are appended unchanged
     * (raw bytes) to `logs/quarantine/<log file name>` *before* the log file is rewritten without them, so a
     * repair can always be undone by appending them back. If the log file cannot be rewritten (e.g. because it
     * is still open on Windows), the quarantine is rolled back and the file is retried on the next cleanup.
     * 
     * Valid records are written back byte by byte as they were.
     *
     * @param Filemanager $filemanager
     * @param string $pathToLogs
     * @return string|NULL
     */
    protected static function repairBrokenLogs(Filemanager $filemanager, string $pathToLogs) : ?string
    {
        $logFiles = glob($pathToLogs . '/*.log');
        $repairedFiles = [];
        $failedFiles = [];
        $logger = $filemanager->getWorkbench()->getLogger();
        foreach ($logFiles as $file) {
            // Skip logs that we cannot write anyhow - we cannot help them
            if (! is_writable($file)) {
                continue;
            }
            $fileName = FilePathDataType::findFileName($file, true);
            $version = CsvLogConfig::getVersionFromFileName($file);
            // Quick check without lock, so intact files never block the writers. It may see a record, that is
            // being written right now, as broken - the repair checks again under lock. On Windows, locks are
            // mandatory, so the read fails while a record is written - check under lock then.
            $handle = @fopen($file, 'rb');
            if ($handle !== false) {
                try {
                    $brokenRecordNos = static::splitLogRecords($handle, $version);
                } catch (\Throwable $e) {
                    $brokenRecordNos = null;
                } finally {
                    fclose($handle);
                }
                if ($brokenRecordNos === []) {
                    continue;
                }
            }
            
            $quarantineFile = static::getQuarantinePath($pathToLogs, $fileName);
            try {
                $brokenRecordNos = static::repairLogFile($filemanager, $file, $version, $quarantineFile);
            } catch (\Throwable $e) {
                $failedFiles[] = $fileName;
                $logger->warning('Cannot repair broken log file "' . $fileName . '": ' . $e->getMessage(), ['logFile' => $file]);
                continue;
            }
            // The quick check only saw a record in progress
            if (empty($brokenRecordNos)) {
                continue;
            }
            $repairedFiles[$file] = $brokenRecordNos;
            $logger->error('Broken log file "' . $fileName . '" detected. Broken record(s) ' . implode(', ', $brokenRecordNos) . ' moved to "' . $quarantineFile . '"!', ['logFile' => $file, 'quarantineFile' => $quarantineFile, 'logRecordsRemoved' => $brokenRecordNos]);
        }
        $msgs = [];
        if (! empty($repairedFiles)) {
            $msgs[] = 'Repaired ' . count($repairedFiles) . ' log files.';
        }
        if (! empty($failedFiles)) {
            $msgs[] = 'Could not repair ' . count($failedFiles) . ' log files: ' . implode(', ', $failedFiles) . '.';
        }
        return empty($msgs) ? null : implode(' ', $msgs);
    }
    
    /**
     * Reads the log records from the stream and returns the numbers of the broken ones - optionally copying the
     * raw bytes of valid and broken records to separate streams.
     * 
     * Parses the stream directly and only holds one record in memory, so log files of any size can be checked.
     * Pass `php://temp` streams to collect the records: they spill to disk if they get large.
     * 
     * @param resource $handle stream of the log file - read from its start
     * @param string $version
     * @param resource|null $validOut receives the valid records - always ending with a line break
     * @param resource|null $brokenOut receives the broken records
     * @return int[] numbers of the broken records (1-based)
     */
    protected static function splitLogRecords($handle, string $version, $validOut = null, $brokenOut = null) : array
    {
        $columns = CsvLogConfig::getColumns($version);
        $delimiter = CsvLogConfig::getDelimiter($version);
        $enclosure = CsvLogConfig::getEnclosure($version);
        $escape = CsvLogConfig::getEscape($version);
        
        rewind($handle);
        $broken = [];
        $recordNo = 0;
        $lastValid = null;
        while (true) {
            $start = ftell($handle);
            $fields = fgetcsv($handle, null, $delimiter, $enclosure, $escape);
            if ($fields === false) {
                break;
            }
            $recordNo++;
            $end = ftell($handle);
            $isValid = static::isValidLogRecord($fields, $columns);
            if (! $isValid) {
                $broken[] = $recordNo;
            }
            $out = $isValid ? $validOut : $brokenOut;
            if ($out !== null) {
                fseek($handle, $start);
                $raw = $end > $start ? fread($handle, $end - $start) : '';
                fwrite($out, $raw);
                if ($isValid) {
                    $lastValid = $raw;
                }
            }
        }
        
        // Writers append lines, so the file must end with a line break - otherwise the next record would
        // continue the last line.
        if ($validOut !== null && $lastValid !== null && substr($lastValid, -1) !== "\n") {
            fwrite($validOut, "\n");
        }
        return $broken;
    }
    
    /**
     * Returns TRUE if the parsed CSV record is a plausible log entry of the given column layout.
     * 
     * Checks the field count, the log ID, the numeric level (and its name), the timestamp and that
     * context/extra are empty or valid JSON. Empty context/extra are allowed, because fallback rows
     * (see `CsvHandler`) do not have them.
     * 
     * @param array $fields
     * @param string[] $columns
     * @return bool
     */
    protected static function isValidLogRecord(array $fields, array $columns) : bool
    {
        if (count($fields) !== count($columns)) {
            return false;
        }
        $record = array_combine($columns, $fields);
        
        $id = $record[CsvLogConfig::KEY_ID] ?? null;
        if ($id === null || preg_match('/^\S+$/', $id) !== 1) {
            return false;
        }
        
        if (array_key_exists(CsvLogConfig::KEY_LEVEL, $record)) {
            $levels = LogLevelDataType::getValuesOfMonologLevels();
            $level = $record[CsvLogConfig::KEY_LEVEL] ?? '';
            if (! ctype_digit($level) || ! array_key_exists((int) $level, $levels)) {
                return false;
            }
            if (array_key_exists(CsvLogConfig::KEY_LEVEL_NAME, $record) && strtoupper($record[CsvLogConfig::KEY_LEVEL_NAME] ?? '') !== $levels[(int) $level]) {
                return false;
            }
        }
        
        if (array_key_exists(CsvLogConfig::KEY_DATETIME, $record)) {
            $datetime = $record[CsvLogConfig::KEY_DATETIME] ?? '';
            // Not checked against the exact date format, so files of older writers with another precision stay valid
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $datetime) !== 1 || strtotime($datetime) === false) {
                return false;
            }
        }
        
        foreach ([CsvLogConfig::KEY_CONTEXT, CsvLogConfig::KEY_EXTRA] as $key) {
            $json = $record[$key] ?? '';
            if ($json !== '') {
                json_decode($json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Re-reads the log file under an exclusive lock, quarantines its broken records and rewrites it in place.
     * 
     * Takes the same lock as the writers (`CsvHandler` locks every line), so no record can be half-written
     * while the file is read and rewritten. Writers wait meanwhile. Only files with broken records are locked.
     * 
     * The file is rewritten in place instead of being replaced: loggers, that have the file open, keep
     * appending to it afterwards. A replaced file would make them write to the old file (Linux) or fail
     * (Windows), so files currently in use could not be repaired.
     * 
     * @param Filemanager $filemanager
     * @param string $logFile
     * @param string $version
     * @param string $quarantineFile
     * @throws \Throwable if reading, quarantining or rewriting fails
     * @return int[] numbers of the broken records (1-based) - empty if the file is intact
     */
    protected static function repairLogFile(Filemanager $filemanager, string $logFile, string $version, string $quarantineFile) : array
    {
        $handle = fopen($logFile, 'r+b');
        if ($handle === false) {
            throw new RuntimeException('Cannot open log file "' . $logFile . '"');
        }
        // Spill to disk above 2 MB, so large log files do not exhaust the memory
        $validOut = fopen('php://temp/maxmemory:2097152', 'w+b');
        $brokenOut = fopen('php://temp/maxmemory:2097152', 'w+b');
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock log file "' . $logFile . '"');
            }
            // Read through the locked handle: on Windows, locks are mandatory and would block other handles.
            $brokenRecordNos = static::splitLogRecords($handle, $version, $validOut, $brokenOut);
            if (! empty($brokenRecordNos)) {
                static::quarantineAndRewrite($filemanager, $handle, $validOut, $quarantineFile, $brokenOut);
            }
            return $brokenRecordNos;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
            fclose($validOut);
            fclose($brokenOut);
        }
    }
    
    /**
     * Appends the broken records to the quarantine file and then overwrites the locked log file with the valid records.
     * 
     * The log is overwritten from the start and truncated afterwards. The valid records are never longer than
     * the original content, so if the process dies midway, the file contains new records followed by the rest
     * of the old ones (duplicates, possibly a broken record at the seam), but no record is lost. The next
     * cleanup quarantines what is still broken.
     * 
     * If the log file was not changed at all, the quarantine file is truncated to its previous size, so the
     * same records are not quarantined twice when the repair is retried. Once the log file was changed, the
     * quarantine is kept: duplicates are better than losing the broken records.
     * 
     * @param Filemanager $filemanager
     * @param resource $logHandle handle of the log file opened for reading and writing and locked exclusively
     * @param resource $validRecords stream with the valid records
     * @param string $quarantineFile
     * @param resource $brokenRecords stream with the broken records
     * @throws \Throwable if quarantining or rewriting fails
     * @return void
     */
    protected static function quarantineAndRewrite(Filemanager $filemanager, $logHandle, $validRecords, string $quarantineFile, $brokenRecords) : void
    {
        $filemanager->mkdir(dirname($quarantineFile));
        $handle = fopen($quarantineFile, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Cannot open quarantine file "' . $quarantineFile . '"');
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock quarantine file "' . $quarantineFile . '"');
            }
            $previousSize = fstat($handle)['size'];
            fseek($handle, 0, SEEK_END);
            $brokenSize = fstat($brokenRecords)['size'];
            rewind($brokenRecords);
            if (stream_copy_to_stream($brokenRecords, $handle) !== $brokenSize || ! fflush($handle)) {
                ftruncate($handle, $previousSize);
                throw new RuntimeException('Cannot write quarantine file "' . $quarantineFile . '"');
            }
            $validSize = fstat($validRecords)['size'];
            try {
                rewind($validRecords);
                rewind($logHandle);
                $written = $validSize === 0 ? 0 : stream_copy_to_stream($validRecords, $logHandle);
            } catch (\Throwable $e) {
                $written = false;
            }
            if ($written === false || ($written === 0 && $validSize !== 0)) {
                // Log file unchanged - undo the quarantine
                ftruncate($handle, $previousSize);
                throw new RuntimeException('Cannot rewrite log file', null, $e ?? null);
            }
            if ($written !== $validSize || ! ftruncate($logHandle, $written) || ! fflush($logHandle)) {
                throw new RuntimeException('Log file only partially rewritten - broken records are kept in the quarantine, remaining ones will be repaired by the next cleanup');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
    
    /**
     * Returns the path of the quarantine file for a log file
     * 
     * @param string $pathToLogs
     * @param string $logFileName
     * @return string
     */
    protected static function getQuarantinePath(string $pathToLogs, string $logFileName) : string
    {
        return $pathToLogs . DIRECTORY_SEPARATOR . self::FOLDER_NAME_QUARANTINE . DIRECTORY_SEPARATOR . $logFileName;
    }
}