<?php
namespace exface\Core\Widgets;

class InputHidden extends Input
{

    protected function init()
    {
        parent::init();
        $this->setHidden(true);
        $this->setVisibility(EXF_WIDGET_VISIBILITY_HIDDEN);
    }
}
?>