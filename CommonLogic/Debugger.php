<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Debugger\ExceptionRenderer;
use Symfony\Component\ErrorHandler\ErrorHandler;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\CommonLogic\Debugger\CommunicationInterceptor;

class Debugger implements DebuggerInterface
{
    private $prettify_errors = false;
    
    private $error_reporting = null;

    private $logger = null;
    
    private $tracer = null;
    
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
            $this->tracer = new Tracer($config->getWorkbench(), $this->workbenchStartTimeMs);
        }
        if ($config->getOption('DEBUG.INTERCEPT_COMMUNICATION') === true) {
            $this->communicationInterceptor = new CommunicationInterceptor($config->getWorkbench());
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::printException()
     */
    public function printException(\Throwable $exception, $use_html = true)
    {
        $renderer = new ExceptionRenderer($exception);
        if ($use_html) {
            $output = $renderer->renderHtml(false);
        } else {
            $output = strip_tags($renderer->renderHtml());
        }
        return $output;
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
    public function printVariable($anything, $use_html = true, $expand_depth = 1)
    {
        $cloner = new VarCloner();
        if ($use_html) {
            $dumper = new HtmlDumper();
            return $dumper->dump($cloner->cloneVar($anything), true, ['maxDepth' => $expand_depth]);
        } else {
            $dumper = new CliDumper();
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
}