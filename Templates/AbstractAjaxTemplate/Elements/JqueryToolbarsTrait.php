<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;

/**
 * TODO use facade elements for Toolbar and ButtonGroup instead of this trait.
 * 
 * @method iHaveToolbars getWidget()
 * @method AbstractAjaxFacade getFacade()
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
            $output .= $this->getFacade()->getElement($toolbar)->buildHtml();
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
            $output .= $this->getFacade()->buildJs($toolbar);
        }
        return $output;
    }
    
    protected function buildJsToolbars(){
        return $this->buildJsButtons();
    }
}
