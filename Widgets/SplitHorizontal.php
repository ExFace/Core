<?php
namespace exface\Core\Widgets;

/**
 * A horizontal split displays it's panels side-by-side.
 * 
 * For panels with `resizable` set to true, the user can change the width by dragging the panel's border.
 * 
 * This widget is the same as a generic `Split` with `orientation` set to `horizontal`.
 *
 * @author Andrej Kabachnik
 *        
 */
class SplitHorizontal extends SplitVertical
{
    // TODO add stacking options
    
    public function getOrientation($default = null): string
    {
        return split::ORIENTATION_HORIZONTAL;
    }
    
    /**
     * Setting orientation for a horizontal split does not make sense - it is allways horizontal!
     *
     * No UXON annotations here!
     *
     * @param string $value
     * @return SplitVertical
     */
    public function setOrientation(string $value) : Split
    {
        return $this;
    }
}