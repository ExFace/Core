<?php
namespace exface\Core\DataTypes;

use cebe\markdown\GithubMarkdown;
use exface\Core\Interfaces\DataTypes\HtmlCompatibleDataTypeInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
class MarkdownDataType 
    extends TextDataType
    implements HtmlCompatibleDataTypeInterface
{
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
            $md .= ' ' . static::escapeString($heding) . ' |';
        }
        $md .= PHP_EOL . '|';
        foreach ($headings as $heding) {
            $md .= ' ' . str_pad('', strlen($heding), '-') . ' |';
        }
        foreach ($rows as $row) {
            $md .= PHP_EOL . '|';
            foreach ($row as $cell) {
                $md .= ' ' . static::escapeString($cell) . ' |';
            }
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
     *
     * @param string $markdown
     * @return string
     */
    public static function convertMarkdownToHtml(string $markdown) : string
    {
        $parser = new GithubMarkdown();
        return $parser->parse($markdown);
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
            return self::convertMarkdownToHtml($value);
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
    
    public static function makeHorizontalLine() : string
    {
        return "\n\n-------------------------------------\n\n";
    }
}