<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\Markdown\GithubAlertExtension;
use exface\Core\Interfaces\DataTypes\HtmlCompatibleDataTypeInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * 
 * @author andrej.kabachnik
 *
 */
class MarkdownDataType 
    extends TextDataType
    implements HtmlCompatibleDataTypeInterface
{
    /**
     * Configured converters keyed by newline mode (1 = newlines enabled, 0 = disabled)
     * 
     * @var MarkdownConverter[]
     */
    private static array $converters = [];
    
    private $enableNewlines = true;

    /**
     * Set to FALSE to follow strict markdown newline rules instead of converting every single newline to a `<br>`.
     * 
     * By default (`true`) every single newline (a simple line break created by hitting "enter") is rendered
     * as a `<br>` tag. This matches the behavior of the markdown editor. If set to `false`, standard markdown
     * rules apply: only a blank line starts a new paragraph and only lines ending with two trailing spaces
     * produce a `<br>`.
     * 
     * @uxon-property enable_newlines
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return MarkdownDataType
     */
    public function setEnableNewlines(bool $value) : MarkdownDataType
    {
        $this->enableNewlines = BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getEnableNewlines() : bool
    {
        return $this->enableNewlines;
    }

    public static function escapeString(string $text) : string
    {
        return str_replace([
            '\\', 
            '-', 
            '#',
            '*', 
            '+', 
            '`', 
            '.', 
            '[', 
            ']', 
            '(', 
            ')', 
            '!', 
            '&', 
            '<', 
            '>', 
            '_', 
            '{', 
            '}', 
            '|'
        ], [
            '\\\\', 
            '\-', 
            '\#', 
            '\*', 
            '\+', 
            '\`', 
            '\.', 
            '\[', 
            '\]', 
            '\(', 
            '\)', 
            '\!', 
            '\&', 
            '\<', 
            '\>', 
            '\_', 
            '\{', 
            '\}',
            '\|'
        ], $text);
    }
    
    /**
     * 
     * @param string[] $strings
     * @param string $indent
     * @param string $emptyText
     * @return string
     */
    public static function buildMarkdownListFromArray(array $strings, string $emptyText = '', string $indent = "", bool $makeCodeBlocks = false) : string
    {
        if (empty($strings)) {
            $md = $emptyText;
        } else {
            $md = '';
            foreach ($strings as $str) {
                $str = static::escapeString($str);
                if ($makeCodeBlocks === true) {
                    $str = "`{$str}`";
                }
                $md .= PHP_EOL . "{$indent}- {$str}";
            }
        }
        return $md;
    }

    /**
     * Summary of buildMarkdownTableFromArray
     * 
     * @param string[][] $rows
     * @param string[]|null $headings
     * 
     * @return string
     */
    public static function buildMarkdownTableFromArray(array $rows, array $headings = null) : string
    {
        $headings = $headings ?? array_keys($rows[0] ?? []);
        $md = '|';
        foreach ($headings as $heding) {
            $md .= ' ' . static::escapeString($heding ?? '') . ' |';
        }
        $md .= PHP_EOL . '|';
        foreach ($headings as $heding) {
            $md .= ' ' . str_pad('', strlen($heding), '-') . ' |';
        }
        foreach ($rows as $row) {
            $md .= PHP_EOL . '|';
            foreach ($row as $cell) {
                $md .= ' ' . static::escapeString($cell ?? '') . ' |';
            }
        }

        return $md . "\n";
    }

    /**
     * Summary of buildMarkdownTableFromArray
     * 
     * @param array $keyValues
     * @param string $keyHeading
     * @param string $valueHeading
     * @return string
     */
    public static function buildMarkdownTableFromPropertySet(array $keyValues, string $keyHeading, string $valueHeading) : string
    {
        $md = '| ' . static::escapeString($keyHeading) . ' | ' . static::escapeString($valueHeading) . ' |';
        $md .= PHP_EOL . '| ---- | ---- |';
        
        foreach ($keyValues as $key => $value) {
            $md .= PHP_EOL . '| ' . self::escapeString($key ?? '') . ' | ' . self::escapeString($value ?? '') . ' |';
        }

        return $md;
    }

    /**
     * Builds a Markdown header string with the given heading level.
     *
     * Example:
     *   buildMarkdownHeader("Title", 3)
     *   returns: "### Title"
     *
     * @param string $content       The text of the header
     * @param int    $headingLevel  The header level (1 to 6)
     *
     * @return string A Markdown formatted header line
     */
    public static function buildMarkdownHeader(string $content, int $headingLevel): string
    {
        $prefix = str_repeat('#', $headingLevel);
        return $prefix . ' ' . $content;
    }


    /**
     * Converts Markdown to HTML - the single entry point for all Markdown rendering in the workbench.
     * 
     * Widgets, the DocsFacade, UXON schema docs, debug output and toHtml() all use this method, so
     * the parser and its configuration only need to be maintained here. YAML front matter is turned
     * into a quoted code block first to make sure it is displayed instead of being parsed as Markdown.
     * 
     * Besides CommonMark, GitHub extensions are supported: tables, strikethrough, task lists, autolinks
     * and alert boxes (`> [!NOTE]`, `> [!WARNING]`, etc. - see GithubAlertExtension).
     * 
     * If `$enableNewlines` is `true`, every single newline produces a `<br />` (like the Markdown editor
     * does). Otherwise strict CommonMark rules apply and only two trailing spaces or a backslash produce
     * a hard line break.
     * 
     * NOTE: raw HTML in the Markdown is passed through without sanitizing - do not render untrusted
     * Markdown with this method without additional sanitizing.
     *
     * @param string $markdown
     * @param bool $enableNewlines
     * @return string
     */
    public static function convertMarkdownToHtml(string $markdown, bool $enableNewlines = true) : string
    {
        return self::getConverter($enableNewlines)
            ->convert(self::convertFrontMatterToMarkdown($markdown))
            ->getContent();
    }
    
    /**
     * Returns a configured Markdown converter for the given newline mode, building it only once.
     * 
     * Building a CommonMark environment (registering extensions, parsers and renderers) is far more
     * expensive than converting a typical short value. Since toHtml() may be called for every row of
     * a data sheet, the converter is cached in a static property - one per newline mode, because the
     * soft break rendering is part of the environment configuration.
     * 
     * @param bool $enableNewlines
     * @return MarkdownConverter
     */
    protected static function getConverter(bool $enableNewlines) : MarkdownConverter
    {
        $key = $enableNewlines ? 1 : 0;
        if (null === $converter = (self::$converters[$key] ?? null)) {
            $environment = new Environment([
                // Docs and other model content contain raw HTML, that must keep working
                'html_input' => 'allow',
                // Intentionally allow links like `javascript:` or `data:` in Markdown link syntax to keep the
                // behavior of the previous Markdown parser. This does not open up anything new: with
                // `html_input = allow` raw HTML (including scripts and event handlers) is permitted anyway.
                'allow_unsafe_links' => true,
                'renderer' => [
                    'soft_break' => $enableNewlines ? "<br />\n" : "\n"
                ]
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            $environment->addExtension(new StrikethroughExtension());
            $environment->addExtension(new TaskListExtension());
            // Turns bare URLs (http://, https://, ftp://, www.) and bare email addresses into links
            $environment->addExtension(new AutolinkExtension());
            // Renders GitHub-style alerts like `> [!WARNING]` as alert boxes instead of plain blockquotes
            $environment->addExtension(new GithubAlertExtension());
            $converter = new MarkdownConverter($environment);
            self::$converters[$key] = $converter;
        }
        return $converter;
    }

    /**
     * Adjusts all Markdown header levels so that the highest level header
     * (the one with the fewest number of # characters) is shifted to a
     * specified target level.
     *
     * Example:
     * If the highest header in the input is "#" and $highestLevel = 2,
     * all headers are shifted by +1, so:
     *   # Title     -> ## Title
     *   ## Section  -> ### Section
     *
     * If the highest header already equals $highestLevel, the input
     * markdown is returned unchanged.
     *
     * Headers are always clamped between level 1 and 6.
     *
     * @param string $markdown      The full markdown content
     * @param int    $highestLevel  The desired level for the highest header (default 2)
     *
     * @return string The markdown content with adjusted header levels
     */
    public static function convertHeaderLevels(string $markdown, int $highestLevel = 2): string
    {
        if (!preg_match_all('/^(#{1,6})\s+.+$/m', $markdown, $matches)) {
            return $markdown;
        }

        $levels = array_map('strlen', $matches[1]);
        $minLevel = min($levels);

        if ($minLevel === $highestLevel) {
            return $markdown;
        }

        $offset = $highestLevel - $minLevel;

        $converted = preg_replace_callback(
            '/^(#{1,6})\s+(.+)$/m',
            function (array $m) use ($offset) {
                $currentLevel = strlen($m[1]);
                $newLevel = $currentLevel + $offset;

                if ($newLevel < 1) {
                    $newLevel = 1;
                } elseif ($newLevel > 6) {
                    $newLevel = 6;
                }

                return str_repeat('#', $newLevel) . ' ' . $m[2];
            },
            $markdown
        );

        return $converted ?? $markdown;
    }



    /**
     * 
     * @see exface\Core\Interfaces\DataTypes\HtmlCompatibleDataTypeInterface:toHtml()
     */
    function toHtml($value = null): string
    {
        $value = $value ?? $this->getValue();
        if($value) {
            return self::convertMarkdownToHtml($value, $this->getEnableNewlines());
        } else {
            return '';
        }
    }

    public static function findHeadOfFile(string $filePath) : string 
    {
        $content = file_get_contents($filePath);
        if (preg_match('/^#{1,6}\s+(.+)/', $content, $matches)) {
            return trim($matches[1]);
        }

        return pathinfo($filePath, PATHINFO_FILENAME);
    }

    /**
     * @param string $text
     * @param int $level
     * @return string
     */
    public static function makeHeading(string $text, int $level = 1) : string
    {
        return str_repeat('#', $level) . ' ' . $text;
    }

    /**
     * Wraps given code in a Markdown code block
     *
     * @param string $code
     * @param string|null $language
     * @return string
     */
    public static function escapeCodeBlock(string $code, ?string $language = null) : string
    {
        return <<<MD

```{$language}
{$code}
```
MD;
    }

    /**
     * Wraps given text in a Markdown inline code span.
     *
     * WHY THIS EXISTS: escapeString() neutralizes Markdown SYNTAX, but it does not stop the autolink
     * extension of the CommonMark parser, which turns bare URLs (starting with http://, https://, ftp://
     * or www.) and bare email addresses into links - and it cannot, because a URL scheme, `www.` or an
     * email address contain no characters, that would prevent autolinking when escaped. Escaping would
     * only add backslashes INSIDE the resulting link, leaving a link that is both still rendered and now
     * genuinely broken. An inline code span is consumed by the parser before anything inside it is
     * looked at, so the text is rendered literally: no autolink, no formatting side effects, no escape
     * characters - the value stays exactly as written and remains copy-pasteable. Use this for file
     * paths, log file names, addresses and any other literal value that must be READ rather than followed.
     *
     * WHY THE VARIABLE-LENGTH FENCE: a value may itself contain backticks. Markdown closes a code
     * span at the first backtick run of the SAME length, so a hard-coded single backtick would end
     * the span in the middle of the value. The fence is therefore one backtick longer than the
     * longest run inside the value, and padded with spaces when the value starts or ends with a
     * backtick, as the CommonMark code-span rules require.
     *
     * @param string $text
     * @return string
     */
    public static function escapeCodeInline(string $text) : string
    {
        if ($text === '') {
            return '';
        }
        $longest = 0;
        if (preg_match_all('/`+/', $text, $matches) > 0) {
            foreach ($matches[0] as $run) {
                $longest = max($longest, strlen($run));
            }
        }
        $fence = str_repeat('`', $longest + 1);
        $pad = (str_starts_with($text, '`') || str_ends_with($text, '`')) ? ' ' : '';

        return $fence . $pad . $text . $pad . $fence;
    }

    /**
     * Wraps given text in a Markdown blockquote
     *
     * @param string $text
     * @return string
     */
    public static function escapeBlockquote(string $text) : string
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $quoted = array_map(function (string $line) : string {
            return '> ' . $line;
        }, $lines);

        return implode(PHP_EOL, $quoted);
    }
    
    public static function makeHorizontalLine() : string
    {
        return "\n\n-------------------------------------\n\n";
    }

    /**
     * Removes YAML-style front matter from th given markdown
     * 
     * @param string $markdown
     * @return string
     */
    public static function stripFrontMatter(string $markdown): string
    {
        // Remove UTF-8 BOM if present
        $markdown = preg_replace('/^\xEF\xBB\xBF/', '', $markdown);

        // Strip YAML front matter at the very beginning
        $markdown = preg_replace(
            '/\A---\R.*?\R---\R?/s',
            '',
            $markdown
        );

        return $markdown;
    }

    /**
     * Parses the leading YAML-style front matter block of a markdown string into an associative array.
     *
     * Only simple `key: value` lines are supported (surrounding single or double quotes are stripped from
     * values). Returns `null` if the string does not start with a `---` front matter block.
     *
     * @param string $markdown
     * @return array<string,string>|null
     */
    public static function parseFrontMatter(string $markdown) : ?array
    {
        return static::parseFrontMatterLines(explode("\n", static::findFrontMatter($markdown)));
    }

    /**
     * @param string $markdown
     * @param bool $stripDelimiters
     * @return string[]|null
     */
    public static function findFrontMatter(string $markdown, bool $stripDelimiters = true) : ?string
    {
        // Remove UTF-8 BOM if present
        $markdown = preg_replace('/^\xEF\xBB\xBF/', '', $markdown);
        if (! self::startsWith($markdown, '---') || ! preg_match('/\A---\R(.*?)\R---/s', $markdown, $matches)) {
            return null;
        }
        return $stripDelimiters ? $matches[1] : $matches[0];
    }

    /**
     * Reads only the leading YAML-style front matter block of a markdown file into an associative array.
     *
     * Reads the file line by line and stops at the closing `---`, so the body of the file is never loaded.
     * Returns `null` if the file cannot be opened or does not start with a front matter block.
     *
     * @param string $filePath
     * @return array<string,string>|null
     */
    public static function readFrontMatterFromFile(string $filePath) : ?array
    {
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return null;
        }

        $firstLine = fgets($handle);
        if ($firstLine !== false) {
            // Strip a possible UTF-8 BOM from the very first line
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        }
        if ($firstLine === false || rtrim($firstLine, "\r\n") !== '---') {
            fclose($handle);
            return null;
        }

        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '---') {
                break;
            }
            $lines[] = $line;
        }
        fclose($handle);

        return static::parseFrontMatterLines($lines);
    }

    /**
     * Parses an array of `key: value` front matter lines into an associative array.
     *
     * @param string[] $lines
     * @return array<string,string>
     */
    protected static function parseFrontMatterLines(array $lines) : array
    {
        $data = [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if (! preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*)$/', $line, $matches)) {
                continue;
            }
            $value = trim($matches[2]);
            if (strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"')
                    || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $data[$matches[1]] = $value;
        }
        return $data;
    }

    /**
     * Converts YAML FrontMatter leading the given Markdown to a Blockquote to make sure it renders correctly
     * 
     * @param string $markdownWithFrontMatter
     * @return string
     */
    public static function convertFrontMatterToMarkdown(string $markdownWithFrontMatter) : string
    {
        $frontMatter = static::findFrontMatter($markdownWithFrontMatter, false);
        if ($frontMatter !== null) {
            $markdown = static::escapeBlockquote(static::escapeCodeBlock($frontMatter, 'yaml-frontmatter')) . "\n" . static::stripFrontMatter($markdownWithFrontMatter);
        } else {
            $markdown = $markdownWithFrontMatter;
        }
        return $markdown;
    }
}