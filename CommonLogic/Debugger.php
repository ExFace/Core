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

class Debugger implements DebuggerInterface
{

    private $prettify_errors = false;
    
    private $error_reporting = null;

    private $logger = null;

    function __construct(LoggerInterface $logger, ConfigurationInterface $config)
    {
        $this->logger = $logger;
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
        $this->prettify_errors = true;
        $handler = new ErrorHandler(null, $this->prettify_errors);
        $handler->setDefaultLogger($this->logger, $this->error_reporting);
        ErrorHandler::register($handler, true);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
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
}
