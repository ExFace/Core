<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Debugger implements DebuggerInterface
{

    private $prettify_errors = false;

    private $logger = null;

    function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::printException()
     */
    public function printException(\Throwable $exception, $use_html = true)
    {
        $handler = new ExceptionHandler();        
        $flattened_exception = FlattenException::create($exception);
        if ($use_html) {
            $output = <<<HTML
    <style>
            /*body { background-color: #F9F9F9; color: #222; font: 14px/1.4 Helvetica, Arial, sans-serif; margin: 0; padding-bottom: 45px; }
*/
            .exception a { cursor: pointer; text-decoration: none; }
            .exception a:hover { text-decoration: underline; }
            .exception abbr[title] { border-bottom: none; cursor: help; text-decoration: none; }

            .exception code, .exception pre { font: 13px/1.5 Consolas, Monaco, Menlo, "Ubuntu Mono", "Liberation Mono", monospace; }

            .exception table, .exception tr, .exception th, .exception td { background: #FFF; border-collapse: collapse; vertical-align: top; }
            .exception table { background: #FFF; border: 1px solid #E0E0E0; box-shadow: 0px 0px 1px rgba(128, 128, 128, .2); margin: 1em 0; width: 100%; }
            .exception table th, .exception table td { border: solid #E0E0E0; border-width: 1px 0; padding: 8px 10px; }
            .exception table th { background-color: #E0E0E0; font-weight: bold; text-align: left; }

            .exception .hidden-xs-down { display: none; }
            .exception .block { display: block; }
            .exception .break-long-words { -ms-word-break: break-all; word-break: break-all; word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; }
            .exception .text-muted { color: #999; }

            .exception .container { max-width: 1024px; margin: 0 auto; padding: 0 15px; }
            .exception .container::after { content: ""; display: table; clear: both; }

            .exception .exception-summary { display: none }

            .exception .exception-message-wrapper { display: flex; align-items: center; min-height: 70px; }
            .exception .exception-message { flex-grow: 1; padding: 30px 0; }
            .exception .exception-message, .exception .exception-message a { color: #FFF; font-size: 21px; font-weight: 400; margin: 0; }
            .exception .exception-message.long { font-size: 18px; }
            .exception .exception-message a { text-decoration: none; }
            .exception .exception-message a:hover { text-decoration: underline; }

            .exception .exception-illustration { flex-basis: 111px; flex-shrink: 0; height: 66px; margin-left: 15px; opacity: .7; }

            .exception .trace + .trace { margin-top: 30px; }
            .exception .trace-head .trace-class { color: #222; font-size: 18px; font-weight: bold; line-height: 1.3; margin: 0; position: relative; }

            .exception .trace-message { font-size: 14px; font-weight: normal; margin: .5em 0 0; }

            .exception .trace-file-path, .trace-file-path a { margin-top: 3px; color: #999; color: #795da3; color: #B0413E; color: #222; font-size: 13px; }
            .exception .trace-class { color: #B0413E; }
            .exception .trace-type { padding: 0 2px; }
            .exception .trace-method { color: #B0413E; color: #222; font-weight: bold; color: #B0413E; }
            .exception .trace-arguments { color: #222; color: #999; font-weight: normal; color: #795da3; color: #777; padding-left: 2px; }

            @media (min-width: 575px) {
                .exception .hidden-xs-down { display: initial; }
            }
    </style>
    <div class="exception">
        {$handler->getContent($flattened_exception)}
    </div>
HTML;
        } else {
            $output = strip_tags($handler->getContent($flattened_exception));
        }
        return $output;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::getPrettifyErrors()
     */
    public function getPrettifyErrors()
    {
        return $this->prettify_errors;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::setPrettifyErrors()
     */
    public function setPrettifyErrors($value)
    {
        $this->prettify_errors = \exface\Core\DataTypes\BooleanDataType::parse($value);
        if ($this->prettify_errors) {
            $this->registerHandler();
        }
        return $this;
    }

    protected function registerHandler()
    {
        // Debug::enable(E_ALL & ~E_NOTICE);
        ExceptionHandler::register();
        ErrorHandler::register();
        
        // register logger
        $handler = new \Monolog\ErrorHandler($this->logger);
        $handler->registerErrorHandler([], false);
        $handler->registerExceptionHandler();
        $handler->registerFatalHandler();
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
            $dumper->setDisplayOptions(array(
                'maxDepth' => $expand_depth
            ));
        } else {
            $dumper = new CliDumper();
        }
        return $dumper->dump($cloner->cloneVar($anything), true);
    }
}
