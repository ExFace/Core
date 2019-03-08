<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsValueDecoratingInterface;
use exface\Core\Widgets\QrCode;

/**
 *
 * @method QrCode getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryQrCodeTrait
{
    
    /**
     * 
     * @see AbstractJqueryElement::buildHtml()
     */
    public function buildHtml()
    {
        return $this->buildHtmlQrCode();
    }

    /**
     *
     * @see AbstractJqueryElement::buildJs()
     */
    public function buildJs()
    {
        return $this->buildJsQrCodeRenderer();
    }
    
    protected function buildJsQrCodeRenderer() : string
    {
        return '$("#' . $this->getId() . '").children("canvas").remove(); $("#' . $this->getId() . '").qrcode("' . $this->getWidget()->getValueWithDefaults() . '");';
    }
    
    /**
     * Returns the <img> HTML tag with the given source.
     * 
     * @param string $src
     * @return string
     */
    protected function buildHtmlQrCode()
    {
        $style = '';
        if (! $this->getWidget()->getWidth()->isUndefined()) {
            $style .= 'width:' . $this->getWidth() . '; ';
        }
        if (! $this->getWidget()->getHeight()->isUndefined()) {
            $style .= 'height: ' . $this->getHeight() . '; ';
        }
        
        $output = '<div class="' . $this->buildCssElementClass() . '" style="' . $style . '" id="' . $this->getId() . '" />';
        return $output;
    }
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        return <<<JS
'{$this->buildHtmlQrCode("'+" . $value_js . "+'")}'
JS;
    }
        
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $template = $this->getTemplate();
        $includes[] = '<script type="text/javascript" src="' . $template->buildUrlToSource('LIBS.QRCODE.JS') . '"></script>';
        return $includes;
    }
    
    
    
    public function buildCssElementClass()
    {
        return 'exf-qrcode';
    }
}
?>