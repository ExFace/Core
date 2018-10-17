<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataPreloader;

interface iCanPreloadData extends WidgetInterface
{
    /**
     * 
     * @param UxonObject|string $uxonOrString
     * @return iCanPreloadData
     */
    public function setPreloadData($uxonOrString) : iCanPreloadData;
    
    /**
     * 
     * @return bool
     */
    public function isPreloadDataEnabled() : bool;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet) : DataSheetInterface;
    
    /**
     * 
     * @return DataPreloader
     */
    public function getPreloader() : DataPreloader; 
}