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
    
    /**
     * 
     * @return string
     */
    protected function buildJsIFrameInit() : string
    {
        // Show busy icon on the iframe right after it was instantiate.
        // Hide it when the iframe was loaded and register a listener
        // to show it again when the user is about to navigate inside
        // the iframe.
        // NOTE: this will probably only work with same-origin iframes!
        return <<<JS

        $('#{$this->getId()}').ready(function () {
            var startUrl = '{$this->getWidget()->getValueWithDefaults()}';
            if (startUrl !== '') {
                {$this->buildJsBusyIconShow()}
            }
        });
        $('#{$this->getId()}').on('load', function () {
            {$this->buildJsBusyIconHide()}
            $('#{$this->getId()}')[0].contentWindow.onbeforeunload  = function(){
                {$this->buildJsBusyIconShow()}
            };
        });
JS;
    }
    
    /**
     * 
     * @see AbstractJqueryElement::buildJsRefresh()
     */
    public function buildJsRefresh()
    {
        return "(function(domEl){if (domEl !== undefined) domEl.contentWindow.location.reload() })(document.getElementById('{$this->getId()}'))";
    }
    
    /**
     * 
     * @see AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($valueJs)
    {
        return "document.getElementById('{$this->getId()}').src = {$valueJs}";
    }
}