<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWARouteInterface extends iCanBeConvertedToUxon
{
    public function getPWA() : PWAInterface;
    
    /**
     * 
     * @return WidgetInterface|NULL
     */
    public function getWidget() : ?WidgetInterface;
    
    /**
     * 
     * @return ActionInterface|NULL
     */
    public function getAction() : ?ActionInterface;
    
    public function getPage() : UiPageInterface;
    
    public function getURL() : string;
    
    public function getTriggerWidget() : ?iTriggerAction;
    
    public function getTriggerInputWidget() : ?WidgetInterface;
    
    public function getOfflineStrategy() : string;
}