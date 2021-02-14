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

class Debugger implements DebuggerInterface
{

    private $prettify_errors = false;

    private $logger = null;

    function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        
        $this->setPrettifyErrors(false);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DebuggerInterface::printException()
     */
    public function printException(\Throwable $exception, $use_html = true)
    {
        if (! $exception instanceof \Exception){
            if ($exception instanceof \Error){
                $error = $exception;
                $exception = new \ErrorException('Internal PHP error: see description below!', $error->getCode(), null, $error->getFile(), $error->getLine(), $error);
            } else {
                throw new RuntimeException('Cannot print exception of type ' . gettype($exception) . ' (' . get_class($exception) . ')!');
            }
        }
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
        $this->prettify_errors = true; //\exface\Core\DataTypes\BooleanDataType::cast($value);
        $handler = new ErrorHandler(null, $this->prettify_errors);
        $handler->setDefaultLogger($this->logger, E_ALL & ~E_NOTICE);
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
