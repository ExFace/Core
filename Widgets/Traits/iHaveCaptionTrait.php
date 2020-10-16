<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\CommonLogic\UxonObject;

/**
 * This trait adds the caption property to a widget or a widget part.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveCaptionTrait {
    
    use TranslatablePropertyTrait;
    
    private $hide_caption = null;
    
    private $caption = null;
    
    /**
     * Sets the caption or title of the widget.
     *
     * @uxon-property caption
     * @uxon-type string|metamodel:formula
     * @uxon-translatable true
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::setCaption()
     */
    public function setCaption($caption, bool $forceCaptionVisible = true) : iHaveCaption
    {
        $this->caption = $this->evaluatePropertyExpression($caption);
        if ($this->hide_caption === null && $forceCaptionVisible === true) {
            $this->setHideCaption(false);
        }
        return $this;
    }    
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        return $this->caption;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveCaption::getHideCaption()
     */
    public function getHideCaption() : ?bool
    {
        return $this->hide_caption;
    }
    
    /**
     * Set to TRUE to hide the caption of the widget and to FALSE to force showing it.
     * 
     * If not set explicitly, it is upto the widget logic and the facade to decide,
     * if the caption is to be shown.
     *
     * @uxon-property hide_caption
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritdoc}
     *
     * @see iHaveCaption::setHideCaption()
     */
    public function setHideCaption(bool $value) : iHaveCaption
    {
        $this->hide_caption = $value;
        return $this;
    }
    
    /**
     * Adds properties caption and hide_caption to give UXON if they were explicitly set.
     * 
     * @param UxonObject $uxon
     * @return UxonObject
     */
    protected function exportUxonObjectAddCaptionPoperties(UxonObject $uxon) : UxonObject
    {
        if ($this->caption !== null) {
            $uxon->setProperty('caption', $this->caption);
        }
        if ($this->hide_caption !== null) {
            $uxon->setProperty('hide_caption', $this->hide_caption);
        }
        return $uxon;
    }
}