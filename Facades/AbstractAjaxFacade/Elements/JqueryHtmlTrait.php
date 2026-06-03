<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Html;

/**
 *
 * @method Html getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryHtmlTrait {

    public function buildHtml()
    {
        $output = '';
        $widget = $this->getWidget();
        $style = $widget->getCssInlineStyle() ?? '';
        
        if ($widget->getCss()) {
            $output .= '<style>' . $widget->getCss() . '</style>';
        }
        if ($caption = $this->getCaption()) {
            $output .= '<label for="' . $this->getId() . '">' . $caption . '</label>';
        } else {
            $style .= 'width: 100%';
        }
        
        if ($widget->getMargins()) {
            $style .= 'margin: 10px;';
        }
        if ($widget->getInline() === true) {
            $style .= 'display: inline-block;';
        }
        
        $valueHtml = $widget->getHtml();
        if (null !== $tpl = $widget->getHtmlTemplate()) {
            $valueHtml = str_replace('[#~value#]', $valueHtml, $tpl);
        }
        
        $output .= '<div id="' . $this->getId() . '" style="' . $style . '" class="' . $this->buildCssElementClass() . '">' . $valueHtml . '</div>';
        return $this->buildHtmlGridItemWrapper($output);
    }
    
    public function buildJs()
    {
        return $this->getWidget()->getJavascript();
    }
    
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()){
            return 'auto';
        } else {
            return parent::getHeight();
        }
    }
    
}
?>