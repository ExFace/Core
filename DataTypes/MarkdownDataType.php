<?php
namespace exface\Core\DataTypes;

use cebe\markdown\GithubMarkdown;

/**
 * 
 * @author andrej.kabachnik
 *
 */
class MarkdownDataType extends TextDataType
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
    public static function buildMarkdownListFromArray(array $strings, string $emptyText = '', string $indent = "") : string
    {
        if (empty($strings)) {
            $md = $emptyText;
        } else {
            $md = '';
            foreach ($strings as $str) {
                $str = static::escapeString($str);
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
}