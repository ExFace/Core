<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;


/**
 * Generates a markdown documentation for a widget type
 * 
 * @author Brooklyn Fränzschky
 */
class WidgetPrototypeMarkdownPrinter implements MarkdownPrinterInterface
{
    private WorkbenchInterface $workbench;
    
    private ?string $filePath = '';

    public function __construct(WorkbenchInterface $workbench, string $widgetPrototypeFilePath)
    {
        $this->workbench = $workbench;
        $this->filePath = $widgetPrototypeFilePath;
    }
    
    public function getMarkdown(): string
    {
        $widgetDescriptionDs = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.UXON_ENTITY_ANNOTATION');
        $widgetDescriptionDs->getColumns()->addMultiple(['FULL_DESCRIPTION']);
        $widgetDescriptionDs->getFilters()->addConditionFromString('FILE', $this->filePath, ComparatorDataType::EQUALS);
        $widgetDescriptionDs->dataRead();
        $description = $widgetDescriptionDs->getRows();
        $output = $description[0]['FULL_DESCRIPTION'] . PHP_EOL;

        $widgetPropertiesDs = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.UXON_PROPERTY_ANNOTATION');
        $widgetPropertiesDs->getColumns()->addMultiple([
            'PROPERTY',
            'TITLE',
            'TYPE',
            'DEFAULT',
            'TEMPLATE',
            'REQUIRED',
            'DESCRIPTION'
        ]);
        $widgetPropertiesDs->getFilters()->addConditionFromString('FILE', $this->filePath, ComparatorDataType::EQUALS);
        $widgetPropertiesDs->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::ASC);
        $widgetPropertiesDs->dataRead();
        $widgetProperties = $widgetDescriptionDs->getRows();

        $widgetFunctionsDs = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.WIDGET_FUNCTION_ANNOTATION');
        $widgetFunctionsDs->getColumns()->addMultiple([
            'PROPERTY',
            'TITLE'
        ]);
        $widgetFunctionsDs->getFilters()->addConditionFromString('FILE', $this->filePath, ComparatorDataType::EQUALS);
        $widgetFunctionsDs->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::DESC);
        $widgetFunctionsDs->dataRead();
        $widgetFunctions = $widgetFunctionsDs->getRows();

        $widgetPresetsDs = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.UXON_PROPERTY_ANNOTATION');
        $widgetPresetsDs->getColumns()->addMultiple([
            'NAME',
            'DESCRIPTION',
            'WRAP_FLAG',
            'APP__LABEL'
        ]);
        $widgetPresetsDs->getFilters()->addConditionFromString('FILE', $this->filePath, ComparatorDataType::EQUALS);
        $widgetPresetsDs->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::ASC);
        $widgetPresetsDs->dataRead();
        $widgetPresets = $widgetPresetsDs->getRows();

        $widgetPropertiesHeaders = ['PROPERTY', 'TITLE', 'TYPE', 'DEFAULT', 'TEMPLATE', 'REQUIRED', 'DESCRIPTION'];
        $widgetFunctionsHeaders = ['FUNCTION', 'TITLE'];
        $widgetPresetsHeaders = ['NAME', 'DESCRIPTION', 'USABLE AS WRAPPER', 'IS PART OF APP'];

        $output .= $this->createTextTable('Widget Properties', $widgetPropertiesHeaders, $widgetProperties) . PHP_EOL;
        $output .= $this->createTextTable('Widget Functions', $widgetFunctionsHeaders, $widgetFunctions) . PHP_EOL;
        $output .= $this->createTextTable('Widget Presets', $widgetPresetsHeaders, $widgetPresets);

        return $output;
    }

    public function createTextTable(string $title, array $headers, array $rows, int $wrap = 80): string
    {
        $out  = "### " . $title . "\n\n";
        $out .= '| ' . implode(' | ', $headers) . " | \n";
        $sep  = array_map(fn($h) => str_repeat('-', max(3, mb_strlen($h))), $headers);
        $out .= '| ' . implode(' | ', $sep) . " | \n";

        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $cell) {
                $text    = preg_replace('/\s+/', ' ', trim((string)$cell));
                $wrapped = wordwrap($text, $wrap, "\n", true);
                $cells[] = str_replace("\n", '<br>', $wrapped);
            }
            $out .= '| ' . implode(' | ', $cells) . " | \n";
        }

        return $out;
    }

    protected function getFilePath(): ?string
    {
        return $this->filePath;
    }
}