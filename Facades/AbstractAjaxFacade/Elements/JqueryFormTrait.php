<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Form;

/**
 *
 * @method Form getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryFormTrait {

    function buildHtmlButtons()
    {
        $output = '';
        foreach ($this->getWidget()->getButtons() as $btn) {
            $output .= $this->getFacade()->buildHtml($btn);
        }
        
        return $output;
    }

    function buildJsButtons()
    {
        $output = '';
        foreach ($this->getWidget()->getButtons() as $btn) {
            $output .= $this->getFacade()->buildJs($btn);
        }
        
        return $output;
    }
}
?>