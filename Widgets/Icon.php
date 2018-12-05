<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 * The Icon widget is a standard Display widget with an icon beside it.
 * 
 * @see Display
 *
 * @author Andrej Kabachnik
 *        
 */
class Icon extends Display implements iHaveColor
{
    private $icon = null;
    
    private $iconSize = null;
    
    private $iconPosition = EXF_ALIGN_LEFT;

    private $color = null;
    
    /**
     * 
     * @return string|NULL
     */
    public function getIconSize() : ?string
    {
        return $this->iconSize;
    }
    
    /**
     * Sets the size of the icon (SMALL, NORMAL, BIG)
     * 
     * @uxon-property icon_size
     * @uxon-type [small|normal|big]
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Icon
     */
    public function setIconSize($value) : Icon
    {
        $this->iconSize = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getIconPosition() : string
    {
        return $this->iconPosition;
    }
    
    /**
     * Sets the position of the icon relativ to the caption/value (LEFT, RIGHT, CENTER).
     * 
     * @uxon-property icon_position
     * @uxon-type [left|right|center]
     * 
     * @param string $value
     * @return Icon
     */
    public function setIconPosition(string $value) : Icon
    {
        $this->iconPosition = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (! is_null($this->iconSize)) {
            $uxon->setProperty('icon_size', $this->iconSize);
        }
        if (! is_null($this->iconAlignment)) {
            $uxon->setProperty('icon_alignment', $this->iconAlignment);
        }
        if (! is_null($this->icon)) {
            $uxon->setProperty('icon', $this->icon);
        }
        if (! is_null($this->color)) {
            $uxon->setProperty('color', $this->color);
        }
        return $uxon;
    }
    
    /**
     * Returns the color of the text or NULL if no color explicitly defined.
     * 
     * {@inheritdoc}
     * @see iHaveColor::getColor()
     */
    public function getColor()
    {
        return $this->color;
    }
    
    /**
     * Sets a specific color for the text - if not set, templates will use their own color scheme.
     *
     * HTML color names are supported by default. Additionally any color selector supported by
     * the current template can be used. Most HTML templates will support css colors.
     *
     * @link https://www.w3schools.com/colors/colors_groups.asp
     *
     * @uxon-property color
     * @uxon-type string
     *
     * {@inheritdoc}
     * @see iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->color = $color;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getIcon() : string
    {
        return $this->icon;
    }
    
    /**
     * Sets the name of the icon to be displayed - any name supported by the template is OK.
     * 
     * @uxon-property icon
     * @uxon-type string
     * 
     * @param string $value
     * @return Icon
     */
    public function setIcon(string $value) : Icon
    {
        $this->icon = $value;
        return $this;
    }
}