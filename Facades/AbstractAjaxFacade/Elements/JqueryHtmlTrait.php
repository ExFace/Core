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
        if ($widget->getCss()) {
            $output .= '<style>' . $widget->getCss() . '</style>';
        }
        if ($caption = $this->getCaption()) {
            $output .= '<label for="' . $this->getId() . '">' . $caption . '</label>';
        }
        
        $style = '';
        if ($widget->getMargins()) {
            $style .= 'margin: 10px;';
        }
        if ($widget->getInline() === true) {
            $style .= 'display: inline-block;';
        }
        
        $output .= '<div id="' . $this->getId() . '" style="' . $style . '" class="' . $this->buildCssElementClass() . '">' . $widget->getHtml() . '</div>';
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
