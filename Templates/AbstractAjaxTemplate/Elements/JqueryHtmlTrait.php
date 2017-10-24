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

    public function generateHtml()
    {
        $output = '';
        if ($this->getWidget()->getCss()) {
            $output .= '<style>' . $this->getWidget()->getCss() . '</style>';
        }
        if ($this->getWidget()->getCaption() && ! $this->getWidget()->getHideCaption()) {
            $output .= '<label for="' . $this->getId() . '">' . $this->getWidget()->getCaption() . '</label>';
        }
        
        $style = '';
        if ($this->getWidget()->getMargins()) {
            $style .= 'margin: 10px;';
        }
        
        $output .= '<div id="' . $this->getId() . '" style="' . $style . '">' . $this->getWidget()->getHtml() . '</div>';
        return $this->buildHtmlWrapper($output);
    }
    
    public function generateJs()
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
