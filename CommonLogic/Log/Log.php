<?php

namespace exface\Core\CommonLogic\Log;


use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Log\Handlers\DebugMessageFileHandler;
use exface\Core\CommonLogic\Log\Handlers\limit\FileLimitingLogHandler;
use exface\Core\CommonLogic\Log\Handlers\limit\DirLimitingLogHandler;
use exface\Core\CommonLogic\Log\Handlers\LogfileHandler;
use exface\Core\Interfaces\LoggerInterface;

class Log
{
    /**
     * Creates a LoggerInterface implementation if none is there yet and returns it after adding default
     * LogHandlerInterface instances to it.
     *
     * @return LoggerInterface
     */
    public static function getDefaultLogger()
    {
        $logger = new Logger();

        $coreLogFilePath = static::getCoreLogPath();
	    $detailsLogBasePath = static::getDetailsLogPath();

	    $minLogLevel = static::getWorkbench()->get_config()->get_option('LOG.MINIMUM_LEVEL_TO_LOG');
	    $maxDaysToKeep = static::getWorkbench()->get_config()->get_option('LOG.MAX_DAYS_TO_KEEP');
	    $detailsStaticFilenamePart = static::getWorkbench()->get_config()->get_option('LOGDETAILS.STATIC_FILENAME_PART');

        $logger->pushHandler(new FileLimitingLogHandler(
        	function($filename) use ($minLogLevel) {
	            return new LogfileHandler("exface", $filename, $minLogLevel);
			}, $coreLogFilePath, $maxDaysToKeep
        ));

        // TODO tvw enable when log details are used
        $logger->pushHandler(new DirLimitingLogHandler(
        	function() use ($detailsLogBasePath, $detailsStaticFilenamePart, $minLogLevel) {
		        return new DebugMessageFileHandler($detailsLogBasePath, $detailsStaticFilenamePart, $minLogLevel);
            }, $detailsLogBasePath, $detailsStaticFilenamePart, $maxDaysToKeep
        ));

        return $logger;
    }

	private static function getCoreLogPath() {
		$workbench = static::getWorkbench();

		$basePath = Filemanager::path_normalize($workbench->filemanager()->get_path_to_base_folder());

		$obj = $workbench->model()->get_object('exface.Core.LOG');
		$relativePath = $obj->get_data_address();

		return $basePath . '/' . $relativePath . '/log';
	}

	private static function getDetailsLogPath() {
		$workbench = static::getWorkbench();

		$basePath = Filemanager::path_normalize($workbench->filemanager()->get_path_to_base_folder());

		$obj = $workbench->model()->get_object('exface.Core.LOGDETAILS');
		$relativePath = $obj->get_data_address();

		return $basePath . '/' . $relativePath;
	}

	private static function getWorkbench() {
		global $exface;
		return $exface->ui()->get_workbench();
	}
}
