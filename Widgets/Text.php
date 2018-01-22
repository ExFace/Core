<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;

/**
 * The text widget simply shows text with an optional title created from the caption of the widget
 *
 * @author Andrej Kabachnik
 *        
 */
class Text extends Display implements iShowText
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    
    private $text = NULL;

    private $size = null;

    private $style = null;

    public function getText()
    {
        if (is_null($this->text)) {
            return $this->getValue();
        }
        return $this->text;
    }

    public function setText($value)
    {
        $this->text = $value;
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
}
?>