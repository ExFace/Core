<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

/**
 * Renders an HTML IFrame for a Browser widget
 * 
 * @method \exface\Core\Widgets\Browser getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait HtmlBrowserTrait 
{
    /**
     * 
     * @return string
     */
    protected function buildHtmlIFrame() : string
    {
        $url = $this->getWidget()->getUrl();
        
        if ($base = $this->getWidget()->getBaseUrl()) {
            $url = rtrim($base, "/") . '/' . ltrim($url, "/");
        }
        
        return <<<HTML
<iframe src="{$url}" style="{$this->buildCssElementStyle()}" id="{$this->getId()}" name="{$this->getId()}" seamless></iframe>
HTML;
    }
        
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementStyle()
     */
    public function buildCssElementStyle()
    {
        return 'width: 100%; height: 100%; border: 0;';
    }
}