<?php

namespace exface\Core\Log\Handlers;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\LogHandlerInterface;
use exface\Core\Widgets\DebugMessage;
use FemtoPixel\Monolog\Handler\CsvHandler;

class DebugMessageFileHandler implements LogHandlerInterface {
	private $dir;

	/**
	 * DebugMessageFileHandler constructor.
	 *
	 * @param $dir
	 */
	function __construct( $dir ) {
		$this->dir = $dir;
	}

	public function handle( $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null ) {
		$fileName = $context["id"];
		if ( ! $fileName ) {
			return;
		}

		$logger = new \Monolog\Logger( "Stacktrace" );
		$logger->pushHandler( new CsvHandler( $this->dir . "/" . $fileName, $level ) );

		$debugWidget = $sender->create_debug_widget( $this->createDebugMessage() );
		$logger->error( $debugWidget->export_uxon_object()->to_json(), $this->prepareContext( $context ) );
	}

	protected function prepareContext( $context ) {
		// do not log the exception in this handler
		if ( isset( $context["exception"] ) ) {
			unset( $context["exception"] );
		}

		return $context;
	}

	protected function createDebugMessage() {
		global $exface;
		$ui   = $exface->ui();
		$page = UiPageFactory::create_empty( $ui );

		$debugMessage = new DebugMessage( $page );
		$debugMessage->set_meta_object( $page->get_workbench()->model()->get_object( 'exface.Core.ERROR' ) );

		return $debugMessage;
	}
}
