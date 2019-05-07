<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;

/**
 * This trait adds the caption property to a widget or a widget part.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveCaptionTrait {
    
    use TranslatablePropertyTrait;
    
    private $hide_caption = false;
    
    private $caption = null;
    
    /**
     * Sets the caption or title of the widget.
     *
     * @uxon-property caption
     * @uxon-type string|metamodel:formula
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::setCaption()
     */
    public function setCaption($caption)
    {
        $this->caption = $this->evaluatePropertyExpression($caption);
        return $this;
    }    
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveCaption::getCaption()
     */
    function getCaption()
    {
        return $this->caption;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveCaption::getHideCaption()
     */
    public function getHideCaption()
    {
        return $this->hide_caption;
    }
    
    /**
     * Set to TRUE to hide the caption of the widget.
     *
     * @uxon-property hide_caption
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritdoc}
     *
     * @see iHaveCaption::setHideCaption()
     */
    public function setHideCaption($value)
    {
        $this->hide_caption = $value;
        return $this;
    }
}