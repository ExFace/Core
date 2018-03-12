<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

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
        if ($this->getWidget()->getCss()) {
            $output .= '<style>' . $this->getWidget()->getCss() . '</style>';
        }
        if ($caption = $this->getCaption()) {
            $output .= '<label for="' . $this->getId() . '">' . $caption . '</label>';
        }
        
        $style = '';
        if ($this->getWidget()->getMargins()) {
            $style .= 'margin: 10px;';
        }
        
        $output .= '<div id="' . $this->getId() . '" style="' . $style . '">' . $this->getWidget()->getHtml() . '</div>';
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
