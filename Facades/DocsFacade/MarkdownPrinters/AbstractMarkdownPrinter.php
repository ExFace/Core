<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\MarkdownDataType;

/**
 * 
 */
abstract class AbstractMarkdownPrinter
{
    protected function escapeMarkdownText(string $value): string
    {
        return MarkdownDataType::escapeString($value);
    }

    /**
     * Escapes a value so that it can be safely used inside a Markdown table cell.
     *
     * Line breaks are converted to HTML line breaks and pipe characters are escaped.
     */
    protected function escapeMarkdownCell(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], '<br>', $value);
        $value = str_replace('|', '\|', $value);
        return $value;
    }
}