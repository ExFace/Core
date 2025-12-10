<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\Interfaces\Exceptions\iContainCustomTrace;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;

if (!function_exists('get_debug_type')) {
    function get_debug_type($value): string { return \Symfony\Polyfill\Php80::get_debug_type($value); }
}

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractExceptionRenderer
{
    protected $message;
    protected $code;
    protected $previous;
    protected $trace;
    protected $traceAsString;
    protected $class;
    protected $file;
    protected $line;
    protected $statusCode = null;

    protected $maxArgChars = 500;
    protected $maxArgArrayItems = 100;
    
    public function __construct(\Throwable $exception, int $maxArgChars = 500, int $maxArgArrayItems = 100)
    {
        $this->code = $exception->getCode();
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();
        $this->maxArgArrayItems = $maxArgArrayItems;
        $this->maxArgChars = $maxArgChars;
        
        $this->setMessage($exception->getMessage());
        $this->setTraceFromThrowable($exception);
        $this->setClass(get_debug_type($exception));
        
        if ($exception instanceof ExceptionInterface) {
            $this->statusCode = $exception->getStatusCode();
        }
    }
    
    public function toArray()
    {
        $exceptions = [];
        foreach (array_merge([$this], $this->getAllPrevious()) as $exception) {
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'class' => $exception->getClass(),
                'trace' => $exception->getTrace(),
            ];
        }
        
        return $exceptions;
    }
    
    public function getStatusCode()
    {
        return $this->statusCode;
    }
    
    public function getClass()
    {
        return $this->class;
    }
    
    /**
     * @return $this
     */
    protected function setClass($class)
    {
        $this->class = false !== strpos($class, "@anonymous\0") ? (get_parent_class($class) ?: key(class_implements($class)) ?: 'class').'@anonymous' : $class;
        
        return $this;
    }
    
    public function getFile()
    {
        return $this->file;
    }
    
    public function getLine()
    {
        return $this->line;
    }
    
    public function getMessage()
    {
        return $this->message;
    }
    
    /**
     * @return $this
     */
    protected function setMessage($message)
    {
        if (false !== strpos($message, "@anonymous\0")) {
            $message = preg_replace_callback('/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)[0-9a-fA-F]++/', function ($m) {
                return class_exists($m[0], false) ? (get_parent_class($m[0]) ?: key(class_implements($m[0])) ?: 'class').'@anonymous' : $m[0];
            }, $message);
        }
        
        $this->message = $message;
        
        return $this;
    }
    
    public function getCode()
    {
        return $this->code;
    }
    
    public function getPrevious()
    {
        return $this->previous;
    }
    
    public function getAllPrevious()
    {
        $exceptions = [];
        $e = $this;
        while ($e = $e->getPrevious()) {
            $exceptions[] = $e;
        }
        
        return $exceptions;
    }
    
    public function getTrace()
    {
        return $this->trace;
    }
    
    public function setTraceFromThrowable(\Throwable $throwable)
    {
        $this->traceAsString = $throwable->getTraceAsString();
        
        if ($throwable instanceof iContainCustomTrace) {
            // Wenn der Fehler einen custom Stacktrace enthaelt, dann diesen verwenden.
            // Das ist notwendig, da es in PHP nicht moeglich ist den Stacktrace
            // manuell zu setzen oder zu veraendern.
            return $this->setTrace($throwable->getStacktrace(), null, null);
        } else {
            return $this->setTrace($throwable->getTrace(), $throwable->getFile(), $throwable->getLine());
        }
    }
    
    /**
     * @return $this
     */
    public function setTrace($trace, $file, $line)
    {
        $this->trace = [];
        $this->trace[] = [
            'namespace' => '',
            'short_class' => '',
            'class' => '',
            'type' => '',
            'function' => '',
            'file' => $file,
            'line' => $line,
            'args' => [],
        ];
        foreach ($trace as $entry) {
            $class = '';
            $namespace = '';
            if (isset($entry['class'])) {
                $parts = explode('\\', $entry['class']);
                $class = array_pop($parts);
                $namespace = implode('\\', $parts);
            }
            
            $this->trace[] = [
                'namespace' => $namespace,
                'short_class' => $class,
                'class' => isset($entry['class']) ? $entry['class'] : '',
                'type' => isset($entry['type']) ? $entry['type'] : '',
                'function' => isset($entry['function']) ? $entry['function'] : null,
                'file' => isset($entry['file']) ? $entry['file'] : null,
                'line' => isset($entry['line']) ? $entry['line'] : null,
                'args' => isset($entry['args']) ? $this->flattenArgs($entry['args']) : [],
            ];
        }
        
        return $this;
    }
    
    private function flattenArgs(array $args, int $level = 0, int &$count = 0): array
    {
        $result = [];
        foreach ($args as $key => $value) {
            if (++$count > $this->maxArgArrayItems) {
                return ['array', '*SKIPPED over ' . $this->maxArgArrayItems . ' entries*'];
            }
            if ($value instanceof \__PHP_Incomplete_Class) {
                // is_object() returns false on PHP<=7.1
                $result[$key] = ['incomplete-object', $this->getClassNameFromIncomplete($value)];
            } elseif (\is_object($value)) {
                $result[$key] = ['object', \get_class($value)];
            } elseif (\is_array($value)) {
                if ($level > 10) {
                    $result[$key] = ['array', '*DEEP NESTED ARRAY*'];
                } else {
                    $result[$key] = ['array', $this->flattenArgs($value, $level + 1, $count)];
                }
            } elseif (null === $value) {
                $result[$key] = ['null', null];
            } elseif (\is_bool($value)) {
                $result[$key] = ['boolean', $value];
            } elseif (\is_int($value)) {
                $result[$key] = ['integer', $value];
            } elseif (\is_float($value)) {
                $result[$key] = ['float', $value];
            } elseif (\is_resource($value)) {
                $result[$key] = ['resource', get_resource_type($value)];
            } else {
                $result[$key] = ['string', (string) $value];
            }
        }
        
        return $result;
    }
    
    private function getClassNameFromIncomplete(\__PHP_Incomplete_Class $value): string
    {
        $array = new \ArrayObject($value);
        
        return $array['__PHP_Incomplete_Class_Name'];
    }
    
    public function getTraceAsString()
    {
        return $this->traceAsString;
    }
    
    public function renderAsString()
    {
        $message = '';
        $next = false;
        
        foreach (array_reverse(array_merge([$this], $this->getAllPrevious())) as $exception) {
            if ($next) {
                $message .= 'Next ';
            } else {
                $next = true;
            }
            $message .= $exception->getClass();
            
            if ('' != $exception->getMessage()) {
                $message .= ': '.$exception->getMessage();
            }
            
            $message .= ' in '.$exception->getFile().':'.$exception->getLine().
            "\nStack trace:\n".$exception->getTraceAsString()."\n\n";
        }
        
        return rtrim($message);
    }
    
    /**
     * 
     * @param \Exception $exception
     */
    public function setTraceFromException(\Exception $exception)
    {
        if ($exception instanceof iContainCustomTrace) {
            // Wenn der Fehler einen custom Stacktrace enthaelt, dann diesen verwenden.
            // Das ist notwendig, da es in PHP nicht moeglich ist den Stacktrace
            // manuell zu setzen oder zu veraendern.
            $this->setTrace($exception->getStacktrace(), null, null);
        } else {
            $this->setTrace($exception->getTrace(), $exception->getFile(), $exception->getLine());
        }
    }
}