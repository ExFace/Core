<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\DataTypes\DateDataType;
use exface\Core\CommonLogic\Tracer;

/**
 *  
 * @author ralf.mulansky
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
        if (0 === $maxDaysLogs) {
            return;
        }
        
        $filemanager = $workbench->filemanager();        
        $coreLogDir = $filemanager->getPathToLogFolder();
        $coreLogFileExt = 'log';
        $detailsLogFileExt = 'json';
        $detailsLogDir = $workbench->filemanager()->getPathToLogDetailsFolder();
        
        // Delete log detail files older than max days to keep.
        // If they are not due fordeletion or move them to subfolder.
        // Necessary for migration from old to new log deletion process.
        $limitTime = max(0, time() - ($maxDaysLogs * 24 * 60 * 60));
        $detailsFiles = glob($detailsLogDir . DIRECTORY_SEPARATOR . '*.' . $detailsLogFileExt);
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
        
        // New process of deleting log files.
        // Delete the logfile and the corresponding details sub folder if they are due for deletion.
        $logFiles = glob($coreLogDir . '/*.' . $coreLogFileExt);
        $countDir = 0;
        $countFiles = 0;
        foreach ($logFiles as $logFile) {
            if (is_writable($logFile)) {
                $mtime = filemtime($logFile);
                if ($mtime < $limitTime) {
                    $detailsFolderName = pathinfo($logFile, PATHINFO_FILENAME);
                    $detailsSubFolder = $detailsLogDir . DIRECTORY_SEPARATOR . $detailsFolderName;
                    if (is_dir($detailsSubFolder)) {
                        $filemanager->deleteDir($detailsSubFolder);
                        $countDir++;
                    }
                    @unlink($logFile);
                    $countFiles++;
                }
            }
        }
        
        $event->addResultMessage("Cleaned up log files removing {$countFiles} expired log files and {$countDir} details subfolders.");
        
        // Delete old trace files too
        $maxDaysTraces = $config->getOption('DEBUG.MAX_DAYS_TO_KEEP');
        $limitTime = max(0, time() - ($maxDaysTraces * 24 * 60 * 60));
        $logFiles = glob(
            $coreLogDir . DIRECTORY_SEPARATOR .
            Tracer::FOLDER_NAME_TRACES . DIRECTORY_SEPARATOR .
            '*.csv'
        );
        $countDir = 0;
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
        
        $event->addResultMessage("Cleaned up trace files removing {$countFiles} expired traces");
        
        return;
    }
}