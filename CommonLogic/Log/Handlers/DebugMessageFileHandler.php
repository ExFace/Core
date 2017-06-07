<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\Formatters\MessageOnlyFormatter;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Widgets\DebugMessage;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class DebugMessageFileHandler implements LogHandlerInterface
{

    private $dir;

    private $minLogLevel;

    private $staticFilenamePart;

    /**
     * DebugMessageFileHandler constructor.
     *
     * @param
     *            $dir
     * @param
     *            $staticFilenamePart
     * @param
     *            $minLogLevel
     */
    function __construct($dir, $staticFilenamePart, $minLogLevel = Logger::DEBUG)
    {
        $this->dir = $dir;
        $this->staticFilenamePart = $staticFilenamePart;
        $this->minLogLevel = $minLogLevel;
    }

    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        // check log level and return if it is smaller than min log level, otherwise debug widget will be created also
        // if this is not logged in the underlying log handler
        if (\Monolog\Logger::toMonologLevel($level) < \Monolog\Logger::toMonologLevel($this->minLogLevel))
            return true;

        if ($sender) {
            $fileName = $context["id"] . $this->staticFilenamePart;
            if (! $fileName) {
                return;
            }
            
            $logger = new \Monolog\Logger("Stacktrace");
            $handler = new StreamHandler($this->dir . "/" . $fileName, $this->minLogLevel);
            $handler->setFormatter(new MessageOnlyFormatter());
            $logger->pushHandler($handler);
            
            $debugWidget = $sender->createDebugWidget($this->createDebugMessage());
            $debugWidgetData = $debugWidget->exportUxonObject()->toJson(true);
            $logger->log($level, $debugWidgetData);
        }
    }

    protected function prepareContext($context)
    {
        // do not log the exception in this handler
        if (isset($context["exception"])) {
            unset($context["exception"]);
        }
        
        return $context;
    }

    protected function createDebugMessage()
    {
        global $exface;
        $ui = $exface->ui();
        $page = UiPageFactory::createEmpty($ui);
        
        $debugMessage = new DebugMessage($page);
        $debugMessage->setMetaObject($page->getWorkbench()->model()->getObject('exface.Core.ERROR'));
        
        return $debugMessage;
    }
}
