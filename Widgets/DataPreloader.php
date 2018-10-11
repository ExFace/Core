<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class DataPreloader implements iCanBeConvertedToUxon
{    
    use ImportUxonObjectTrait;
    
    private $widget;
    
    private $prelaodData = null;
    
    private $preloadAll = false;
    
    public function __construct(iShowData $widget)
    {
        $this->widget = $widget;
    }
    
    public function getWidget() : iShowData
    {
        return $this->widget;
    }
    
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    public function setPreloadAll() : DataPreloader
    {
        $this->preloadAll = true;
        return $this;
    }
    
    public function setPreloadNone() : DataPreloader
    {
        $this->preloadAll = false;
        return $this;
    }
    
    public function isEnabled() : bool
    {
        return $this->preloadAll === true || $this->prelaodData !== null;
    }
    public function exportUxonObject()
    {
        return new UxonObject([
            
        ]);
    }
    
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $ds = $this->getWidget()->prepareDataSheetToRead($dataSheet);
        $ds->setRowOffset(0);
        $ds->setRowsOnPage(null);
        return $ds;
    }

}