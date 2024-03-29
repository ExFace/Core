<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Widgets\Traits\iCanUseProxyFacadeTrait;
use exface\Core\Interfaces\Widgets\iCanUseProxyFacade;
use exface\Core\Interfaces\Widgets\iShowImage;
use exface\Core\DataTypes\UrlDataType;

/**
 * Shows an image from an URI in its `value` or data of its `attribute_alias`.
 * 
 * The concrete implementation depends on the facade used, but in most cases, the image
 * URI will be embedded in the generated UI leaving the task of actually fetching the image
 * to the browser (e.g.`<img src="[uri]" />` in a simple HTML facade). Alternatively, the
 * `use_proxy` property can be set to TRUE to make the plattform server fetch the images from
 * their source URIs instead of the client.
 *
 * @author Andrej Kabachnik
 *        
 */
class Image extends Display implements iShowImage, iCanBeAligned, iCanUseProxyFacade
{
    use iCanBeAlignedTrait;
    use iCanUseProxyFacadeTrait;
    
    private $baseUrl = null;
    
    public function getUri() : ?string
    {
        if ($this->hasValue() && ! $this->getValueExpression()->isReference()) {
            return $this->getValue();
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getValue()
     */
    public function getValue()
    {
        $uri = parent::getValue();
        
        // Add base and proxy to URI if it is not empty. Be carefull not to modify
        // anything on empty values as they will become non-empty!!!
        if ($uri) {
            if ($base = $this->getBaseUrl()) {
                $uri = rtrim($base, "/") . '/' . ltrim($uri, "/");
            }
            
            if ($uri !== null && $this->getUseProxy()) {
                return $this->buildProxyUrl($uri);
            }
        }
        
        return $uri;
    }

    /**
     * A fixed image URI to be displayed.
     * 
     * Alternatively you can also use the `value` property - the result is the same.
     * 
     * @uxon-property uri
     * @uxon-type uri
     * 
     * @see \exface\Core\Interfaces\Widgets\iShowImage::setUri()
     */
    public function setUri(string $value) : iShowImage
    {
        return $this->setValue($value);
    }
    
    /**
     *
     * @return string
     */
    public function getBaseUrl() : ?string
    {
        if ($this->baseUrl === null && $this->getValueDataType() instanceof UrlDataType) {
            $this->baseUrl = $this->getValueDataType()->getBaseUrl();
        }
        return $this->baseUrl;
    }
    
    /**
     * Adds a base to every URL.
     *
     * Use this if your data only includes the last part of the URL. You can prefix
     * it then with an absolute or relative base. This will not change the value,
     * but will tell widgets and other components to use this base automatically.
     *
     * @uxon-property base_url
     * @uxon-type uri
     *
     * @param string $value
     * @return UrlDataType
     */
    public function setBaseUrl(string $value) : UrlDataType
    {
        $this->baseUrl = $value;
        return $this;
    }
}