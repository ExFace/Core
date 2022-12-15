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
            $sheet->createDebugWidget($innerTabs);
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
}