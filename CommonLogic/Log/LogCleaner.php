<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\DataTypes\DateDataType;
use exface\Core\CommonLogic\Tracer;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\FilePathDataType;

/**
 * Removes expired log entries and takes care of log migrations, repairs, etc.
 * 
 * @author Ralf Mulansky, Andrej Kabachnik
 *
 */
class LogCleaner
{
    const CLEANUP_AREA_LOGS = 'logs';
    
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
                    $detailsFolderName = pathinfo($logFile, PATHINFO_FILENAME);
                    $detailsSubFolder = $pathToDetails . DIRECTORY_SEPARATOR . $detailsFolderName;
                    if (is_dir($detailsSubFolder)) {
                        $filemanager->deleteDir($detailsSubFolder);
                        $countDir++;
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
     * Removes broken lines from log files
     * 
     * Sometimes a CSV log file conatins half-filled lines for some reason. And it is not
     * just columns missing: the line contains just some substring of what it should have
     * been. This leads to errors like `Array sizes inconsistent` when trying to show the 
     * log. 
     * 
     * This method attemts to find these broken lines and removes them. 
     * 
     * @param Filemanager $filemanager
     * @param string $pathToLogs
     * @return string|NULL
     */
    protected static function repairBrokenLogs(Filemanager $filemanager, string $pathToLogs) : ?string
    {
        $logFiles = glob($pathToLogs . '/*.log');
        $repairedFiles = [];
        foreach ($logFiles as $file) {
            $buffer = '';
            $brokenLines = [];
            $lineNo = 0;
            $handle = fopen($file, "r");
            if ($handle) {
                while (false !== $line = fgets($handle)) {
                    $lineNo++;
                    if (static::isValidLogLine($line)) {
                        $buffer .= $line;
                    } else {
                        $brokenLines[$lineNo] = $line;
                    }
                }
                fclose($handle);
            }
            if (! empty($brokenLines)) {
                $repairedFiles[$file] = $brokenLines;
                $filemanager->dumpFile($file, trim($buffer));
                $filemanager->getWorkbench()->getLogger()->error('Broken log file "' . FilePathDataType::findFileName($file, true) . '" detected. Broken line(s) ' . implode(', ', array_keys($brokenLines)) . ' removed!', ['logFile' => $file, 'logLinesRemoved' => $brokenLines]);
            }
        }
        if (! empty($repairedFiles)) {
            return 'Repaired ' . count($repairedFiles) . ' log files.';
        }
        return null;
    }
    
    /**
     * 
     * @param string $line
     * @return bool
     */
    protected static function isValidLogLine(string $line) : bool
    {
        return substr_count($line, ',') >= 10;
    }
}