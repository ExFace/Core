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
}