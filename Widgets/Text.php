<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;

/**
 * The text widget simply shows text with an optional title (created from the caption of the widget).
 *
 * @author Andrej Kabachnik
 *        
 */
class Text extends Display implements iShowText
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }

    private $size = null;

    private $style = null;

    public function getText()
    {
        return $this->getValue();
    }

    /**
     * Sets the text to be shown explicitly.
     * 
     * This property has the same effect as setting `value`. It also supports formulas.
     * 
     * @uxon-property text
     * @uxon-type string|metamodel:formula
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Text
     */
    public function setText($value)
    {
        $this->setValue($this->evaluatePropertyExpression($value));
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
     * Sets the style of the text: normal, big, small.
     * 
     * @uxon-property style
     * @uxon-type [normal,big,small]
     * @uxon-default normal
     * 
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
     * Sets the style of the text: normal, bold, underline, strikethrough, italic.
     * 
     * @uxon-property style
     * @uxon-type [normal,bold,underline,strikethrough,italic]
     * @uxon-default normal
     * 
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
        return $uxon;
    }
}
?>