<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\StringDataType;
/**
 * Shows a URL or an action in an embedded web browser (e.g. an iFrame in HTML-facades).
 *
 * @author Andrej Kabachnik
 *        
 */
class Browser extends AbstractWidget
{
    private $url = '';
    
    private $urlBase = '';
    
    /**
     * @return string $url
     */
    public function getUrl() : string
    {
        return $this->url;
    }
    /**
     * @param string $url
     * @return Browser
     */
    public function setUrl($url) : Browser
    {
        $phs = [
            'api' => $this->getWorkbench()->getCMS()->buildUrlToRouter() . '/api'
        ];
        $this->url = StringDataType::replacePlaceholders($url, $phs);
        return $this;
    }
    
    public function getUrlBase() : string
    {
        return $this->urlBase;
    }
    
    public function setUrlBase(string $url) : Browser
    {
        $phs = [
            'api' => $this->getWorkbench()->getCMS()->buildUrlToRouter() . '/api'
        ];
        $this->urlBase = StringDataType::replacePlaceholders($url, $phs);
        return $this;
    }
}
?>