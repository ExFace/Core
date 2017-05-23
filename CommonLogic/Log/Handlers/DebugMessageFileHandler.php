<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use exface\Core\CommonLogic\Log\Formatters\MessageOnlyFormatter;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Widgets\DebugMessage;
use Monolog\Handler\StreamHandler;
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
		if ($sender) {
			$fileName = $context["id"] . $this->staticFilenamePart;
			if (!$fileName) {
				return;
			}

			$logger = new \Monolog\Logger("Stacktrace");
			$handler = new StreamHandler($this->dir . "/" . $fileName, $this->minLogLevel);
			$handler->setFormatter(new MessageOnlyFormatter());
			$logger->pushHandler($handler);

			$debugWidget = $sender->create_debug_widget($this->createDebugMessage());
			$debugWidgetData = $debugWidget->export_uxon_object()->to_json(true);
			$logger->log($level, $debugWidgetData);
		}
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
