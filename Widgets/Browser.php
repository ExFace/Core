<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\WidgetFactory;
/**
 * Shows a URL or an action in an embedded web browser (e.g. an iFrame in HTML-facades).
 *
 * @author Andrej Kabachnik
 *        
 */
class Browser extends AbstractWidget implements iFillEntireContainer
{
    private $url = '';
    
    private $urlBase = '';
    
    private $orphanContainer = null;
    
    /**
     * @return string $url
     */
    public function getUrl() : string
    {
        return $this->url;
    }
    /**
     * The start URL
     * 
     * The placeholder `[#api#]` will be replaced by the API-URL of the current installation.
     * 
     * @uxon-propery url
     * @uxon-type url
     * 
     * @param string $url
     * @return Browser
     */
    public function setUrl(string $url) : Browser
    {
        $phs = [
            'api' => $this->getWorkbench()->getCMS()->buildUrlToRouter() . '/api'
        ];
        $this->url = StringDataType::replacePlaceholders($url, $phs);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getUrlBase() : string
    {
        return $this->urlBase;
    }
    
    /**
     * The base URL for the browser (used for relative URLs).
     * 
     * The placeholder `[#api#]` will be replaced by the API-URL of the current installation.
     * 
     * @uxon-property url_base
     * @uxon-type url
     * 
     * @param string $url
     * @return Browser
     */
    public function setUrlBase(string $url) : Browser
    {
        $phs = [
            'api' => $this->getWorkbench()->getCMS()->buildUrlToRouter() . '/api'
        ];
        $this->urlBase = StringDataType::replacePlaceholders($url, $phs);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        if ($this->getParent() && $this->getParent() instanceof iContainOtherWidgets) {
            return $this->getParent();
        }
        
        if ($this->orphanContainer === null) {
            $this->orphanContainer = WidgetFactory::create($this->getPage(), 'Container', $this);
        }
        return $this->orphanContainer;
    }
}