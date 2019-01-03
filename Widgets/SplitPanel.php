<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iAmResizable;
use exface\Core\Interfaces\Widgets\iHaveBorders;

/**
 * A SplitPanel is a special panel, which can be resized within a split layout (e.g.
 * by dragging it's internal border).
 *
 * @author PATRIOT
 */
class SplitPanel extends Panel implements iAmResizable, iHaveBorders
{
    /** @var boolean makes it possible to resize this panel within the layout */
    private $resizable = true;

    private $show_border = true;

    public function getResizable() : bool
    {
        return $this->resizable;
    }

    /**
     * Makes it possible to resize this panel within the layout if set to TRUE.
     * 
     * Resizing one panel will generally also resize a neighbour panel, since 
     * the total size of the layout remains.
     * 
     * @uxon-property resizable
     * @uxon-type boolean
     * @uxon-default true
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveBorders::setResizable()            
     */
    public function setResizable(bool $value) : SplitPanel
    {
        $this->resizable = $value;
    }

    public function getShowBorder() : bool
    {
        return $this->show_border;
    }

    /**
     * Set to FALSE to hide the borders of the panel.
     * 
     * @uxon-property show_border
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveBorders::setShowBorder()
     */
    public function setShowBorder(bool $value) : SplitPanel
    {
        $this->show_border = $value;
    }
}