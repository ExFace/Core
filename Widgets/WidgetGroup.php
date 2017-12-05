<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iAmCollapsible;
use exface\Core\Widgets\Traits\iAmCollapsibleTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;

class WidgetGroup extends WidgetGrid implements iAmCollapsible
{
    use iAmCollapsibleTrait;
    use iHaveIconTrait;
    
    protected function init()
    {
        $this->setWidth(1);
    }
}
?>