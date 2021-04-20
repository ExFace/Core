<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\Processors\ActionAliasProcessor;
use exface\Core\CommonLogic\Log\Processors\IdProcessor;
use exface\Core\CommonLogic\Log\Processors\RequestIdProcessor;
use exface\Core\CommonLogic\Log\Processors\UserNameProcessor;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use FemtoPixel\Monolog\Handler\CsvHandler;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Logger;
use exface\Core\CommonLogic\Workbench;
use Monolog\Handler\GroupHandler;
use exface\Core\CommonLogic\Log\Monolog\DebugMessageMonologHandler;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\CommonLogic\Log\Processors\ContextFilterProcessor;
use Monolog\Handler\ErrorLogHandler;

/**
 * Logs to the main workbench CSV log and places debug widget JSONs in a separate folder.
 * 
 * What exactly is being logged depends on the log level thresholds provided in the constructor.
 * The logger will accumulate all messages with level `$ignoreBelowLevel` or above, but discard
 * them if no message of at least level `$persistAllAfterLevel` is received. If such a trigger
 * message arives, all accumulated messages are dumped to the log.
 * 
 * Additionally the `$passthroughLevel` allows to log messages startig with this level regularly
 * without triggering the total dump.
 * 
 * @author andrej.kabachnik
 *
 */
class MonologCsvFileHandler implements LogHandlerInterface
{

    private $channelName;

    private $csvFilePath;

    private $debugMsgDirPath;
    
    private $workbench;

    private $minLevel;
    
    private $persistLevel;
    
    private $passthroughLevel;
    
    /** @var Logger $monolog */
    private $monolog = null;

    /**
     * 
     * @param Workbench $workbench
     * @param string $channelName
     * @param string $csvFilePath
     * @param string $debugMsgDirPath
     * @param string $ignoreBelowLevel completely ignore messages below this level
     * @param string $persistAllAfterLevel dump all accumulated messages when a message with at least this level is received
     * @param string $passthroughLevel log messages with this level or above regularly (only them, not the accumulated ones)
     */
    public function __construct(Workbench $workbench, string $channelName, string $csvFilePath, string $debugMsgDirPath = null, string $ignoreBelowLevel = LoggerInterface::DEBUG, string $persistAllAfterLevel = LoggerInterface::ALERT, string $passthroughLevel = LoggerInterface::ERROR)
    {
        $this->channelName = $channelName;
        $this->csvFilePath = $csvFilePath;
        $this->workbench = $workbench;
        $this->minLevel = $ignoreBelowLevel;
        $this->persistLevel = $persistAllAfterLevel;
        $this->passthroughLevel = $passthroughLevel;
        $this->debugMsgDirPath = $debugMsgDirPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        if ($sender !== null) {
            $context['sender'] = $sender;
        }
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
            try {
                // create csv log handler and set formatter with customized date format
                $csvHandler = new CsvHandler($this->csvFilePath, $this->minLevel);
                $csvHandler->setFormatter(new NormalizerFormatter("Y-m-d H:i:s.v")); // with milliseconds
                $csvHandler->pushProcessor(new IdProcessor());
                $csvHandler->pushProcessor(new RequestIdProcessor($this->workbench));
                $csvHandler->pushProcessor(new UsernameProcessor($this->workbench));
                $csvHandler->pushProcessor(new ActionAliasProcessor($this->workbench));
                $csvHandler->pushProcessor(new ContextFilterProcessor(['exception', 'sender']));
                
                $dbgMsgHandler = new DebugMessageMonologHandler(
                    $this->workbench, 
                    'sender',
                    $this->debugMsgDirPath, 
                    'id',
                    '.json', 
                    Logger::toMonologLevel($this->minLevel)
                );
                $dbgMsgHandler->pushProcessor(new IdProcessor());
                
                $grpHandler = new GroupHandler([
                    $csvHandler,
                    $dbgMsgHandler
                ]);
                
                if ($this->persistLevel === LoggerInterface::DEBUG) {
                    $this->monolog->pushHandler($grpHandler);
                } else {
                    $fcHandler = new FingersCrossedHandler(
                        $grpHandler,
                        new ErrorLevelActivationStrategy(Logger::toMonologLevel($this->persistLevel)),
                        0,
                        true,
                        true,
                        Logger::toMonologLevel($this->passthroughLevel)
                    );
                    $this->monolog->pushHandler($fcHandler);
                }
            } catch (\Throwable $e) {
                $this->monolog->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::toMonologLevel($this->minLevel)));
                $this->monolog->critical('Failed to initialized log handler "MonologCsvFileHandler": ' . $e->getMessage(), ['exception' => $e]);
            }
        }
            
        return $this->monolog;
    }
}