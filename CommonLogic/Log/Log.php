<?php

namespace exface\Core\CommonLogic\Log;


use exface\Core\CommonLogic\Filemanager;
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

        $coreLogPath = static::getCoreLogPath();
	    $detailsLogPath = static::getDetailsLogPath();

        $logger->pushHandler(new LogfileHandler("exface", $coreLogPath));
        // TODO tvw enable when log details are used
//        $logger->pushHandler(new DebugMessageFileHandler($detailsLogPath));

        return $logger;
    }

	private static function getCoreLogPath() {
		global $exface;
		$workbench = $exface->ui()->get_workbench();

		$basePath = Filemanager::path_normalize($workbench->filemanager()->get_path_to_base_folder());

		$obj = $workbench->model()->get_object('exface.Core.LOG');
		$relativePath = $obj->get_data_address();

		return $basePath . '/' . $relativePath;
	}

	private static function getDetailsLogPath() {
		global $exface;
		$workbench = $exface->ui()->get_workbench();

		$basePath = Filemanager::path_normalize($workbench->filemanager()->get_path_to_base_folder());

		$obj = $workbench->model()->get_object('exface.Core.LOGDETAILS');
		$relativePath = $obj->get_data_address();

		return $basePath . '/' . $relativePath;
	}
}
