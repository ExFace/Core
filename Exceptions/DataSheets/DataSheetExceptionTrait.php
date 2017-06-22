<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * This trait enables an exception to output data sheet specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataSheetExceptionTrait {
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $data_sheet = null;

    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataSheet($data_sheet);
    }

    /**
     *
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    /**
     *
     * @param DataSheetInterface $sheet            
     * @return \exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait
     */
    public function setDataSheet(DataSheetInterface $sheet)
    {
        $this->data_sheet = $sheet;
        return $this;
    }

    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        $page = $debug_widget->getPage();
        // Add a tab with the data sheet UXON
        $uxon_tab = $debug_widget->createTab();
        $uxon_tab->setCaption('DataSheet');
        $uxon_tab->setNumberOfColumns(1);
        $uxon_widget = WidgetFactory::create($page, 'Html');
        $uxon_tab->addWidget($uxon_widget);
        $uxon_widget->setValue('<pre>' . $this->getDataSheet()->exportUxonObject()->toJson(true) . '</pre>');
        $debug_widget->addTab($uxon_tab);
        return $debug_widget;
    }
}
?>