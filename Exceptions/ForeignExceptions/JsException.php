<?php
namespace exface\Core\Exceptions\ForeignExceptions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Widgets\DebugMessage;

class JsException extends RuntimeException
{    
    private array $js;
    private ?array $traceOverride = null; // cached parsed frames

    public function __construct(array $js, int $code = 0, ?\Throwable $previous = null)
    {
        $this->js = $js;

        $message = $js['message'] ?? 'JavaScript error';
        $file    = $js['file']    ?? ($js['source'] ?? 'unknown');
        $line    = $js['line']    ?? 0;

        parent::__construct("[JS] {$message} @ {$file}:{$line}", $code, $previous);
    }

    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);

        foreach ($debugWidget->getTabs() as $tab) {
            if ($tab->getCaption() === 'JavaScript') {
                return $debugWidget;
            }
        }
        $tab = $debugWidget->createTab();
        $tab->setCaption('JavaScript');
        $tab->setWidgets(new UxonObject([[
            'widget_type' => 'Markdown',
            'disabled' => true,
            'width' => '100%',
            'height' => '100%',
            'hide_caption' => true,
            'value' => $this->toMarkdown(),
        ]]));
        $debugWidget->addTab($tab);
        return $debugWidget;
    }
    
    protected function toMarkdown() : string
    {
        return <<<MD
# Position in app

- Breadcrumbs: TODO ideally as markdown links
- Online state: TODO
- Log level?

# Stacktrace

{$this->getJsTraceAsString()}

MD;
    }

    /** Return a PHP-like trace array so your markdown trace formatter keeps working */
    public function getJsTrace(): array
    {
        if ($this->traceOverride !== null) {
            return $this->traceOverride;
        }

        // If frontend sends structured frames, use them directly
        if (!empty($this->js['frames']) && is_array($this->js['frames'])) {
            return $this->traceOverride = array_map([$this, 'frameToPhp'], $this->js['frames']);
        }

        // Else parse typical V8/Chrome stack string
        $stack = (string)($this->js['stack'] ?? '');
        return $this->traceOverride = $this->parseV8StackToTrace($stack);
    }

    /** Some formatters prefer string trace */
    public function getJsTraceAsString(): string
    {
        if (!empty($this->js['stack'])) {
            return (string)$this->js['stack'];
        }
        return parent::getTraceAsString();
    }

    public function jsPayload(): array { return $this->js; }

    private function frameToPhp(array $f): array
    {
        return [
            'file'     => $f['file'] ?? 'unknown',
            'line'     => (int)($f['line'] ?? 0),
            'function' => $f['function'] ?? null,
            'class'    => $f['class'] ?? null,
            'type'     => $f['type'] ?? null,
            'args'     => $f['args'] ?? [],
        ];
    }

    private function parseV8StackToTrace(string $stack): array
    {
        // V8 lines are commonly:
        // "    at funcName (http://site/app.js:10:5)"
        // or "    at http://site/app.js:10:5"
        $trace = [];
        foreach (preg_split('/\R/', $stack) as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, 'at ')) continue;

            $line = substr($line, 3); // strip "at "

            $function = null;
            $file = 'unknown';
            $ln = 0;
            $col = 0;

            if (preg_match('/^(.*?) \((.*)\)$/', $line, $m)) {
                $function = trim($m[1]);
                $loc = $m[2];
            } else {
                $loc = $line;
            }

            // location: "...:line:col" (but URLs may contain ":"), so match from end
            if (preg_match('/^(.*):(\d+):(\d+)$/', $loc, $m)) {
                $file = $m[1];
                $ln   = (int)$m[2];
                $col  = (int)$m[3];
            } else {
                $file = $loc;
            }

            $trace[] = [
                'file'     => $file,
                'line'     => $ln,
                'function' => $function ?: null,
                'class'    => null,
                'type'     => null,
                'args'     => [],       // JS args typically unavailable; keep empty
                'col'      => $col,     // non-standard extra (your formatter can ignore or use)
            ];
        }

        return $trace;
    }
}