<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Debug\DataLogBookInterface;

class DataLogBook extends MarkdownLogBook implements DataLogBookInterface
{
    const MAX_DATA_ROWS = 40;
    
    private $dataSheets = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        // Add a tab with the data sheet UXON
        $tab = $debugWidget->createTab();
        $debugWidget->addTab($tab);
        
        $innerTabs = WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'DebugMessage'
        ]));
        $tab->addWidget($innerTabs);
        
        $innerTabs = parent::createDebugWidget($innerTabs);
        $tab->setCaption($innerTabs->getWidgetFirst()->getCaption());
        $innerTabs->getWidgetFirst()->setCaption('Logbook');
        
        foreach ($this->dataSheets as $name => $sheet) {
            if ($sheet->countRows() > self::MAX_DATA_ROWS) {
                $truncated = $sheet->copy()->removeRows();
                $name .= ' (first ' . self::MAX_DATA_ROWS . ' of ' . $sheet->countRows() . ' rows)';
                $cnt = 1;
                foreach ($sheet->getRows() as $i => $row) {
                    $truncated->addRow($row, false, false, $i);
                    $cnt++;
                    if ($cnt > self::MAX_DATA_ROWS) {
                        break;
                    }
                }
                $innerTabs = $truncated->createDebugWidget($innerTabs);
            } else {
                $name .= ' (' . $sheet->countRows() . ' rows)';
                $innerTabs = $sheet->createDebugWidget($innerTabs);
            }
            $innerTabs->getWidget(($innerTabs->countWidgets()-1))->setCaption($name);
        }
        
        return $debugWidget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\DataLogBookInterface::addDataSheet()
     */
    public function addDataSheet(string $title, DataSheetInterface $dataSheet): LogBookInterface
    {
        $this->dataSheets[$title] = $dataSheet;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\DataLogBookInterface::getDataSheets()
     */
    public function getDataSheets(): array
    {
        return $this->dataSheets;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $dataSheet
     * @return string
     */
    public static function buildTitleForData(DataSheetInterface $dataSheet) : string
    {
        $obj = $dataSheet->getMetaObject()->getAliasWithNamespace();
        $rows = $dataSheet->countRows();
        $cols = $dataSheet->getColumns()->count();
        $filters = $dataSheet->getFilters()->countConditions() + $dataSheet->getFilters()->countNestedGroups();
        if (empty($rows) && empty($cols) && empty($filters)) {
            return "{$obj}\nblank";
        }
        return "{$obj}\n{$rows} row(s), {$cols} col(s), {$filters} filter(s)";
    }
    
    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $dataSheet
     * @return string
     */
    public static function buildMermaidTitleForData(DataSheetInterface $dataSheet) : string
    {
        return '"' . static::buildTitleForData($dataSheet) . '"';
    }
}