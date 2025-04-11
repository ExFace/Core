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
}