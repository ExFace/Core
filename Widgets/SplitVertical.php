<?php
namespace exface\Core\Widgets;

/**
 * A vertical split lists it's panels below each other.
 * 
 * The widget takes all available vertical space and distributes it between
 * all it's panels. For panels with `resizable` set to true, the user can change
 * the height by dragging the panel's border.
 * 
 * This widget is the same as a generic `Split` with `orientatino` set to `vertical`.
 *
 * @author Andrej Kabachnik
 *        
 */
class SplitVertical extends Split
{
    
    protected function getOrientation(): string
    {
        return split::ORIENTATION_VERTICAL;
    }
    
    /**
     * Setting orientation for a vertical split does not make sense - it is allways vertical!
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