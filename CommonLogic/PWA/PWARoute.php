<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\PWA\PWARouteInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\DataTypes\OfflineStrategyDataType;

class PWARoute implements PWARouteInterface
{
    use ImportUxonObjectTrait;
    
    private $widget = null;
    
    private $pwa = null;
    
    private $url = null;
    
    private $action = null;
    
    public function __construct(PWAInterface $pwa, string $url, WidgetInterface $widget = null, ActionInterface $showingAction = null)
    {
        $this->pwa = $pwa;
        $this->widget = $widget;
        $this->url = $url;
        $this->action = $showingAction;
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getPWA()
     */
    public function getPWA(): PWAInterface
    {
        return $this->pwa;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getWidget()
     */
    public function getWidget() : ?WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getAction()
     */
    public function getAction() : ?ActionInterface
    {
        if ($this->action !== null) {
            return $this->action;
        }
        
        if (null !== $triggerWidget = $this->getTriggerWidget()) {
            return $triggerWidget->getAction();
        }
        
        return $this->action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getPage()
     */
    public function getPage() : UiPageInterface
    {
        return $this->getWidget()->getPage();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getURL()
     */
    public function getURL() : string
    {
        return $this->url;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getTriggerWidget()
     */
    public function getTriggerWidget() : ?iTriggerAction
    {
        $routeWidget = $this->getWidget();
        if ($routeWidget !== null && $routeWidget->hasParent()) {
            $triggerWidget = $routeWidget->getParent();
            if ($triggerWidget instanceof iTriggerAction) {
                return $triggerWidget;
            }
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getTriggerInputWidget()
     */
    public function getTriggerInputWidget() : ?WidgetInterface
    {
        if (null !== $triggerWidget = $this->getTriggerWidget()) {
            if ($triggerWidget instanceof iUseInputWidget) {
                return $triggerWidget->getInputWidget();
            }
        }
        return null;
    }
    
    public function getOfflineStrategy() : string
    {
        if (null === $action = $this->getAction()) {
            return OfflineStrategyDataType::PRESYNC;
        }
        return $this->pwa->getActionOfflineStrategy($action);
    }
}