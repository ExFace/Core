<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 * The text widget simply shows text with an optional title created from the caption of the widget
 *
 * @author Andrej Kabachnik
 *        
 */
class Text extends Display implements iShowText, iHaveColor
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    
    private $text = NULL;

    private $size = null;

    private $style = null;
    
    private $color = null;

    public function getText()
    {
        if (is_null($this->text)) {
            return $this->getValue();
        }
        return $this->text;
    }

    public function setText($value)
    {
        $this->text = $this->translateValue($value);
        return $this;
    }
    
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::getStyle()
     */
    public function getStyle()
    {
        return $this->style;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowText::setStyle()
     */
    public function setStyle($value)
    {
        $this->style = $value;
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
        if (! is_null($this->style)) {
            $uxon->setProperty('style', $this->style);
        }
        if (! is_null($this->align)) {
            $uxon->setProperty('align', $this->align);
        }
        if (! is_null($this->text)) {
            $uxon->setProperty('text', $this->text);
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
}
?>