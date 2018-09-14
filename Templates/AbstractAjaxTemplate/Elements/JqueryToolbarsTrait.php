<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate;

/**
 * TODO use template elements for Toolbar and ButtonGroup instead of this trait.
 * 
 * @method iHaveToolbars getWidget()
 * @method AbstractAjaxTemplate getTemplate()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryToolbarsTrait 
{
    
    protected function buildHtmlToolbars()
    {
        $output = '';
        foreach ($this->getWidget()->getToolbars() as $toolbar){
            $output .= $this->getTemplate()->getElement($toolbar)->buildHtml();
        }
        return $output;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsButtons(){
        $output = '';
        foreach ($this->getWidget()->getToolbars() as $toolbar) {
            $output .= $this->getTemplate()->buildJs($toolbar);
        }
        return $output;
    }
    
    protected function buildJsToolbars(){
        return $this->buildJsButtons();
    }
}
