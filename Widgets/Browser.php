<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * Shows a URL or an action in an embedded web browser (e.g. an iFrame in HTML-facades).
 *
 * @author Andrej Kabachnik
 *        
 */
class Browser extends Value implements iFillEntireContainer
{
    private $urlBase = '';
    
    private $orphanContainer = null;
    
    /**
     * @return string $url
     */
    public function getUrl() : string
    {
        return $this->getValue() ?? '';
    }
    /**
     * The start URL
     * 
     * The placeholder `[#api#]` will be replaced by the API-URL of the current installation.
     * 
     * @uxon-property url
     * @uxon-type uri
     * 
     * @param string $url
     * @return Browser
     */
    public function setUrl(string $url) : Browser
    {
        $this->setValue($url);
        return $this;
    }
    
    public function setValue($expressionOrString, bool $parseStringAsExpression = true)
    {
        if (is_string($expressionOrString)) {
            $phs = [
                'api' => $this->getWorkbench()->getUrl() . 'api'
            ];
            $expressionOrString = StringDataType::replacePlaceholders($expressionOrString, $phs);
        }
        parent::setValue($expressionOrString, $parseStringAsExpression);
    }
    
    /**
     * 
     * @return string
     */
    public function getBaseUrl() : string
    {
        return $this->urlBase;
    }
    
    /**
     * The base URL for the browser (used for relative URLs).
     * 
     * The placeholder `[#api#]` will be replaced by the API-URL of the current installation.
     * 
     * @uxon-property base_url
     * @uxon-type uri
     * 
     * @param string $url
     * @return Browser
     */
    public function setBaseUrl(string $url) : Browser
    {
        $phs = [
            'api' => $this->getWorkbench()->getUrl() . 'api'
        ];
        $this->urlBase = StringDataType::replacePlaceholders($url, $phs);
        return $this;
    }
    
    /**
     * @deprecated use setBaseUrl()
     * @param string $url
     * @return Browser
     */
    protected function setUrlBase(string $url) : Browser
    {
        return $this->setBaseUrl($url);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
    }
}