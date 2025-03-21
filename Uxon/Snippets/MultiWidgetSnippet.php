<?php
namespace exface\Core\Uxon\Snippets;

use exface\Core\CommonLogic\UxonObject;

class MultiWidgetSnippet extends GenericArraySnippet
{
    /**
     * Widgets to be placed instead of this snippet
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\AbstractWidget[]
     * @uxon-required true
     * @uxon-template [{"widget_type": ""}]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxonArray
     * @return MultiWidgetSnippet
     */
    protected function setWidgets(UxonObject $uxonArray) : MultiWidgetSnippet
    {
        $this->setSnippet($uxonArray);
        return $this;
    }
}