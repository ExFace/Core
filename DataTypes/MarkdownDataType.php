<?php
namespace exface\Core\DataTypes;

use cebe\markdown\GithubMarkdown;
use exface\Core\Interfaces\ICanBeConvertedToHtml;

/**
 * 
 * @author andrej.kabachnik
 *
 */
class MarkdownDataType 
    extends TextDataType
    implements ICanBeConvertedToHtml
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
     *
     * @param string $markdown
     * @return string
     */
    public static function convertMarkdownToHtml(string $markdown) : string
    {
        $parser = new GithubMarkdown();
        return $parser->parse($markdown);
    }

    function toHtml($value = null): string
    {
        $value = $value ?? $this->getValue();
        if($value) {
            return self::convertMarkdownToHtml($value);
        } else {
            return '';
        }
    }
}