<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\LogHandlerInterface;
use exface\Core\Widgets\DebugMessage;
use FemtoPixel\Monolog\Handler\CsvHandler;
use Monolog\Logger;

class DebugMessageFileHandler implements LogHandlerInterface {
	private $dir;
	private $minLogLevel;
	private $staticFilenamePart;

	/**
	 * DebugMessageFileHandler constructor.
	 *
	 * @param $dir
	 * @param $staticFilenamePart
	 * @param $minLogLevel
	 */
	function __construct($dir, $staticFilenamePart, $minLogLevel = Logger::DEBUG) {
		$this->dir                = $dir;
		$this->staticFilenamePart = $staticFilenamePart;
		$this->minLogLevel        = $minLogLevel;
	}

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		$fileName = $context["id"] . $this->staticFilenamePart;
		if (!$fileName) {
			return;
		}

		$logger = new \Monolog\Logger("Stacktrace");
		$logger->pushHandler(new CsvHandler($this->dir . "/" . $fileName, $this->minLogLevel));

		$debugWidget = $sender->create_debug_widget($this->createDebugMessage());
		$logger->log($level, $debugWidget->export_uxon_object()->to_json(), $this->prepareContext($context));
	}

	protected function prepareContext($context) {
		// do not log the exception in this handler
		if (isset($context["exception"])) {
			unset($context["exception"]);
		}

		return $context;
	}

	protected function createDebugMessage() {
		global $exface;
		$ui   = $exface->ui();
		$page = UiPageFactory::create_empty($ui);

		$debugMessage = new DebugMessage($page);
		$debugMessage->set_meta_object($page->get_workbench()->model()->get_object('exface.Core.ERROR'));

		return $debugMessage;
	}
}
