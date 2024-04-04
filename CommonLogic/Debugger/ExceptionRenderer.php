<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\Interfaces\Exceptions\iContainCustomTrace;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\DataTypes\StringDataType;

if (!function_exists('get_debug_type')) {
    function get_debug_type($value): string { return \Symfony\Polyfill\Php80::get_debug_type($value); }
}

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class ExceptionRenderer
{
    private $message;
    private $code;
    private $previous;
    private $trace;
    private $traceAsString;
    private $class;
    private $file;
    private $line;
    private $charset = null;
    private $statusCode = null;
    
    private $maxArgChars = 500;
    private $maxArgArrayItems = 100;
    
    public function __construct(\Throwable $exception, string $charset = null, int $maxArgChars = 500, int $maxArgArrayItems = 100)
    {
        $this->code = $exception->getCode();
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();
        $this->charset = $charset;
        $this->maxArgArrayItems = $maxArgArrayItems;
        $this->maxArgChars = $maxArgChars;
        
        $this->setMessage($exception->getMessage());
        $this->setTraceFromThrowable($exception);
        $this->setClass(get_debug_type($exception));
        
        if ($exception instanceof ExceptionInterface) {
            $this->statusCode = $exception->getStatusCode();
        }
        
        $previous = $exception->getPrevious();
        if ($previous instanceof \Throwable) {
            $this->previous = new self($previous);
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
    
    public function getAsString()
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
    
    /**
     * Gets the HTML content associated with the given exception.
     *
     * @return string The content as a string
     */
    public function renderHtmlBody() : string
    {
        switch ($this->getStatusCode()) {
            case 404:
                $title = 'Sorry, the page you are looking for could not be found.';
                break;
            default:
                $title = $this->escapeHtml($this->getMessage());
        }
        
        $content = '';
        
        $count = \count($this->getAllPrevious());
        $total = $count + 1;
        foreach ($this->toArray() as $position => $e) {
            $ind = $count - $position + 1;
            $class = $this->formatClass($e['class']);
            $message = nl2br($this->escapeHtml($e['message']));
            $content .= sprintf(<<<'EOF'
                    <div class="trace trace-as-html">
                        <table class="trace-details">
                            <thead class="trace-head"><tr><th>
                                <h3 class="trace-class">
                                    <span class="text-muted">(%d/%d)</span>
                                    <span class="exception_title">%s</span>
                                </h3>
                                <p class="break-long-words trace-message">%s</p>
                            </th></tr></thead>
                            <tbody>
EOF
                    , $ind, $total, $class, $message);
            foreach ($e['trace'] as $trace) {
                $content .= '<tr><td>';
                if ($trace['function']) {
                    $content .= sprintf('at <span class="trace-class">%s</span><span class="trace-type">%s</span><span class="trace-method">%s</span>', $this->formatClass($trace['class']), $trace['type'], $trace['function']);
                    
                    if (isset($trace['args'])) {
                        $content .= sprintf('(<span class="trace-arguments">%s</span>)', $this->formatArgs($trace['args']));
                    }
                }
                if (isset($trace['file']) && isset($trace['line'])) {
                    $content .= $this->formatPath($trace['file'], $trace['line']);
                }
                $content .= "</td></tr>\n";
            }
            
            $content .= "</tbody>\n</table>\n</div>\n";
        }
        
        return <<<EOF

        <div class="exception">
            <div class="exception-summary">
                <div class="container">
                    <div class="exception-message-wrapper">
                        <h1 class="break-long-words exception-message">$title</h1>
                    </div>
                </div>
            </div>
            
            <div class="container">
                $content
            </div>
        </div>
EOF;
    }
    
    public function renderHtml(bool $withHeader = true) : string
    {
        $css = $this->renderCss();
        if ($withHeader === false) {
            $css .= "
        .exception .exception-summary { display: none !important }";
        }
        return <<<HTML

    <style>
        {$css}
    </style>

    {$this->renderHtmlBody()}

HTML;
    }
    
    protected function renderCss() : string
    {        
        return <<<'EOF'
        
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
            
            .exception .exception-summary { background: #B0413E; border-bottom: 2px solid rgba(0, 0, 0, 0.1); border-top: 1px solid rgba(0, 0, 0, .3); flex: 0 0 auto; margin-bottom: 30px; }
            
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
EOF;
    }
    
    /**
     * HTML-encodes a string.
     */
    private function escapeHtml(string $str): string
    {
        return htmlspecialchars($str, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
    }
    
    private function formatClass(string $class): string
    {
        $parts = explode('\\', $class);
        
        return sprintf('<abbr title="%s">%s</abbr>', $class, array_pop($parts));
    }
    
    /**
     * Formats an array as a string.
     */
    private function formatArgs(array $args): string
    {
        if ($this->maxArgChars === 0) {
            return '';
        }
        
        $result = [];
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf('<em>object</em>(%s)', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('<em>array</em>(%s)', \is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(var_export($item[1], true)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', $this->escapeHtml(StringDataType::truncate(var_export($item[1], true), $this->maxArgChars, false, false, true, true)));
            }
            
            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", $this->escapeHtml($key), $formattedValue);
        }
        
        return implode(', ', $result);
    }
    
    private function formatPath(string $path, int $line): string
    {
        $file = null;
        $file = $this->escapeHtml(preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path);
        $fmt = ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        
        if (!$fmt) {
            return sprintf('<span class="block trace-file-path">in <span title="%s%3$s"><strong>%s</strong>%s</span></span>', $this->escapeHtml($path), $file, 0 < $line ? ' line '.$line : '');
        }
        
        if (\is_string($fmt)) {
            $i = strpos($f = $fmt, '&', max(strrpos($f, '%f'), strrpos($f, '%l'))) ?: \strlen($f);
            $fmt = [substr($f, 0, $i)] + preg_split('/&([^>]++)>/', substr($f, $i), -1, \PREG_SPLIT_DELIM_CAPTURE);
            
            for ($i = 1; isset($fmt[$i]); ++$i) {
                if (0 === strpos($path, $k = $fmt[$i++])) {
                    $path = substr_replace($path, $fmt[$i], 0, \strlen($k));
                    break;
                }
            }
            
            $link = strtr($fmt[0], ['%f' => $path, '%l' => $line]);
        } else {
            try {
                $link = $fmt->format($path, $line);
            } catch (\Exception $e) {
                return sprintf('<span class="block trace-file-path">in <span title="%s%3$s"><strong>%s</strong>%s</span></span>', $this->escapeHtml($path), $file, 0 < $line ? ' line '.$line : '');
            }
        }
        
        return sprintf('<span class="block trace-file-path">in <a href="%s" title="Go to source"><strong>%s</string>%s</a></span>', $this->escapeHtml($link), $file, 0 < $line ? ' line '.$line : '');
    }
}
