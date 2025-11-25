<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\StringDataType;

/**
 * Renders an exception as markdown - H2 chapter with a stacktrace table for every exception
 * 
 * @author Andrej Kabachnik
 *
 */
class ExceptionMarkdownRenderer extends AbstractExceptionRenderer
{
    public function __construct(\Throwable $exception, int $maxArgChars = 500, int $maxArgArrayItems = 100)
    {
        parent::__construct($exception, $maxArgChars, $maxArgArrayItems);

        $previous = $exception->getPrevious();
        if ($previous instanceof \Throwable) {
            $this->previous = new self($previous);
        }
    }
    
    /**
     * @param bool $withHeader
     * @return string
     */
    public function render() : string
    {
        return $this->renderMarkdown();
    }
    
    /**
     * Gets the HTML content associated with the given exception.
     *
     * @return string The content as a string
     */
    public function renderMarkdown() : string
    {
        $content = '';

        $count = \count($this->getAllPrevious());
        $total = $count + 1;
        foreach ($this->toArray() as $position => $e) {
            $ind = $count - $position + 1;
            $class = $this->formatClass($e['class']);
            $message = nl2br($this->escapeMarkdown($e['message']));
            // Need two line breaks before the table to make it render properly!
            $content .= sprintf(<<<'EOF'
## `%d/%d` %s

%s 


EOF
                , 
                $ind, 
                $total,
                $class,
                $message
            );
            foreach ($e['trace'] as $i => $trace) {
                $content .= '| ';
                if ($trace['function']) {
                    $content .= sprintf(
                        'at `%s%s%s', 
                        $this->formatClass($trace['class']), 
                        $trace['type'], 
                        $trace['function']
                    );

                    if (isset($trace['args'])) {
                        $content .= sprintf('(%s)', $this->formatArgs($trace['args']));
                    }
                    $content .= '`<br>';
                }
                if (isset($trace['file']) && isset($trace['line'])) {
                    $content .= $this->formatPath($trace['file'], $trace['line']);
                }
                $content .= " | \n";
                if ($i === 0) {
                    $content .= "| --- |\n";
                }
            }

            $content .= "\n";
        }

        return $content;
    }

    private function formatClass(string $class): string
    {
        $parts = explode('\\', $class);

        return sprintf('%s', array_pop($parts));
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
                $formattedValue = sprintf('object(%s)', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('array(%s)', \is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = 'null';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = strtolower(var_export($item[1], true));
            } elseif ('resource' === $item[0]) {
                $formattedValue = 'resource';
            } else {
                $formattedValue = str_replace("\n", '', $this->escapeBackticks(StringDataType::truncate(var_export($item[1], true), $this->maxArgChars, false, false, true, true)));
            }

            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", $this->escapeBackticks($key), $formattedValue);
        }

        return implode(', ', $result);
    }

    private function formatPath(string $path, int $line): string
    {
        $file = null;
        $file = (preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path);

        return sprintf('in **%s** %s', $file, 0 < $line ? ' line '.$line : '');
    }
    
    private function escapeMarkdown(string $content): string
    {
        return MarkdownDataType::escapeString($content);
    }

    private function escapeBackticks(string $content): string
    {
        return str_replace('`', '``', $content);
    }
}