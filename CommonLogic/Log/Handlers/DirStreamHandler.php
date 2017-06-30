<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class DirStreamHandler extends StreamHandler
{
    private $baseUrl = null;

    public function __construct(
        $stream,
        $level = Logger::DEBUG,
        $bubble = true,
        $filePermission = null,
        $useLocking = false
    ) {
        $this->baseUrl = $stream;
        parent::__construct($stream, $level, $bubble, $filePermission, $useLocking);
    }


    protected function write(array $record)
    {
        $this->stream = null;
        $this->url    = $this->baseUrl . $record['context']['filename'];

        parent::write($record);
    }
}
