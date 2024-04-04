<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\DataTypes\FilePathDataType;

/**
 * A Pad is a container with margins/paddings, that allows to place smaller content in the center of the screen.
 *     
 * @author Andrej Kabachnik
 *        
 */
class Pad extends Container implements iFillEntireContainer
{
    private $centerContent = true;
    
    private $backgroundImage = null;
    
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
    
    /**
     * 
     * @return string|NULL
     */
    public function getBackgroundImageURL() : ?string
    {
        return FilePathDataType::normalize($this->backgroundImage, '/');
    }
    
    /**
     * 
     * @return bool
     */
    public function hasBackgroundImage() : bool
    {
        return $this->backgroundImage !== null;
    }
    
    /**
     * Sets an image as background - the image is set via URI relative to workbench root
     * 
     * @uxon-property background_image
     * @uxon-type uri
     * 
     * @param string $value
     * @return Pad
     */
    public function setBackgroundImage(string $value) : Pad
    {
        $this->backgroundImage = $value;
        return $this;
    }
}