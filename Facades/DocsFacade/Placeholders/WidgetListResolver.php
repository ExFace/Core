<?php
namespace exface\Core\Facades\DocsFacade\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractMarkdownPlaceholderResolver;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class WidgetListResolver extends AbstractMarkdownPlaceholderResolver implements PlaceholderResolverInterface
{
    const OPTION_FOR = "for";

    const OPTION_FOR_AI = "ai";

    const OPTION_FOR_HUMAN = "human";

    private $workbench = null;

    private $optionDefaults = [
        self::OPTION_FOR => self::OPTION_FOR_HUMAN
    ];

    public function __construct(WorkbenchInterface $workbench, string $prefix = 'WidgetList:') {
        $this->workbench = $workbench;
        $this->setPrefix($prefix);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $vals = [];
        $names = array_map(fn($ph) => $ph['name'], $placeholders);
        $filteredNames = $this->filterPlaceholders($names);
        foreach ($placeholders as $i => $placeholder) {
            if (in_array($placeholder['name'], $filteredNames)) {
                $options = $placeholder['options'];
                parse_str($options, $optionsArray);
                $val = $this->getWidgetList(for: $this->getOption('for',$optionsArray));
                $vals[$i] = $val;
            }
        }
        return $vals;
    }

    protected function getOption(string $optionName, array $callValues)
    {
        $value = $callValues[$optionName] ?? null;
        $default = $this->optionDefaults[$optionName] ?? null;
        if ($optionName == self::OPTION_FOR) {
            // validation
        }
        return $value ?? $default;
    }

    protected function getWidgetList(string $for) : string
    {
        $existing_objects = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.WIDGET');
        $existing_objects->getColumns()->addMultiple(['NAME', 'APP__ALIAS', 'PATHNAME_RELATIVE']);
        $existing_objects->getSorters()->addFromString('NAME', 'ASC');
        $existing_objects->dataRead();
        $widgetList = $existing_objects->getRows();
        
        $result = [];
        foreach($widgetList as $widget)
        {
            $widgetDescriptionDs = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_ENTITY_ANNOTATION');
            $widgetDescriptionDs->getColumns()->addMultiple(['FULL_DESCRIPTION']);
            $widgetDescriptionDs->getFilters()->addConditionFromString('FILE', $widget['PATHNAME_RELATIVE'], ComparatorDataType::EQUALS);
            $widgetDescriptionDs->dataRead();
            $annotation = $widgetDescriptionDs->getRows();
            $description = null;
            if($for === self::OPTION_FOR_HUMAN) {
                $lines = preg_split('/\r?\n\s*\r?\n/', $annotation[0]['FULL_DESCRIPTION']);
                $description = $lines[0];
            }
            else {
                $description = $annotation[0]['FULL_DESCRIPTION'];
            }
            $result[] = [
                'NAME'=> $widget['NAME'],
                'IS PART OF APP' => $widget['APP__ALIAS'],
                'PATH' => $widget['PATHNAME_RELATIVE'],
                'DESCRIPTION' => $description
            ];
        }
        $widgetsHeaders = ['NAME', 'IS PART OF APP', 'PATH','DESCRIPTION'];

        return $this->createTextTable('WIDGETS', $widgetsHeaders, $result);
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

    protected function getWorkbench()
    {
        return $this->workbench;
    }
}