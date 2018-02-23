<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Factories\TemplateFactory;
use Psr\Http\Message\UriInterface;
use exface\Core\Exceptions\Templates\TemplateRoutingError;
use exface\Core\CommonLogic\Selectors\UiPageSelector;

class UiManager implements UiManagerInterface
{

    private $widget_id_forbidden_chars_regex = '[^A-Za-z0-9_\.]';

    private $loaded_templates = array();

    private $exface = null;

    private $base_template = null;

    private $page_current = null;

    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Returns a template instance for a given template alias.
     * If no alias given, returns the current template.
     * 
     * @param string $template
     * @return TemplateInterface
     */
    public function getTemplate($selectorOrString)
    {
        if (! $instance = $this->loaded_templates[$selectorOrString]) {
            $instance = TemplateFactory::createFromString($selectorOrString, $this->exface);
            $this->loaded_templates[$selectorOrString] = $instance;
        }
        return $instance;
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns the UI page with the given $page_alias.
     * If the $page_alias is ommitted or ='', the default (initially empty) page is returned.
     * 
     * @param UiPageSelector|string $selectorOrString
     * @return UiPageInterface
     */
    public function getPage($selectorOrString = null)
    {
        // FIXME use UiPageSelector in the factory and in the CMS interfaces
        $string = $selectorOrString instanceof UiPageSelector ? $selectorOrString->toString() : $selectorOrString;
        return UiPageFactory::createFromCmsPage($this, $string);
    }

    /**
     * 
     * @return UiPageInterface
     */
    public function getPageCurrent()
    {
        if (is_null($this->page_current)) {
            $this->page_current = UiPageFactory::createFromCmsPageCurrent($this);
        }
        return $this->page_current;
    }

    /**
     * 
     * @param UiPageInterface $pageCurrent
     * @return UiManager
     */
    public function setPageCurrent(UiPageInterface $pageCurrent)
    {
        $this->page_current = $pageCurrent;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UiManagerInterface::getTemplateForUri()
     */
    public function getTemplateForUri(UriInterface $uri) : TemplateInterface
    {
        $url = $uri->getPath() . '?' . $uri->getQuery();
        foreach ($this->getWorkbench()->getConfig()->getOption('TEMPLATE.ROUTES') as $pattern => $templateAlias) {
            if (preg_match($pattern, $url) === 1) {
                return $this->getTemplate($templateAlias);
            }
        }
        throw new TemplateRoutingError('No route can be found for URL "' . $url . '" - please check system configuration option TEMPLATE.ROUTES!');
    }
}

?>