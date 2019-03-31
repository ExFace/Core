<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Image;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;

/**
 *
 * @method Image getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait HtmlImageTrait
{
    use JqueryAlignmentTrait;
    
    /**
     * 
     * @see AbstractJqueryElement::buildHtml()
     */
    public function buildHtml()
    {
        return $this->buildHtmlImage($this->getWidget()->getUri());
    }

    /**
     *
     * @see AbstractJqueryElement::buildJs()
     */
    public function buildJs()
    {
        return '';
    }
    
    /**
     * Returns the <img> HTML tag with the given source.
     * 
     * @param string $src
     * @return string
     */
    protected function buildHtmlImage($src)
    {
        $style = '';
        if (! $this->getWidget()->getWidth()->isUndefined()) {
            $style .= 'width:' . $this->getWidth() . '; ';
        }
        if (! $this->getWidget()->getHeight()->isUndefined()) {
            $style .= 'height: ' . $this->getHeight() . '; ';
        }
        
        switch ($this->getWidget()->getAlign()) {
            case EXF_ALIGN_CENTER:
                $style .= 'margin-left: auto; margin-right: auto;';
                break;
            case EXF_ALIGN_RIGHT:
                $style .= 'float: right';
        }
        
        $output = '<img src="' . $src . '" class="' . $this->buildCssElementClass() . '" style="' . $style . '" id="' . $this->getId() . '" />';
        return $output;
    }
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        if ($base = $this->getWidget()->getBaseUrl()) {
            $value_js = "'{$base}'+" . $value_js;
        }
        
        if ($this->getWidget()->getUseProxy()) {
            return <<<JS
function() {
    var url = encodeURI({$value_js});
    var proxyUrl = "{$this->getWidget()->buildProxyUrl('xxurixx')}";
    proxyUrl = proxyUrl.replace("xxurixx", url);
    return '{$this->buildHtmlImage("'+proxyUrl+'")}'
}()

JS;
        }
        return <<<JS
'{$this->buildHtmlImage("'+" . $value_js . "+'")}'
JS;
    }
}
?>