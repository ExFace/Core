<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Image;

/**
 *
 * @method Image getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait HtmlImageTrait
{

    function generateHtml()
    {
        $style = '';
        if (! $this->getWidget()->getWidth()->isUndefined()) {
            $width = ' width="' . $this->getWidth() . '"';
        }
        if (! $this->getWidget()->getHeight()->isUndefined()) {
            $height = ' height="' . $this->getHeight() . '"';
        }
        
        switch ($this->getWidget()->getAlign()) {
            case EXF_ALIGN_CENTER:
                $style .= 'margin-left: auto; margin-right: auto;';
                break;
            case EXF_ALIGN_RIGHT:
                $style .= 'float: right';
        }
        
        $output = '<img src="' . $this->getWidget()->getUri() . '"' . $width . $height . ' class="' . $this->buildCssElementClass() . '" style="' . $style . '" />';
        return $output;
    }

    function generateJs()
    {
        return '';
    }
}
?>