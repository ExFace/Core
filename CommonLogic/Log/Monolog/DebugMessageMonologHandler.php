<?php
namespace exface\Core\CommonLogic\Log\Monolog;

use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\DebugMessage;
use Monolog\Handler\StreamHandler;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\UxonObject;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use exface\Core\CommonLogic\Log\Processors\DebugWidgetProcessor;

/**
 * Monolog handler to save debug messages to a specified folder.
 * 
 * @author Andrej Kabachnik
 *
 */
class DebugMessageMonologHandler extends AbstractProcessingHandler implements HandlerInterface
{
    private $dir;

    private $staticFilenamePart;
    
    private $filenameRecordKey;
    
    private $workbench;
    
    private $debugGeneratorContextKey;

    /**
     * 
     * @param Workbench $workbench
     * @param string $debugGeneratorContextKey
     * @param string $dir
     * @param string $filenameRecordKey
     * @param string $fileSuffix
     * @param int $minLogLevel
     */
    public function __construct(Workbench $workbench, string $debugGeneratorContextKey, string $dir, string $filenameRecordKey, string $fileSuffix, int $minLogLevel)
    {
        parent::__construct($minLogLevel);
        $this->workbench = $workbench;
        $this->dir                = $dir;
        $this->staticFilenamePart = $fileSuffix;
        $this->debugGeneratorContextKey = $debugGeneratorContextKey;
        $this->filenameRecordKey = $filenameRecordKey;
    }
    
    /**
     * Generates a JSON-dump of an HTML-widget with the given contents. 
     * 
     * This is handy if the regular DebugWidget cannot be created for some 
     * reason. Using this fallback it is still possible to create a readable
     * debug widget.
     * 
     * @param string $html
     * @return string
     */
    protected function createHtmlFallback($html){
        $uxon = new UxonObject();
        $uxon->setProperty('widget_type', 'Html');
        $uxon->setProperty('html', $html);
        return $uxon->toJson(true);
    }
    
    /**
     * 
     * @return \exface\Core\Widgets\DebugMessage
     */
    protected function createDebugMessage()
    {
        $page = UiPageFactory::createEmpty($this->workbench);

        $debugMessage = new DebugMessage($page);
        $debugMessage->setMetaObject($page->workbench->model()->getObject('exface.Core.MESSAGE'));

        return $debugMessage;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Monolog\Handler\AbstractProcessingHandler::write()
     */
    protected function write(array $record)
    {
        $fileName = $record[$this->filenameRecordKey] . $this->staticFilenamePart;
        // Do no do anything if no file name could be determined or the file exists. The latter
        // case is important as if two loggers would attempt to log to the same file (e.g. the
        // main logger and the debug logger), the file would have double content and it would
        // be impossible to parse it. Since the file name is the id of the debug message, we
        // can be sure that it's content will be the same every time we attempt to write it.
        if (! $fileName || file_exists($this->dir . "/" . $fileName)) {
            return;
        }
        
        $streamHandler = new StreamHandler($this->dir . "/" . $fileName, $this->getLevel());
        $streamHandler->setFormatter(new MessageOnlyFormatter());
        $streamHandler->pushProcessor(new DebugWidgetProcessor($this->workbench, $this->debugGeneratorContextKey, 'message'));
        
        return $streamHandler->handle($record);
    }
}
