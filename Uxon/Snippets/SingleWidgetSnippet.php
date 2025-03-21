<?php
namespace exface\Core\Uxon\Snippets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;

class SingleWidgetSnippet extends GenericObjectSnippet
{
    /**
     * Widget to be placed instead of this snippet
     * 
     * @uxon-property widget
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     * @uxon-required true
     * @uxon-template {"widget_type": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return UxonSnippetInterface
     */
    protected function setWidget(UxonObject $uxon) : UxonSnippetInterface
    {
        $this->setSnippet($uxon);
        return $this;
    }
}