<?php
namespace exface\Core\Facades\ConsoleFacade;

use exface\Core\CommonLogic\Debugger\ExceptionCliRenderer;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Helper class for convenient CLI output
 */
class CliOutputPrinter
{
    const KEYWORD_ERROR = 'ERROR: ';
    const KEYWORK_FAILED = 'FAILED ';
    
    public static function printExceptionMessage(\Throwable $e, bool $includeFileLine = true, string $prefix = self::KEYWORD_ERROR) : string
    {
        $msg = $prefix . $e->getMessage();
        if ($includeFileLine) {
            $msg .= ' in ' . $e->getFile() . ':' . $e->getLine();
        }
        if ($e instanceof ExceptionInterface) {
            $msg .= " (see Log-ID {$e->getId()})";
        }
        return $msg;
    }
    
    public static function printExceptionWithAllTraces(\Throwable $e, string $indent = '', string $prefix = '') : void
    {
        $renderer = new ExceptionCliRenderer($e);
        echo $prefix . $renderer->render($indent);
    }

    public static function printExceptionWithBottomTraceOnly(\Throwable $e, string $indent = '', string $prefix = '') : string
    {
        $renderer = new ExceptionCliRenderer($e);
        $renderer->setIncludeTrace(true, true);
        return $prefix . $renderer->render($indent);
    }

    public static function printExceptionWithoutTraces(\Throwable $e, string $indent = '', string $prefix = '') : string
    {
        $renderer = new ExceptionCliRenderer($e);
        $renderer->setIncludeTrace(false);
        return $prefix . $renderer->render($indent);
    }
    
    public static function printLine(string $line) : string
    {
        return $line . PHP_EOL;
    }
    
    public static function printBorder(string $symbol = '-') : string
    {
        return str_repeat($symbol, 80) . PHP_EOL;
    }
    
    public static function printBordered(string $title, string $symbol = '-') : string
    {
        return PHP_EOL 
            . static::printBorder($symbol)
            . $title . PHP_EOL
            . static::printBorder($symbol);
    }
}