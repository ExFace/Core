<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Widgets\Traits\iCanUseProxyTemplateTrait;
use exface\Core\Interfaces\Widgets\iCanUseProxyTemplate;
use exface\Core\Interfaces\Widgets\iShowImage;
use exface\Core\DataTypes\UrlDataType;

/**
 * The image widget shows the image specified by the URL in the value of an attribute.
 * 
 * The concrete implementation depends on the template used, but in most cases, the image
 * URI will be embedded in the generated UI leaving the task of actually fetching the image
 * to the browser (e.g.`<img src="[uri]" />` in a simple HTML template). Alternatively, the
 * `use_proxy` property can be set to TRUE to make the plattform server fetch the images from
 * their source URIs instead of the client.
 *
 * @author Andrej Kabachnik
 *        
 */
class Image extends Display implements iShowImage, iCanBeAligned, iCanUseProxyTemplate
{
    use iCanBeAlignedTrait;
    use iCanUseProxyTemplateTrait;
    
    private $baseUrl = null;
    
    public function getUri() : ?string
    {
        return $this->getValue();
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
     * @uxon-type url
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