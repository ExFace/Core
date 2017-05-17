<?php

namespace exface\Core\CommonLogic\Log;


use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Log\Handlers\DebugMessageFileHandler;
use exface\Core\CommonLogic\Log\Handlers\limit\FileLimitingLogHandler;
use exface\Core\CommonLogic\Log\Handlers\limit\DirLimitingLogHandler;
use exface\Core\CommonLogic\Log\Handlers\LogfileHandler;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\LoggerInterface;
use exface\Core\Interfaces\LogHandlerInterface;
use Monolog\ErrorHandler;

class Log
{
	/** @var Logger $logger */
	private static $logger = null;

	/** @var LogHandlerInterface[] $errorLogHandlers */
	private static $errorLogHandlers = null;

	/**
     * Creates a LoggerInterface implementation if none is there yet and returns it after adding default
     * LogHandlerInterface instances to it.
     *
     * @param Workbench $workbench
     *
     * @return LoggerInterface
     */
    public static function getErrorLogger($workbench)
    {
    	if (!static::$logger) {
		    static::$logger = new Logger();

		    foreach (static::getErrorLogHandlers($workbench) as $handler) {
			    static::$logger->pushHandler($handler);
		    }
	    }

        return static::$logger;
    }

	private static function getCoreLogPath($workbench) {
		$basePath = Filemanager::path_normalize($workbench->filemanager()->get_path_to_base_folder());

		$obj = $workbench->model()->get_object('exface.Core.LOG');
		$relativePath = $obj->get_data_address();

		return $basePath . '/' . $relativePath;
	}

	private static function getDetailsLogPath($workbench) {
		$basePath = Filemanager::path_normalize($workbench->filemanager()->get_path_to_base_folder());

		$obj = $workbench->model()->get_object('exface.Core.LOGDETAILS');
		$relativePath = $obj->get_data_address();

		return $basePath . '/' . $relativePath;
	}

	/**
	 * Registers error logger at monolog ErrorHandler.
	 * 
	 * @param $workbench
	 */
	public static function registerErrorLogger($workbench) {
		ErrorHandler::register(static::getErrorLogger($workbench));
	}

	protected static function getErrorLogHandlers($workbench) {
    	if (!static::$errorLogHandlers) {
    		self::$errorLogHandlers = array();

		    $coreLogFilePath = static::getCoreLogPath($workbench);
		    $detailsLogBasePath = static::getDetailsLogPath($workbench);

		    $minLogLevel = $workbench->get_config()->get_option('LOG.MINIMUM_LEVEL_TO_LOG');
		    $maxDaysToKeep = $workbench->get_config()->get_option('LOG.MAX_DAYS_TO_KEEP');
		    $detailsStaticFilenamePart = $workbench->get_config()->get_option('LOGDETAILS.STATIC_FILENAME_PART');

    		self::$errorLogHandlers["filelog"] = new FileLimitingLogHandler(
			    new LogfileHandler("exface", "", $minLogLevel), // real file name is determined late by FileLimitingLogHandler
			    $coreLogFilePath,
			    $maxDaysToKeep
		    );

		    self::$errorLogHandlers["detaillog"] = new DirLimitingLogHandler(
			    new DebugMessageFileHandler($detailsLogBasePath, $detailsStaticFilenamePart, $minLogLevel),
			    $detailsLogBasePath,
			    $detailsStaticFilenamePart,
			    $maxDaysToKeep
		    );
	    }

	    return static::$errorLogHandlers;
	}
}
