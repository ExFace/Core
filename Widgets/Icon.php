<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 * The Icon widget is a Display with an icon beside it.
 *
 * @author Andrej Kabachnik
 *        
 */
class Icon extends Display implements iHaveColor
{
    private $icon = null;
    
    private $size = null;

    private $color = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::getSize()
     */
    public function getSize()
    {
        return $this->size;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::setSize()
     */
    public function setSize($value)
    {
        $this->size = $value;
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
        if (! is_null($this->size)) {
            $uxon->setProperty('size', $this->size);
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