<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\MarkdownDataType;
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

    
    public function __construct(WorkbenchInterface $workbench, string $logId, int $depth = 0)
    {
        $this->workbench = $workbench;
        
        // Clean up log id
        if (stripos($logId, 'log-') !== false) {
            $logId = str_ireplace('log-', '', $logId);
            $logId = strtoupper($logId);
        }
        
        $this->logId = $logId;
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
        $logId = $this->logId;

        $logFileSheet = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.LOG');
        $logFileCol = $logFileSheet->getColumns()->addFromExpression('PATHNAME_RELATIVE');
        $logFileSheet->getFilters()->addConditionFromString('CONTENTS', $logId, ComparatorDataType::IS);
        $logFileSheet->dataRead();

        $logFile = $logFileCol->getValue(0);

        $logEntrySheet = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.LOG_ENTRY');
        $logEntrySheet->getColumns()->addMultiple([
            'id', 'levelname', 'message', 'filepath', 'context' , 'channel'
        ]);
        $logEntrySheet->getFilters()->addConditionFromString('id', $logId, ComparatorDataType::EQUALS);
        $logEntrySheet->getFilters()->addConditionFromString('logfile', $logFile, ComparatorDataType::EQUALS);
        $logEntrySheet->dataRead();

        $row = $logEntrySheet->getRow(0);
        $detailsPath = $this->workbench->filemanager()->getPathToLogDetailsFolder(). '/' . $row['filepath'] . '.json';

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
     * @param array<string,mixed> $json Decoded JSON node describing a part of the log details.
     * @param int $headingLevel Current heading level that should be used for this node.
     *
     * @return string Generated Markdown segment for the given JSON node.
     */
    protected function buildMarkdown($json, int $headingLevel) : string
    {
        $markdown = "";

        if (isset($json['caption'])) {

            $hide = isset($json['hide_caption']) ? (bool)$json['hide_caption'] : false;

            if (!$hide) {
                $markdown .= MarkdownDataType::convertHeaderLevels($json['caption'], $headingLevel) . "\n";
            }
        }

        if (isset($json['widgets']) && is_array($json['widgets'])) {
            foreach ($json['widgets'] as $widget) {
                // Recursive call for each nested widget, using a deeper heading level
                $markdown .= $this->buildMarkdown($widget, $headingLevel + 1);
            }
        }

        if (isset($json['value'])) {

            // Future toggle to allow filtering of forbidden words
            $showContactSupport = true;

            if (
                $showContactSupport
                && stripos(strtolower($json['value']), 'support') === false
            ) {
                $markdown .= MarkdownDataType::convertHeaderLevels($json['value'], $headingLevel + 1);
            }
        }

        return $markdown . "\n";
    }
}
