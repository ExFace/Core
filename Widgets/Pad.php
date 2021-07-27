<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * A Pad is a container with margins/paddings, that allows to place smaller content in the center of the screen.
 *     
 * @author Andrej Kabachnik
 *        
 */
class Pad extends Container implements iFillEntireContainer
{
    private $centerContent = true;
    
    public function getCenterContent() : bool
    {
        return $this->centerContent;
    }
    
    /**
     * 
     * @param bool $value
     * @return Pad
     */
    public function setCenterContent(bool $value) : Pad
    {
        $this->centerContent = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        if ($filler = $this->getFillerWidget()) {
            if ($alternative = $filler->getAlternativeContainerForOrphanedSiblings()) {
                return $alternative;
            }
        }
        return $this;
    }
}