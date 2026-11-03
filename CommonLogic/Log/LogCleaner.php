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
            $content = file_get_contents($file);
            if ($content === false) {
                $failedFiles[] = $fileName;
                continue;
            }
            [$validRecords, $brokenRecords] = static::splitLogRecords($content, CsvLogConfig::getVersionFromFileName($file));
            if (empty($brokenRecords)) {
                continue;
            }
            
            $quarantineFile = static::getQuarantinePath($pathToLogs, $fileName);
            try {
                static::quarantineAndRewrite($filemanager, $file, $validRecords, $quarantineFile, implode('', $brokenRecords));
            } catch (\Throwable $e) {
                // On Windows, files still open by a logger (e.g. today's log) cannot be replaced
                $failedFiles[] = $fileName;
                $logger->warning('Cannot repair broken log file "' . $fileName . '": ' . $e->getMessage(), ['logFile' => $file]);
                continue;
            }
            $repairedFiles[$file] = array_keys($brokenRecords);
            $logger->error('Broken log file "' . $fileName . '" detected. Broken record(s) ' . implode(', ', array_keys($brokenRecords)) . ' moved to "' . $quarantineFile . '"!', ['logFile' => $file, 'quarantineFile' => $quarantineFile, 'logRecordsRemoved' => array_keys($brokenRecords)]);
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
    
    // Generated by GitHub Copilot - Nov-03-2026
    /**
     * Splits the contents of a log file into valid records and broken records (raw bytes each).
     * 
     * @param string $content
     * @param string $version
     * @return array [string $validRecords, string[] $brokenRecords] - broken records keyed by their record number (1-based)
     */
    protected static function splitLogRecords(string $content, string $version) : array
    {
        $columns = CsvLogConfig::getColumns($version);
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, $content);
        rewind($handle);
        
        $valid = '';
        $broken = [];
        $recordNo = 0;
        try {
            while (true) {
                $start = ftell($handle);
                $fields = fgetcsv($handle, null, CsvLogConfig::getDelimiter($version), CsvLogConfig::getEnclosure($version), CsvLogConfig::getEscape($version));
                if ($fields === false) {
                    break;
                }
                $recordNo++;
                $raw = substr($content, $start, ftell($handle) - $start);
                if (static::isValidLogRecord($fields, $columns)) {
                    $valid .= $raw;
                } else {
                    $broken[$recordNo] = $raw;
                }
            }
        } finally {
            fclose($handle);
        }
        
        // Writers append lines, so the file must end with a line break - otherwise the next record would
        // continue the last line.
        if ($valid !== '' && substr($valid, -1) !== "\n") {
            $valid .= "\n";
        }
        return [$valid, $broken];
    }
    
    // Generated by GitHub Copilot - Nov-03-2026
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
    
    // Generated by GitHub Copilot - Nov-03-2026
    /**
     * Appends the broken records to the quarantine file and then replaces the log file with the valid records.
     * 
     * If replacing the log file fails, the quarantine file is truncated to its previous size, so the same
     * records are not quarantined twice when the repair is retried.
     * 
     * @param Filemanager $filemanager
     * @param string $logFile
     * @param string $validRecords
     * @param string $quarantineFile
     * @param string $brokenRecords
     * @throws \Throwable if quarantining or rewriting fails - the log file is unchanged then
     * @return void
     */
    protected static function quarantineAndRewrite(Filemanager $filemanager, string $logFile, string $validRecords, string $quarantineFile, string $brokenRecords) : void
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
            if (fwrite($handle, $brokenRecords) !== strlen($brokenRecords) || ! fflush($handle)) {
                ftruncate($handle, $previousSize);
                throw new RuntimeException('Cannot write quarantine file "' . $quarantineFile . '"');
            }
            try {
                $filemanager->dumpFile($logFile, $validRecords);
            } catch (\Throwable $e) {
                ftruncate($handle, $previousSize);
                throw $e;
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