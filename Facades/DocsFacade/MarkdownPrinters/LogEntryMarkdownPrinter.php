<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Builds a Markdown representation of a single log entry.
 *
 * The printer resolves the log file and log entry from the database,
 * loads the corresponding JSON details file and renders it as Markdown.
 * The JSON structure is processed recursively so that nested widgets
 * and captions are converted into a hierarchical Markdown document.
 */
class LogEntryMarkdownPrinter
{
    
    protected WorkbenchInterface $workbench;
    
    /**
     * Normalized identifier of the log entry that should be rendered.
     *
     * The constructor strips a leading "log-" prefix in a case insensitive way
     * and stores the cleaned value in upper case.
     */
    protected string $logId;
    protected ?string $logFilePath = null;

    
    public function __construct(WorkbenchInterface $workbench, string $logId, ?string $logFilePath = null, int $depth = 0)
    {
        $this->workbench = $workbench;
        
        // Clean up log id
        if (stripos($logId, 'log-') !== false) {
            $logId = str_ireplace('log-', '', $logId);
            $logId = strtoupper($logId);
        }
        
        $this->logId = $logId;
        $this->logFilePath = $logFilePath;
    }
    
    /**
     * Generates the complete Markdown output for the configured log entry.
     *
     * The method resolves the log file that contains the entry, loads the
     * corresponding details JSON from the file system and passes it to
     * buildMarkdown for recursive rendering.
     *
     * @return string Markdown representation of the log entry or an empty string
     *                if no details can be found.
     */
    public function getMarkdown() : string
    {
        $logEntrySheet = $this->workbench->getDebugger()->getLogData($this->logId, $this->logFilePath, [
            'id', 'levelname', 'message', 'filepath', 'context' , 'channel'
        ]);
        
        $row = $logEntrySheet->getRow(0);
        $detailsPath = $this->workbench->filemanager()->getPathToLogDetailsFolder(). DIRECTORY_SEPARATOR . $row['filepath'] . '.json';

        $detailsJson = json_decode(file_get_contents($detailsPath), true);
        
        return $this->buildMarkdown($detailsJson, 1);
    }
    
    /**
     * Recursively converts a JSON structure of log details into Markdown.
     *
     * The method walks through the JSON node and renders:
     *  - caption: as a Markdown heading with the given heading level
     *  - widgets: each widget is processed recursively with an increased level
     *  - value: rendered as a heading as well, optionally filtered
     *
     * This recursive approach allows nested widgets and sections in the
     * log details file to be mapped to a nested Markdown document with
     * multiple levels of headings.
     *
     * @param array<string,mixed> $widgetUxon Decoded JSON node describing a part of the log details.
     * @param int $headingLevel Current heading level that should be used for this node.
     *
     * @return string Generated Markdown segment for the given JSON node.
     */
    protected function buildMarkdown($widgetUxon, int $headingLevel) : string
    {
        $markdown = "";

        if (isset($widgetUxon['caption'])) {

            $hide = isset($widgetUxon['hide_caption']) ? (bool)$widgetUxon['hide_caption'] : false;

            if (!$hide) {
                $caption = MarkdownDataType::makeHeading($widgetUxon['caption'], $headingLevel) . "\n";
                $markdown .= $caption;
                $headingLevel++;
            }
        }

        $children = $widgetUxon['widgets'] ?? ($widgetUxon['tabs'] ?? []);
        if (! empty($children)) {
            foreach ($children as $childUxon) {
                // Recursive call for each nested widget, using a deeper heading level
                $markdown .= $this->buildMarkdown($childUxon, $headingLevel);
            }
        }

        if (($value = $widgetUxon['value']) || ($value = $widgetUxon['markdown']) || ($value = $widgetUxon['text'])) {

            // Future toggle to allow filtering of forbidden words
            $showContactSupport = true;

            switch (true) {
                case $widgetUxon['widget_type'] === 'Message':
                    if ($showContactSupport && stripos(strtolower($value), 'support') === false) {
                        $markdown .= $value;
                    }
                    break;
                case $widgetUxon['widget_type'] === 'InputUxon':
                case $widgetUxon['widget_type'] === 'InputJson':
                case $widgetUxon['widget_type'] === 'InputCode':
                    $markdown .= MarkdownDataType::escapeCodeBlock($value);
                    break;
                default:
                    $markdown .= MarkdownDataType::convertHeaderLevels($value, $headingLevel + 1);
            }
        }

        return $markdown . "\n";
    }
}