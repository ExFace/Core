<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use Monolog\Logger;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use Monolog\Handler\ErrorLogHandler;

/**
 * Logs to PHP error_log via Monolog
 * 
 * @author Andrej Kabachnik
 *
 */
class MonologErrorLogHandler implements LogHandlerInterface
{
    /** @var Logger $monolog */
    private $monolog = null;
    
    private $channelName;
    
    private $minLevel;

    /**
     * 
     * @param string $channelName
     * @param string $ignoreBelowLevel
     */
    public function __construct(string $channelName, string $ignoreBelowLevel = LoggerInterface::CRITICAL)
    {
        $this->channelName = $channelName;
        $this->minLevel = $ignoreBelowLevel;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->getMonolog()->log($level, $message, $context);
    }
    
    /**
     * 
     * @return Logger
     */
    protected function getMonolog() : Logger
    {
        if ($this->monolog === null) {
            $this->monolog = new Logger($this->channelName);            
            $this->monolog->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::toMonologLevel($this->minLevel)));
        }
            
        return $this->monolog;
    }
}
