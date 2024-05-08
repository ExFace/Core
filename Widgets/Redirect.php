<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Performs a browser redirect to a given URL or a page instead of really showing something
 * 
 * Examples:
 * 
 * Open an external page in a new window:
 * 
 * ```
 *  {
 *      "widget_type": "Redirect",
 *      "object_alias": "exface.Core.DUMMY",
 *      "url": "https://...",
 *      "open_in_new_window": true
 *  }
 *  
 * ```
 * 
 * Redirect to another page (e.g. add additional shortcuts to that page in the menu):
 * 
 * ```
 *  {
 *      "widget_type": "Redirect",
 *      "object_alias": "exface.Core.DUMMY",
 *      "page_alias": "exface.core.administration",
 *      "open_in_new_window": true
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class Redirect extends AbstractWidget
{
    private $url = null;
    
    private $pageAlias = null;
    
    private $openInNewWindow = false;
    
    /**
     * 
     * @return string|NULL
     */
    public function getUrl() : ?string
    {
        return $this->url;
    }
    
    /**
     * The URL to redirect to
     * 
     * @uxon-property url
     * @uxon-type url
     * 
     * @param string $value
     * @return Redirect
     */
    public function setUrl(string $value) : Redirect
    {
        $this->url = $value;
        $this->pageAlias = null;
        return $this;
    }
    
    /**
     * The alias of the page to redirect to
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * 
     * @param string $aliasOrUid
     * @return Redirect
     */
    public function setPageAlias(string $aliasOrUid) : Redirect
    {
        $this->pageAlias = $aliasOrUid;
        $this->url = null;
        return $this;
    }
    
    /**
     * 
     * @param HtmlPageFacadeInterface $facade
     * @return string
     */
    public function getTargetUrl(HtmlPageFacadeInterface $facade) : string
    {
        if ($this->isTargetPage()) {
            return $facade->buildUrlToPage($this->pageAlias);
        }
        if ($this->url === null) {
            throw new WidgetConfigurationError($this, 'Invalid configuration for ' . $this->getWidgetType() . ' widget: neither `url` nor `page_alias` are set!');
        }
        return $this->url;
    }
    
    /**
     * 
     * @return bool
     */
    public function isTargetUrl() : bool
    {
        return $this->url !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function isTargetPage() : bool
    {
        return $this->pageAlias !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool
    {
        return $this->openInNewWindow;
    }
    
    /**
     * Set to TRUE to open the URL in a new window
     * 
     * @uxon-property open_in_new_window
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Redirect
     */
    public function setOpenInNewWindow(bool $value) : Redirect
    {
        $this->openInNewWindow = $value;
        return $this;
    }
}