<?php

namespace exface\Core\Log\Handlers;


use exface\Core\Log\Processors\IdProcessor;
use FemtoPixel\Monolog\Handler\CsvHandler;
use Monolog\Logger;

class LogfileHandler extends AbstractMonologHandler {
	/**
	 * @param resource|string $stream
	 * @param int $level The minimum logging level at which this handler will be triggered
	 * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
	 * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
	 * @param Boolean $useLocking Try to lock log file before doing any writes
	 *
	 * @throws \Exception                If a missing directory is not buildable
	 * @throws \InvalidArgumentException If stream is not a resource or string
	 */
	function __construct( $name, $stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false ) {
		$logger = new Logger( $name );
		$logger->pushHandler( new CsvHandler( $stream, $level, $bubble, $filePermission, $useLocking ) );
		$logger->pushProcessor( new IdProcessor() );

		parent::__construct( $logger );
	}
}
