<?php
namespace exface\Core\Widgets;

/**
 * A special variation of DebugMessage for errors.
 * 
 * @author Andrej Kabachnik
 *
 */
class ErrorMessage extends DebugMessage
{
    public function getCaption()
    {
        return $this->translate('ERROR.CAPTION');
    }
}
