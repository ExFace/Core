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
    public function getCaption() : ?string
    {
        return $this->translate('ERROR.CAPTION');
    }
}
