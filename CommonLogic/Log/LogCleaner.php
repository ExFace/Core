<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\DataTypes\DateDataType;

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
        $maxDaysToKeep = $config->getOption('LOG.MAX_DAYS_TO_KEEP');
        
        if (0 === $maxDaysToKeep) {
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
        $limitTime = max(0, time() - ($maxDaysToKeep * 24 * 60 * 60));
        $detailsFiles = glob($detailsLogDir . '/*.' . $detailsLogFileExt);
        foreach ($detailsFiles as $detailFile) {
            if (is_writable($detailFile)) {
                $mtime = filemtime($detailFile);
                // delete files that are due
                if ($mtime < $limitTime) {
                    @unlink($detailFile);
                // else copy them to subfolder and then remove them
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
                // delete files that are due and their corresponding details folder
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
        $event->addResultMessage("Cleaned up log files, deleted {$countFiles} files and {$countDir} details subfolders.");
        
        return;
    }
}