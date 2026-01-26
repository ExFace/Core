<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Debugger\ExceptionMarkdownRenderer;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Debugger\ExceptionHtmlRenderer;
use Symfony\Component\ErrorHandler\ErrorHandler;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\CommonLogic\Debugger\CommunicationInterceptor;

class Debugger implements DebuggerInterface
{
    private $prettify_errors = false;
    
    private $error_reporting = null;

    private $logger = null;
    
    private $tracer = null;
    
    private ConfigurationInterface $config;
    
    private $communicationInterceptor = null;

    private $workbenchStartTimeMs = null;

    /**
     * 
     * @param LoggerInterface $logger
     * @param ConfigurationInterface $config
     * @param float $workbenchStartTimeMs
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function __construct(LoggerInterface $logger, ConfigurationInterface $config, float $workbenchStartTimeMs = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->workbenchStartTimeMs = $workbenchStartTimeMs ?? self::getTimeMsNow();
        try {
            $opt = $config->getOption('DEBUG.PHP_ERROR_REPORTING');
            if (preg_match('/[^a-z0-9.\s~&_^]+/i', $opt) !== 0) {
                throw new RuntimeException('Invalid configuration option DEBUG.PHP_ERROR_REPORTING: it must be a valid PHP syntax!');
            }
            $errorReportingFlags = eval('return ' . $opt . ';');
        } catch (\Throwable $e) {
            throw $e;
            $errorReportingFlags = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED;
        }
        $this->error_reporting = $errorReportingFlags;
        error_reporting($errorReportingFlags);
        
        $this->setPrettifyErrors($config->getOption('DEBUG.PRETTIFY_ERRORS'));
        if ($config->getOption('DEBUG.TRACE') === true) {
            try {
                $this->tracer = new Tracer($config->getWorkbench(), $this->workbenchStartTimeMs);
            } catch (\Throwable $e) {
                $logger->logException(new RuntimeException('Cannot start tracer! ' . $e->getMessage(), null, $e));
            }
        }
        if ($config->getOption('DEBUG.INTERCEPT_COMMUNICATION') === true) {
            try {
                $this->communicationInterceptor = new CommunicationInterceptor($config->getWorkbench());
            } catch (\Throwable $e) {
                $logger->logException(new RuntimeException('Cannot start communication intercepter! ' . $e->getMessage(), null, $e));
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DebuggerInterface::printException()
     */
    public static function printException(\Throwable $exception, $use_html = false) : string
    {
        $renderer = new ExceptionHtmlRenderer($exception);
        if ($use_html) {
            $output = $renderer->renderHtml(false);
        } else {
            $output = $renderer->renderAsString();
        }
        return $output;
    }

    /**
     * @param $exception
     * @return string
     */
    public static function printExceptionAsHtml($exception) : string
    {
        return static::printException($exception, true);
    }

    /**
     * @param \Throwable $exception
     * @return string
     */
    public static function printExceptionAsMarkdown(\Throwable $exception) : string
    {
        $renderer = new ExceptionMarkdownRenderer($exception);
        return $renderer->render();
    }

    /**
     * 
     * @param bool $value
     * @return \exface\Core\CommonLogic\Debugger
     */
    protected function setPrettifyErrors(bool $value) : DebuggerInterface
    {
        $this->prettify_errors = $value;
        if ($value === true) {
            $handler = new ErrorHandler(null, $this->prettify_errors);
            $handler->setDefaultLogger($this->logger, $this->error_reporting);
            ErrorHandler::register($handler, true);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DebuggerInterface::printVariable()
     */
    public static function printVariable($anything, $use_html = true, $expand_depth = 1)
    {
        $cloner = new VarCloner();
        if ($use_html) {
            $dumper = new HtmlDumper();
            return $dumper->dump($cloner->cloneVar($anything), true, ['maxDepth' => $expand_depth]);
        } else {
            $dumper = new CliDumper();
            // No colors
            $dumper->setColors(false);
            // If DUMP_LIGHT_ARRAY is set, then arrays are dumped in a shortened format similar to PHP's short array notation
            // $dumper->setFlags(CliDumper::DUMP_LIGHT_ARRAY);

            return $dumper->dump($cloner->cloneVar($anything), true);
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DebuggerInterface::getTimeMsOfWorkebnchStart()
     */
    public function getTimeMsOfWorkebnchStart() : float
    {
        return $this->workbenchStartTimeMs;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DebuggerInterface::getTimeMsNow()
     */
    public static function getTimeMsNow() : float
    {
        return microtime(true) * 1000;
    }
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DebuggerInterface::getTimeMsFromStart()
     */
    public function getTimeMsFromStart() : float
    {
        return self::getTimeMsNow() - $this->getTimeMsOfWorkebnchStart();
    }
    
    public function getLogData(string $logId, ?string $logFilePath = null, array $attrs = ['id', 'levelname', 'message', 'filepath', 'channel']) : DataSheetInterface
    {
        // Find the message in the log file. 
        // If we do not know the log file, search in all logs for the message id
        if (! $logFilePath) {
            $logFileSheet = DataSheetFactory::createFromObjectIdOrAlias($this->config->getWorkbench(), 'exface.Core.LOG');
            $logFileCol = $logFileSheet->getColumns()->addFromExpression('PATHNAME_RELATIVE');
            $logFileSheet->getFilters()->addConditionFromString('CONTENTS', $logId, ComparatorDataType::IS);
            $logFileSheet->dataRead();
            $logFile = $logFileCol->getValue(0);

            // If the message cannot be found in the logs, try the traces
            if (! $logFile) {
                $logFileSheet = DataSheetFactory::createFromObjectIdOrAlias($this->config->getWorkbench(), 'exface.Core.TRACE_LOG');
                $logFileCol = $logFileSheet->getColumns()->addFromExpression('PATHNAME_RELATIVE');
                $logFileSheet->getFilters()->addConditionFromString('CONTENTS', $logId, ComparatorDataType::IS);
                $logFileSheet->dataRead();
                $logFile = $logFileCol->getValue(0);
                if (! $logFile) {
                    throw new RuntimeException('Cannot file log message "' . $logId . '"');
                }
            }
        } else {
            $logFile = $logFilePath;
        }

        $logEntrySheet = DataSheetFactory::createFromObjectIdOrAlias($this->config->getWorkbench(), 'exface.Core.LOG_ENTRY');
        $logEntrySheet->getColumns()->addMultiple($attrs);
        $logEntrySheet->getFilters()->addConditionFromString('id', $logId, ComparatorDataType::EQUALS);
        $logEntrySheet->getFilters()->addConditionFromString('logfile', $logFile, ComparatorDataType::EQUALS);
        $logEntrySheet->dataRead();

        // TODO throw errors if no message could be found or it has no details file or the file does not exist, etc.
        
        return $logEntrySheet;
    }
}