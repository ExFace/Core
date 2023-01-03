<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWADatasetInterface extends iCanBeConvertedToUxon
{
    public function getPWA() : PWAInterface;
    
    public function getMetaObject() : MetaObjectInterface;
    
    public function getDataSheet() : DataSheetInterface;
}