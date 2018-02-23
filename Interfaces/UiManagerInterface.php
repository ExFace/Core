<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Templates\TemplateInterface;
use Psr\Http\Message\UriInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;

interface UiManagerInterface extends ExfaceClassInterface
{
    /**
     * 
     * @param UriInterface $uri
     * @return TemplateInterface
     */
    public function getTemplateForUri(UriInterface $uri) : TemplateInterface;
    
    /**
     * Returns the UI page with the given $page_alias.
     * If the $page_alias is ommitted or ='', the default (initially empty) page is returned.
     *
     * @param UiPageSelectorInterface|string $selectorOrString
     * @return UiPageInterface
     */
    public function getPage($selectorOrString);
    
    /**
     * Returns a template instance for a given template alias.
     * If no alias given, returns the current template.
     *
     * @param string $template
     * @return TemplateInterface
     */
    public function getTemplate($selectorOrString);
}

?>