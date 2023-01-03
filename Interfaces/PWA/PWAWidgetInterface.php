<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWAWidgetInterface extends iCanBeConvertedToUxon
{
    public function getPWA() : PWAInterface;
    
    public function getWidget() : WidgetInterface;
    
    public function getPage() : UiPageInterface;
    
    public function getDescription() : string;
}