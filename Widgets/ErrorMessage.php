<?php
namespace exface\Core\Widgets;

class ErrorMessage extends DebugMessage
{

    public function getCaption()
    {
        return $this->translate('ERROR.CAPTION');
    }
}
