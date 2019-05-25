<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;
use exface\Core\Widgets\Browser;
/**
 * Renders an HTML IFrame for a Browser widget
 * 
 * @method Browser getWidget()
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
        $url = $this->getWidget()->getUrlBase() . $this->getWidget()->getUrl();
        return <<<HTML
<iframe src="{$url}" style="{$this->buildCssElementStyle()}" seamless></iframe>
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
    
    /**
     * Wraps the given content in a sap.m.Page with back-button, that works with the iFrame.
     *
     * @param string $contentJs
     * @param string $footerConstructor
     * @param string $headerContentJs
     *
     * @return string
     */
    protected function buildJsPageWrapper(string $contentJs) : string
    {
        $caption = $this->getCaption();
        if ($caption === '' && $this->getWidget()->hasParent() === false) {
            $caption = $this->getWidget()->getPage()->getName();
        }
        
        return <<<JS
        
        new sap.m.Page({
            title: "{$caption}",
            showNavButton: true,
            navButtonPress: function(){window.history.go(-1);},
            content: [
                {$contentJs}
            ],
            headerContent: [
                
            ]
        })
        
JS;
    }
}