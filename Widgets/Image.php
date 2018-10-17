<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Widgets\Traits\iCanUseProxyTemplateTrait;
use exface\Core\Interfaces\Widgets\iCanUseProxyTemplate;
use exface\Core\Interfaces\Widgets\iShowImage;

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
    
    
    public function getUri() : ?string
    {
        return $this->getValue();
    }
    
    public function getValue()
    {
        $uri = parent::getValue();
        if ($uri !== null && $this->getUseProxy()) {
            return $this->buildProxyUrl($uri);
        }
        return $uri;
    }

    
    public function setUri(string $value) : iShowImage
    {
        return $this->setValue($value);
    }
}
?>