<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Interfaces\Widgets\iCanWrapText;

/**
 * Displays multiline text with an optional title (created from the caption of the widget) and some simple formatting.
 * You **must** use text or value as attribute for this widget to fill the text.
 * 
 * In contrast to the more generic `Display` widget, `Text` allows line breaks and will wrap long values. It also
 * allows some simple formatting like `style`, `size` and `align` though they are very inconsistent between ui's.
 *
 *  ```
 *  {
 *      "value": "=IfNull(Bemerkung, '')",
 *      "widget_type": "Text",
 *      "width": 1,
 *      "hide_caption": true
 *  }
 *
 *  ```
 *
 * @author Andrej Kabachnik
 *        
 */
class Text extends Display implements iShowText, iCanWrapText
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }

    private $size = null;

    private $style = null;
    
    private $multiLine = true;
    
    private $multiLineMaxLines = null;

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
     * @uxon-translatable true
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Text
     */
    public function setText($value)
    {
        // Evaluate statif formulas right here, but leave dynamic formulas to be handled by the value
        if (Expression::detectFormula($value)) {
            $expr = ExpressionFactory::createFromString($this->getWorkbench(), $value);
            if ($expr->isStatic()) {
                $value = $expr->evaluate() ?? '';
            }
        }
        $this->setValue($value);
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
     * Sets the size of the text: normal, big, small.
     * 
     * @uxon-property size
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
        $this->style = strtolower($value);
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
    
    /**
     * 
     * @return bool
     */
    public function isMultiLine() : bool
    {
        return $this->multiLine;
    }
    
    /**
     * Set to FALSE to force a single-line text widget
     * 
     * @uxon-property multi_line
     * @uxon-type boolean
     * @uxon-default true
     * 
     * 
     * @param bool $value
     * @return Text
     */
    public function setMultiLine(bool $value) : Text
    {
        $this->multiLine = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanWrapText::getNowrap()
     */
    public function getNowrap(): bool
    {
        return ! $this->isMultiLine();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanWrapText::setNowrap()
     */
    public function setNowrap(bool $value): iCanWrapText
    {
        return $this->setMultiLine(! $value);
    }

    /**
     * 
     * @return int|NULL
     */
    public function getMultiLineMaxLines() : ?int
    {
        return $this->multiLineMaxLines;
    }
    
    /**
     * Limit the maximum number of lines rendered for a multi-line text widget
     * 
     * This can be useful in tables or other situations with limited vertical space. The exact behavior
     * will depend on the facade used. The text will mostly get truncated, but the tooltip (if present)
     * will still include the entire text.
     * 
     * @uxon-property multi_line_max_lines
     * @uxon-type integer
     * 
     * @param int $value
     * @return Text
     */
    public function setMultiLineMaxLines($value) : Text
    {
        $value = intval($value);
        if ($value === 0) {
            $value = null;
        }
        $this->multiLineMaxLines = $value;
        return $this;
    }
}