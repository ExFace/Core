<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;

class LogEntryMarkdownPrinter
{
    protected WorkbenchInterface $workbench;
    
    protected string $logId;

    public function __construct(WorkbenchInterface $workbench, string $logId, int $depth = 0)
    {
        $this->workbench = $workbench;
        
        
        //Clean up Log Id 
        if (stripos($logId, 'log-') !== false) {
            $logId = str_ireplace('log-', '', $logId);
            $logId = strtoupper($logId);
        }
        
        $this->logId = $logId;
        
    }
    
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
        $logEntrySheet->getFilters()->addConditionFromString('id',$logId, ComparatorDataType::EQUALS);
        $logEntrySheet->getFilters()->addConditionFromString('logfile', $logFile, ComparatorDataType::EQUALS);
        $logEntrySheet->dataRead();
        $row = $logEntrySheet->getRow(0);
        $detailsPath = $this->workbench->filemanager()->getPathToLogDetailsFolder(). '/' . $row['filepath'] . '.json';

        $detailsJson =  json_decode(file_get_contents($detailsPath), true);
        
        return $this->buildMarkdown($detailsJson, 1);
        
    }
    
    protected function buildMarkdown($json, int $headingLevel) : string
    {
        $markdown = "";

        if (isset($json['caption'])) {

            $hide = isset($json['hide_caption']) ? (bool)$json['hide_caption'] : false;

            if (!$hide) {
                $markdown .= $this->buildMarkdownHeader($json['caption'], $headingLevel) . "\n";
            }
        }

        if (isset($json['widgets']) && is_array($json['widgets'])) {
            foreach ($json['widgets'] as $widget) {
                $markdown .= $this->buildMarkdown($widget, $headingLevel + 1);
            }
        }

        if (isset($json['value'])) {

            // future toggle to allow filtering of forbidden words
            $showContactSupport = true;

            if (
                $showContactSupport
                && stripos(strtolower($json['value']), 'support') === false
            ) {
                $markdown .= $json['value'];
            }
        }


        return $markdown;
    }

    protected function buildMarkdownHeader(string $content, int $headingLevel) : string
    {
        $prefix = str_repeat('#', $headingLevel);
        return $prefix . ' ' . $content;
    }


}