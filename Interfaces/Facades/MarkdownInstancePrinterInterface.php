<?php
namespace exface\Core\Interfaces\Facades;

interface MarkdownInstancePrinterInterface extends MarkdownPrinterInterface
{
    /**
     * @param object $instance
     * @return MarkdownPrinterInterface
     */
    public static function constructForInstance(object $instance) : MarkdownPrinterInterface;
}