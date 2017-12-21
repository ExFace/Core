<?php
namespace exface\Core\Widgets;

/**
 * This is a special text input widget for JSON editing.
 * Templates may have a WYSIWYG editor for JSON or should fall back to a regular
 * text input
 *
 * @author Andrej Kabachnik
 *        
 */
class InputJson extends InputText
{
    /**
     * If not set explicitly, the width of a JSON editor will be "max".
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
    public function getWidth()
    {
        $width = parent::getWidth();
        if ($width->isUndefined()) {
            $width->parseDimension('max');
        }
        return $width;
    }
}
?>