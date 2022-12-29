<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\ProgressiveWebAppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\PWA\PWARouteInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;

class PWARoute implements PWARouteInterface
{
    use ImportUxonObjectTrait;
    
    private $widget = null;
    
    private $objectAction = null;
    
    private $pwa = null;
    
    private $url = null;
    
    public function __construct(ProgressiveWebAppInterface $pwa, string $url, WidgetInterface $widget, ActionInterface $action = null)
    {
        $this->pwa = $pwa;
        $this->widget = $widget;
        $this->url = $url;
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    public function getPWA(): ProgressiveWebAppInterface
    {
        return $this->pwa;
    }
    
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    public function getPage() : UiPageInterface
    {
        return $this->getWidget()->getPage();
    }
    
    public function getURL() : string
    {
        return $this->getURL();
    }
}