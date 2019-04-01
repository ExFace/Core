<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;
use exface\Core\Widgets\Browser;
/**
 * Renders a browser widget as an iFrame
 * 
 * @method Browser getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait HtmlBrowserTrait 
{
    public function buildHtml()
    {
        $url = $this->getWidget()->getUrlBase() . $this->getWidget()->getUrl();
        return <<<HTML
<iframe src="{$url}" style="{$this->buildCssElementStyle()}" seamless></iframe>
HTML;
    }
    
    public function buildJs()
    {
        return '';
    }
}